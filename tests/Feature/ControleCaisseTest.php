<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\ControleCaisseAcces;
use App\Services\Finance\ControleCaisseService;
use App\View\Components\ControleCaisse;
use Tests\TestCase;

/**
 * L'écran de contrôle des caisses : celui que la direction inspecte.
 *
 * Il répond à une phrase des agences, répétée pendant des semaines : « quand
 * je compte ma caisse le soir, ce n'est pas ce que le logiciel affiche ». Le
 * relevé du 24/09/2026 a donné les trois causes, et l'écran doit les montrer
 * toutes les trois — en taire une reviendrait à laisser croire que le reste
 * est expliqué.
 */
final class ControleCaisseTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Finance/ControleCaisseService.php');
    }

    /** @return array<string, mixed> */
    private function donnees(): array
    {
        $caisses = [
            [
                'agence_id' => 1, 'agence' => 'Abobo Dokui',
                'encaisse' => 200000.0, 'especes' => 150000.0, 'operations' => 3,
                'dernier_encaissement' => '2026-09-22 18:40:00',
                'theorique' => 150000.0, 'compte' => 148000.0, 'ecart' => -2000.0,
                'explication' => 'monnaie rendue à un client',
                'date_soumission' => '2026-09-22 19:05:00',
                'etat' => 'SOUMIS', 'compte_sans_comptage' => false,
                'apres_soumission' => 0.0, 'operations_apres' => 0,
            ],
            [
                'agence_id' => 2, 'agence' => 'Adjamé Latin',
                'encaisse' => 180000.0, 'especes' => 180000.0, 'operations' => 4,
                'dernier_encaissement' => '2026-09-22 18:50:00',
                'theorique' => 120000.0, 'compte' => 120000.0, 'ecart' => 0.0,
                'explication' => '',
                'date_soumission' => '2026-09-22 15:00:00',
                'etat' => 'SOUMIS', 'compte_sans_comptage' => false,
                'apres_soumission' => 60000.0, 'operations_apres' => 2,
            ],
            [
                'agence_id' => 3, 'agence' => 'Aéroport',
                'encaisse' => 120000.0, 'especes' => 120000.0, 'operations' => 2,
                'dernier_encaissement' => '2026-09-22 17:00:00',
                'theorique' => null, 'compte' => null, 'ecart' => null,
                'explication' => '',
                'date_soumission' => null,
                'etat' => 'AUCUN_POINT', 'compte_sans_comptage' => false,
                'apres_soumission' => 0.0, 'operations_apres' => 0,
            ],
        ];

        $service = new ControleCaisseService($this->createStub(\PDO::class));

        return [
            'filtres' => ['jour' => '2026-09-22', 'fenetre' => 30, 'agence_id' => 0, 'depuis' => '2026-08-24'],
            'agences' => [['id' => 1, 'name' => 'Abobo Dokui'], ['id' => 3, 'name' => 'Aéroport']],
            'caisses' => $caisses,
            'totaux' => $service->totaux($caisses),
            'sansPoint' => [[
                'jour' => '2026-09-19', 'agence_id' => 3, 'agence' => 'Aéroport',
                'total' => 181900.0, 'nb' => 1, 'etat' => 'AUCUN_POINT', 'ouvert_le' => null,
            ]],
            'apresSoumission' => [[
                'date_jour' => '2026-09-22', 'agence_id' => 2, 'agence' => 'Adjamé Latin',
                'date_soumission' => '2026-09-22 15:00:00', 'solde_soumis' => 120000.0,
                'nb_apres' => 2, 'montant_apres' => 60000.0, 'dernier_encaissement' => '2026-09-22 18:50:00',
            ]],
            'ecarts' => [[
                'date_jour' => '2026-09-22', 'agence_id' => 1, 'agence' => 'Abobo Dokui',
                'theorique' => 150000.0, 'compte' => 148000.0, 'ecart' => -2000.0,
                'explication_ecart' => 'monnaie rendue à un client', 'statut' => 'soumis',
            ]],
        ];
    }

    // ------------------------------------------------------------------
    // Qui regarde
    // ------------------------------------------------------------------

    /**
     * L'écran dit ce que la direction contrôle et quand. Une agence qui le
     * verrait saurait exactement quand on regarde : il lui est fermé, au
     * comptable aussi.
     */
    public function test_l_ecran_est_reserve_a_la_direction(): void
    {
        self::assertSame(
            ['dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance'],
            ControleCaisseAcces::ROLES_LECTURE
        );

        foreach (['agent_saisie', 'caissiere', 'gestionnaire_caisse', 'chef_agence', 'comptable'] as $role) {
            self::assertNotContains($role, ControleCaisseAcces::ROLES_LECTURE);
        }
    }

    /**
     * Aucune route d'écriture : l'écran constate, il ne corrige pas. Une
     * correction se fait par le point de caisse, sous le nom de l'agence.
     */
    public function test_l_ecran_ne_modifie_rien(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');

        preg_match_all('/\$router->(get|post)\(\'(\/controle-caisse[^\']*)\'/', $routes, $trouves, PREG_SET_ORDER);

        self::assertCount(2, $trouves, "L'écran a exactement deux routes : la page et sa version imprimable.");
        foreach ($trouves as $route) {
            self::assertSame('get', $route[1], 'Aucune route d\'écriture sur le contrôle des caisses.');
        }

        $controleur = (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/ControleCaisseController.php');
        self::assertStringNotContainsString('$_POST', $controleur);
    }

    /** La tuile ne s'affiche que pour qui peut ouvrir l'écran. */
    public function test_la_tuile_suit_l_habilitation(): void
    {
        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');

        self::assertStringContainsString(
            "'available' => \\App\\Security\\ControleCaisseAcces::peutOuvrir()",
            $navigation
        );
    }

    // ------------------------------------------------------------------
    // Ce que l'écran compte
    // ------------------------------------------------------------------

    /**
     * L'argent appartient à l'agence de celui qui l'a encaissé, jamais à celle
     * de la facture : le billet est dans le tiroir où il a été reçu. C'est la
     * cause du « les points de caisse sont mélangés ».
     */
    public function test_l_argent_suit_le_tiroir_pas_la_facture(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            "COALESCE(p.agence_id, u.agence_id, f.agence_id)",
            $source,
            "L'agence de l'argent est celle du collecteur."
        );
        self::assertStringNotContainsString('WHERE f.agence_id =', $source);
    }

    /** L'attendu en caisse ne retient que les espèces : le reste ne passe pas par le tiroir. */
    public function test_seules_les_especes_sont_dans_le_tiroir(): void
    {
        self::assertStringContainsString("IN ('especes', 'espece', 'cash')", $this->source());
    }

    /**
     * Une journée n'est comptée que si son point est soumis. Un brouillon
     * laissé en plan ne compte pas — c'est précisément le cas qui a laissé
     * 1 316 270 FCFA sans comptage.
     */
    public function test_un_brouillon_ne_vaut_pas_un_comptage(): void
    {
        $source = $this->source();

        self::assertStringContainsString("e.statut IN ('soumis', 'consolide')", $source);
        self::assertStringContainsString("'BROUILLON'", $source);
        self::assertStringContainsString("'AUCUN_POINT'", $source);
    }

    /** L'argent d'une caisse non soumise est de l'argent que personne n'a compté. */
    public function test_le_total_non_compte_ne_retient_que_les_caisses_non_soumises(): void
    {
        $totaux = $this->donnees()['totaux'];

        self::assertSame(500000.0, $totaux['encaisse']);
        self::assertSame(450000.0, $totaux['especes']);
        self::assertSame(2, $totaux['soumises']);
        self::assertSame(120000.0, $totaux['non_comptes'], "Seule l'agence sans point n'a pas été comptée.");
        self::assertSame(60000.0, $totaux['apres_soumission']);
        self::assertSame(-2000.0, $totaux['ecart']);
    }

    /**
     * Le filtre ne se laisse pas dicter n'importe quoi : une date inventée
     * ramène au jour même, une fenêtre inventée à trente jours.
     */
    public function test_les_filtres_refusent_ce_qui_n_est_pas_une_date(): void
    {
        $service = new ControleCaisseService($this->createStub(\PDO::class));

        $filtres = $service->filtres(['jour' => '1 OR 1=1', 'fenetre' => '365', 'agence_id' => '-4']);

        self::assertSame(date('Y-m-d'), $filtres['jour']);
        self::assertSame(30, $filtres['fenetre']);
        self::assertSame(0, $filtres['agence_id']);

        $retenus = $service->filtres(['jour' => '2026-09-22', 'fenetre' => '7', 'agence_id' => '3404']);
        self::assertSame(['jour' => '2026-09-22', 'fenetre' => 7, 'agence_id' => 3404], $retenus);
    }

    // ------------------------------------------------------------------
    // Ce que l'écran montre
    // ------------------------------------------------------------------

    /**
     * Les trois causes relevées en production apparaissent chacune sous son
     * titre. En taire une laisserait croire que le reste est expliqué.
     */
    public function test_les_trois_causes_sont_a_l_ecran(): void
    {
        $html = ControleCaisse::page($this->donnees());

        self::assertStringContainsString('Les caisses du 22/09/2026', $html);
        self::assertStringContainsString('Journées jamais comptées', $html);
        self::assertStringContainsString('Points signés avant la fin de la journée', $html);
        self::assertStringContainsString('Écarts déclarés au comptage', $html);
    }

    /**
     * Une caisse jamais comptée se lit sans effort : l'état, le montant, et la
     * phrase qui dit ce que ça veut dire.
     */
    public function test_une_caisse_non_comptee_se_voit(): void
    {
        $html = ControleCaisse::page($this->donnees());

        self::assertStringContainsString('Aucun point', $html);
        // L'apostrophe passe par View::e : elle sort échappée dans la page.
        self::assertStringContainsString('120 000 F n&#039;ont été comptés par personne.', $html);
        self::assertStringContainsString('is-alerte', $html);
    }

    /** Le point signé trop tôt est dit avec son montant et son nombre d'opérations. */
    public function test_l_encaisse_apres_signature_est_dit_en_clair(): void
    {
        $html = ControleCaisse::page($this->donnees());

        self::assertStringContainsString('60 000 F encaissés après la signature', $html);
        self::assertStringContainsString('Signé à 15:00', $html);
    }

    /** L'explication de l'agence est reprise telle quelle, à côté de son écart. */
    public function test_l_explication_de_l_agence_accompagne_son_ecart(): void
    {
        $html = ControleCaisse::page($this->donnees());

        self::assertStringContainsString('monnaie rendue', $html);
        self::assertStringContainsString('-2 000', $html);
    }

    /**
     * Un tableau plus large que l'écran doit défiler dans son cadre, sinon la
     * page entière part de travers sur un téléphone.
     */
    public function test_les_tableaux_defilent_sur_un_petit_ecran(): void
    {
        $html = ControleCaisse::page($this->donnees());

        self::assertSame(
            substr_count($html, '<table'),
            substr_count($html, 'lbp-controle-table-enveloppe"><table'),
            'Chaque tableau est dans son cadre qui défile.'
        );
        self::assertStringContainsString('.lbp-controle-table-enveloppe{overflow-x:auto}', $html);
    }

    /** La version imprimable porte les mêmes chiffres, pour la réunion de direction. */
    public function test_la_version_imprimable_reprend_les_memes_chiffres(): void
    {
        $pdf = ControleCaisse::exportPdf($this->donnees() + ['edite_par' => 'Direction']);

        self::assertStringContainsString('Contrôle des caisses', $pdf);
        self::assertStringContainsString('500 000', $pdf);
        self::assertStringContainsString('120 000', $pdf);
        self::assertStringContainsString('Journées jamais comptées', $pdf);
        self::assertStringContainsString('@page{size:A4 landscape', $pdf);
    }
}
