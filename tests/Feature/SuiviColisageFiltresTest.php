<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\Logistique;
use Tests\TestCase;

/**
 * Suivi colisage : séparer les catégories, retrouver un colis, joindre la facture.
 *
 * L'écran mélangeait LB-CI, CA-CI et tout le reste sans moyen de les séparer,
 * les états imprimés ne portaient que le nom de l'expéditeur — jamais son
 * numéro — et retrouver la facture d'un client obligeait à noter le code du
 * colis, quitter le module et chercher à nouveau dans Finance.
 */
final class SuiviColisageFiltresTest extends TestCase
{
    private function controleur(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Logistique/LogistiqueColisageController.php'
        );
    }

    /** @return array<int, array{code: string, libelle: string}> */
    private function trajets(): array
    {
        return [
            ['code' => 'LB-CI', 'libelle' => 'Abidjan → France'],
            ['code' => 'CA-CI', 'libelle' => 'Abidjan → Paris'],
            ['code' => 'CA-CC', 'libelle' => 'Canada → Abidjan'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function colis(): array
    {
        return [[
            'id' => 1013,
            'numero_tracking' => 'LB-CI-924',
            'created_at' => '2026-10-15 07:56:31',
            'expediteur_name' => 'MARIE',
            'expediteur_phone' => '+33616674128',
            'destinataire_name' => 'MARIE',
            'destinataire_phone' => '+33616674128',
            'nombre_colis' => 9,
            'poids_total' => 134.0,
            'marchandises' => 'DRAPS',
            'col_trajet' => 'CIV_FR',
            'type_expediteur' => 'export_aerien',
            'trajet_code' => 'LB-CI',
            'trajet_libelle' => 'Abidjan → France',
            'agence_depart_name' => 'Abobo Dokui',
            'agence_arrivee_name' => 'Paris',
            'agent_name' => 'KOUAME Yvette',
            'statut' => 'enregistre',
            'facture_id' => 4033,
            'facture_numero' => 'FA-3403-2026-000302',
        ]];
    }

    private function page(array $filtres, bool $peutVoirFacture = true): string
    {
        return Logistique::colisageSuiviPage(
            [['id' => 3403, 'name' => 'Abobo Dokui', 'code' => 'DOK']],
            'today',
            '2026-10-15',
            '2026-10-15',
            3403,
            $this->colis(),
            ['totalSaisies' => 1, 'totalNombreColis' => 9, 'totalPoids' => 134.0],
            $this->trajets(),
            $filtres,
            $peutVoirFacture
        );
    }

    public function test_le_catalogue_des_codes_garde_ceux_qui_sont_a_zero(): void
    {
        /*
         * Neuf codes sur treize n'avaient encore servi à rien le 15/10/2026.
         * Les retirer de la liste ferait croire qu'ils n'existent pas le jour
         * où une agence commencera à les utiliser : demande expresse de
         * l'administrateur.
         */
        $source = $this->controleur();

        self::assertStringContainsString('FROM trajets', $source);
        self::assertStringNotContainsString('JOIN lbp_colis', $source);

        // La liste n'est donc pas tirée des colis existants.
        $catalogue = substr($source, (int) strpos($source, 'function catalogueTrajets'), 400);
        self::assertStringNotContainsString('lbp_colis', $catalogue);
    }

    public function test_la_categorie_se_choisit_a_l_ecran(): void
    {
        $page = $this->page(['trajet' => '', 'q' => '']);

        self::assertStringContainsString('Catégorie de code', $page);
        self::assertStringContainsString('-- Toutes les catégories --', $page);
        self::assertStringContainsString('CA-CC', $page, 'Un code sans colis doit rester proposable.');
    }

    public function test_la_categorie_suit_dans_les_exports(): void
    {
        /*
         * Les exports n'emportaient que l'agence et les dates : on tirait un
         * état complet en croyant tirer le LB-CI qu'on avait sous les yeux.
         */
        $page = $this->page(['trajet' => 'LB-CI', 'q' => '']);

        self::assertStringContainsString('trajet_code=LB-CI', $page);
        self::assertStringContainsString('export-pdf?', $page);
        self::assertStringContainsString('export-excel?', $page);
    }

    public function test_une_recherche_ignore_la_periode(): void
    {
        /*
         * On cherche un code de colis parce qu'on ignore justement sa date.
         * Garder le filtre de dates rendrait la recherche inutilisable.
         */
        $source = $this->controleur();
        $debut = (int) strpos($source, 'function fetchColisData');
        $corps = substr($source, $debut);

        self::assertStringContainsString("if (\$recherche === '') {", $corps);
        self::assertStringContainsString('AND DATE(c.created_at) BETWEEN :date_start AND :date_end', $corps);

        // Et l'écran doit le dire, sinon le total affiché paraît faux.
        $page = $this->page(['trajet' => '', 'q' => 'LB-CI-924']);
        self::assertStringContainsString('toutes dates', $page);
    }

    public function test_la_recherche_n_utilise_jamais_deux_fois_le_meme_parametre(): void
    {
        /*
         * PDO n'émule pas les requêtes préparées ici : un paramètre nommé
         * réutilisé resterait vide à sa seconde occurrence, et la recherche
         * ne trouverait rien sans rien signaler.
         */
        $source = $this->controleur();

        foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $nom) {
            self::assertSame(
                1,
                substr_count($source, ':' . $nom),
                "Le paramètre :{$nom} doit apparaître une seule fois dans la requête."
            );
        }
    }

    public function test_le_numero_de_l_expediteur_est_partout(): void
    {
        $source = $this->controleur();
        self::assertStringContainsString('exp.phone AS expediteur_phone', $source);

        foreach (['colisage_pdf.php', 'colisage_excel.php'] as $vue) {
            $contenu = (string) file_get_contents(BASE_PATH . '/views/logistique/' . $vue);
            self::assertStringContainsString("\$p['expediteur_phone']", $contenu, $vue);
        }

        self::assertStringContainsString('+33616674128', $this->page(['trajet' => '', 'q' => '']));
    }

    public function test_la_facture_est_atteignable_d_un_clic(): void
    {
        $avec = $this->page(['trajet' => '', 'q' => ''], true);
        $sans = $this->page(['trajet' => '', 'q' => ''], false);

        self::assertStringContainsString('FA-3403-2026-000302', $avec);
        self::assertStringContainsString('finance/factures/4033', $avec);

        // Ce module masque les montants : inutile d'ouvrir une porte à qui
        // recevra « Accès refusé » de l'autre côté.
        self::assertStringNotContainsString('FA-3403-2026-000302', $sans);
        self::assertStringNotContainsString('<th>Facture</th>', $sans);
    }

    public function test_la_facture_passe_par_une_sous_requete(): void
    {
        /*
         * Un colis n'en porte qu'une — vérifié sur les 1 009 colis de
         * production — mais une jointure qui doublerait un jour fausserait le
         * nombre de colis et le poids total du pied de tableau.
         */
        $source = $this->controleur();

        self::assertStringContainsString('AS facture_id', $source);
        self::assertStringNotContainsString('JOIN lbp_factures', $source);
    }

    public function test_un_colis_sans_facture_se_dit_en_toutes_lettres(): void
    {
        $composant = (string) file_get_contents(BASE_PATH . '/app/View/Components/Logistique.php');

        // « Non facturé » et non un tiret : c'est une facture qui manque, et
        // c'est une information en soi pour l'agence.
        self::assertStringContainsString('Non facturé', $composant);
    }
}
