<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\WebhookSignature;
use Tests\TestCase;

/**
 * Authentification des appels de machine à machine.
 *
 * Ces points d'entrée soldent des factures et modifient l'état de colis. Ils étaient
 * ouverts à tous : les scénarios ci-dessous vérifient qu'un appel non signé, mal signé
 * ou rejoué plus tard est bien refusé.
 */
final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'secret-de-test-0123456789abcdef';
    private const CORPS = '{"facture_id":3,"transaction_reference":"TX-1","montant":55000,"statut":"success"}';

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['LBP_WEBHOOK_SECRET'] = self::SECRET;
        unset($_SERVER['LBP_WEBHOOK_IPS'], $_SERVER[WebhookSignature::ENTETE_SIGNATURE], $_SERVER[WebhookSignature::ENTETE_HORODATAGE]);
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['LBP_WEBHOOK_SECRET'],
            $_SERVER['LBP_WEBHOOK_IPS'],
            $_SERVER[WebhookSignature::ENTETE_SIGNATURE],
            $_SERVER[WebhookSignature::ENTETE_HORODATAGE]
        );
        parent::tearDown();
    }

    public function test_un_appel_correctement_signe_est_accepte(): void
    {
        $this->signer(self::CORPS);

        self::assertTrue(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_un_appel_sans_signature_est_refuse(): void
    {
        // C'est exactement la situation d'avant : un simple POST du corps JSON.
        $resultat = WebhookSignature::verifier(self::CORPS);

        self::assertFalse($resultat['ok']);
        self::assertStringContainsString('absent', $resultat['motif']);
    }

    public function test_une_signature_falsifiee_est_refusee(): void
    {
        $this->signer(self::CORPS);
        $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = 'sha256=' . str_repeat('a', 64);

        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_un_corps_modifie_apres_signature_est_refuse(): void
    {
        $this->signer(self::CORPS);

        // Le montant est passé de 55 000 à 550 000 après signature.
        $altere = str_replace('55000', '550000', self::CORPS);

        self::assertFalse(WebhookSignature::verifier($altere)['ok']);
    }

    public function test_un_appel_rejoue_plus_tard_est_refuse(): void
    {
        $this->signer(self::CORPS, time() - 3600);

        $resultat = WebhookSignature::verifier(self::CORPS);

        self::assertFalse($resultat['ok']);
        self::assertStringContainsString('fenêtre', $resultat['motif']);
    }

    public function test_un_horodatage_dans_le_futur_est_refuse(): void
    {
        $this->signer(self::CORPS, time() + 3600);

        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_un_leger_decalage_d_horloge_reste_tolere(): void
    {
        $this->signer(self::CORPS, time() - 120);

        self::assertTrue(WebhookSignature::verifier(self::CORPS)['ok'], 'Deux minutes d\'écart doivent passer.');
    }

    public function test_un_horodatage_non_numerique_est_refuse(): void
    {
        $this->signer(self::CORPS);
        $_SERVER[WebhookSignature::ENTETE_HORODATAGE] = 'hier';

        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_sans_secret_configure_tout_est_refuse(): void
    {
        $this->signer(self::CORPS);
        unset($_SERVER['LBP_WEBHOOK_SECRET']);

        // Aucune base n'est accessible dans ce contexte : le repli échoue aussi,
        // et un point d'entrée non configuré doit rester fermé.
        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_la_liste_d_adresses_filtre_l_appelant(): void
    {
        $this->signer(self::CORPS);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';

        $_SERVER['LBP_WEBHOOK_IPS'] = '203.0.113.4, 203.0.113.5';
        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok'], 'Adresse hors liste.');

        $_SERVER['LBP_WEBHOOK_IPS'] = '203.0.113.4, 198.51.100.9';
        self::assertTrue(WebhookSignature::verifier(self::CORPS)['ok'], 'Adresse dans la liste.');
    }

    public function test_une_liste_vide_n_impose_aucune_restriction(): void
    {
        $this->signer(self::CORPS);
        $_SERVER['LBP_WEBHOOK_IPS'] = '   ';

        self::assertTrue(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    public function test_deux_secrets_differents_ne_se_valident_pas(): void
    {
        $signe = WebhookSignature::signer(self::CORPS, 'un-autre-secret');
        $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = $signe['signature'];
        $_SERVER[WebhookSignature::ENTETE_HORODATAGE] = $signe['timestamp'];

        self::assertFalse(WebhookSignature::verifier(self::CORPS)['ok']);
    }

    // -----------------------------------------------------------------

    private function signer(string $corps, ?int $horodatage = null): void
    {
        $signe = WebhookSignature::signer($corps, self::SECRET, $horodatage);

        $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = $signe['signature'];
        $_SERVER[WebhookSignature::ENTETE_HORODATAGE] = $signe['timestamp'];
    }
}
