<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelIA;
use PHPUnit\Framework\TestCase;

final class NoelIATest extends TestCase
{
    public function testSansIAConfigureeNoelGardeSesRegles(): void
    {
        $ia = new NoelIA(['ia' => 'aucun']);

        self::assertNull($ia->repondre('Comment je fais ?', new NoelCarte()));
    }

    public function testSansAucunEcranLIALeSaitEtNInventeRien(): void
    {
        // Port fermé : l'appel échoue vite, la réponse est null, aucune exception.
        $ia = new NoelIA(['ia' => 'ollama', 'ia_url' => 'http://127.0.0.1:9', 'ia_delai_secondes' => 10]);
        $texte = @$ia->connaissances('Comment encaisser une facture ?', []);

        self::assertStringContainsString('aucun', $texte);
    }

    public function testLaReflexionVisibleEstRetiree(): void
    {
        self::assertSame('Ouvrez Finance > Factures Clients.', NoelIA::nettoyer("<think>je réfléchis</think>\n\nOuvrez Finance > Factures Clients."));
        self::assertNull(NoelIA::nettoyer('<think>rien</think>   '));
        // Réflexion dont seule la fin est marquée : on garde ce qui suit.
        self::assertSame('Désolé Serge, je transmets.', NoelIA::nettoyer("Okay, let's see...\n</think>\n\nDésolé Serge, je transmets."));
    }

    public function testLIANeLitQueLesEcransDeLaPersonneEtLaProcedureProche(): void
    {
        $texte = (new NoelIA(['ia' => 'ollama']))->connaissances('comment encaisser un reglement de facture', [
            'finance' => ['label' => 'Finance', 'ecrans' => [['label' => 'Factures Clients', 'url' => 'finance/factures']]],
        ]);

        self::assertStringContainsString('Finance : Factures Clients', $texte);
        self::assertStringContainsString('Encaisser un règlement', $texte);
        self::assertStringNotContainsString('Ressources humaines', $texte);
    }

    public function testUnMontantInventeParLIAEstRejete(): void
    {
        $source = "FAITS SÛRS\nEncaissé aujourd'hui : 125 000 FCFA sur 4 factures.";

        self::assertTrue(NoelIA::chiffresFideles('Vous avez encaissé 125 000 FCFA aujourd’hui.', $source));
        self::assertTrue(NoelIA::chiffresFideles('1. Ouvrez Finance. 2. Vérifiez les 4 factures.', $source), 'Les petits nombres et numéros d’étape ne sont pas contrôlés.');
        self::assertFalse(NoelIA::chiffresFideles('Vous avez encaissé 152 000 FCFA aujourd’hui.', $source));
        self::assertFalse(NoelIA::chiffresFideles('Le colis LB-CI-0042 est parti.', $source));
    }

    public function testLaBibliothequeNeMontreQueLesModulesDeLaPersonne(): void
    {
        $dossier = sys_get_temp_dir() . '/noel_bib_' . bin2hex(random_bytes(4));
        mkdir($dossier);
        file_put_contents($dossier . '/general.md', "# Général\n## La fiche d'engagement\nLe client signe la fiche d'engagement au verso de la facture.\n");
        file_put_contents($dossier . '/rh.md', "# RH\n## Les salaires\nLa fiche d'engagement des salaires est confidentielle.\n");
        file_put_contents($dossier . '/colisage.md', "# Colisage\n## Fiche d'engagement et colis\nOn imprime la fiche d'engagement avec la facture du colis.\n");

        try {
            $ia = new NoelIA(['ia' => 'ollama', 'ia_connaissances' => $dossier]);
            $vu = $ia->extraits("Pourquoi faire signer la fiche d'engagement ?", ['colisage'], 4);

            self::assertStringContainsString('verso de la facture', $vu);
            self::assertStringContainsString('facture du colis', $vu);
            self::assertStringNotContainsString('salaires', $vu, 'Un module réservé ne fuit jamais par la bibliothèque.');
        } finally {
            array_map('unlink', glob($dossier . '/*.md') ?: []);
            rmdir($dossier);
        }
    }
}
