<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelVeille;
use Tests\TestCase;

/**
 * Le gardien : ce qui attend chaque personne, et qu'elle a peut-être oublié.
 *
 * La direction est déjà prévenue des oublis par CronAlertesDirection : passé
 * dix-sept heures, elle reçoit la liste des agences non closes. Mais prévenir
 * le directeur qu'une caissière a oublié, c'est arriver après la faute. NOEL
 * prévient celle qui doit agir, pendant qu'il est encore temps.
 */
final class NoelVeilleTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelVeille.php');
    }

    public function test_les_alertes_sont_du_sql_jamais_de_l_intelligence_artificielle(): void
    {
        /*
         * Décidé avec l'administrateur le 22/10/2026. « Le point de caisse
         * n'est pas soumis » se sait avec certitude pour trois francs ; un
         * modèle de langage le devinerait, parfois faux, et serait facturé à
         * chaque fois. Les règles calculent, NOEL met en mots.
         */
        $source = $this->source();

        self::assertStringContainsString('SELECT', $source);
        self::assertStringNotContainsString('cle_api', $source);
        self::assertStringNotContainsString('NoelClient', $source);
        self::assertStringNotContainsString('api.anthropic', $source);
    }

    public function test_chaque_regle_commence_par_verifier_le_droit_d_agir(): void
    {
        /*
         * On ne prévient que ceux qui peuvent faire quelque chose. Annoncer à
         * une caissière que des demandes de fonds attendent une décision
         * qu'elle ne peut pas prendre ne sert qu'à l'inquiéter.
         *
         * Et surtout : les listes de rôles sont empruntées aux écrans
         * eux-mêmes, jamais recopiées. Une copie dérive — c'est déjà arrivé
         * sur les demandes de fonds, où la liste reconnaissait le comptable
         * que la fiche avait oublié.
         */
        $source = $this->source();

        self::assertStringContainsString('FinanceController::ROLES_SOUMISSION_POINT', $source);
        self::assertStringContainsString('DemandesFondsAcces::peutValider()', $source);
        self::assertStringContainsString('DemandesFondsAcces::peutDecaisser()', $source);
        self::assertStringContainsString('ApproCaisseAcces::peutValider()', $source);

        // Aucune liste de rôles écrite à la main dans ce fichier.
        self::assertStringNotContainsString("'caissiere_principale'", $source);
        self::assertStringNotContainsString("'comptable'", $source);
        self::assertStringNotContainsString("hasRole('", $source);
    }

    public function test_le_point_de_caisse_reprend_la_regle_deja_en_place(): void
    {
        /*
         * « Soumis » ou « consolidé » valent clôture, exactement comme dans
         * CronAlertesDirection. Deux définitions du même fait finiraient par
         * se contredire, et personne ne saurait laquelle croire.
         */
        $veille = $this->source();
        $cron = (string) file_get_contents(
            BASE_PATH . '/app/Services/Mobile/NotificationDirectionService.php'
        );

        self::assertStringContainsString("statut IN ('soumis', 'consolide')", $veille);
        self::assertStringContainsString("statut IN ('soumis', 'consolide')", $cron);
    }

    public function test_noel_ne_previent_que_pour_sa_propre_agence(): void
    {
        /*
         * Annoncer à une caissière de Dokui qu'Adjamé n'a pas clôturé ne sert
         * à rien, sinon à lui faire croire qu'elle doit s'en occuper.
         */
        $source = $this->source();

        self::assertStringContainsString('Auth::agenceId()', $source);
        self::assertStringContainsString('agence_id = :agence', $source);
    }

    public function test_noel_se_tait_avant_la_fin_de_journee(): void
    {
        /*
         * Un rappel toutes les heures depuis le matin ferait de NOEL une
         * nuisance qu'on apprend à ignorer — y compris le soir où il a raison.
         */
        $source = $this->source();

        self::assertSame(16, NoelVeille::HEURE_RAPPEL_POINT);
        self::assertStringContainsString('$heure < self::HEURE_RAPPEL_POINT', $source);
    }

    public function test_dans_le_doute_le_gardien_se_tait(): void
    {
        /*
         * Si la base ne répond pas, on considère le point comme soumis. Un
         * gardien qui crie faute de savoir est pire qu'un gardien muet : on
         * cesse de l'écouter, y compris le jour où il a raison.
         */
        $source = $this->source();

        $debut = (int) strpos($source, 'private function pointEstSoumis');
        $methode = substr($source, $debut, 900);

        self::assertStringContainsString('catch (Throwable $e)', $methode);
        self::assertStringContainsString('return true;', $methode);
    }

    public function test_l_urgent_remonte_en_tete(): void
    {
        // Une liste qui commence par un rappel aimable fait manquer l'alerte
        // qui comptait.
        $source = $this->source();

        self::assertStringContainsString('usort(', $source);
        self::assertStringContainsString('TON_URGENT => 0', $source);
    }

    public function test_une_alerte_se_dit_comme_une_phrase(): void
    {
        $alerte = [
            'titre' => "Votre point de caisse n'est pas encore soumis",
            'detail' => "La journee se termine.",
        ];

        self::assertSame(
            "Mariam, votre point de caisse n'est pas encore soumis. La journee se termine.",
            NoelVeille::enPhrase($alerte, 'Mariam')
        );

        // Sans prénom, la phrase doit rester correcte et garder sa majuscule.
        self::assertStringStartsWith('Votre point', NoelVeille::enPhrase($alerte));
    }

    public function test_chaque_alerte_porte_une_cle_du_jour(): void
    {
        /*
         * La clé évite d'envoyer deux fois la même alerte, comme le fait déjà
         * CronAlertesDirection. Sans elle, relancer la veille toutes les
         * heures noierait la personne sous le même message.
         */
        $source = $this->source();

        foreach (['point-caisse-', 'fonds-a-valider-', 'fonds-a-decaisser-', 'appro-a-valider-'] as $cle) {
            self::assertStringContainsString("'" . $cle, $source);
        }
    }
}
