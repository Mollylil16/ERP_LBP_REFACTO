<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\CallCenterEcrans;
use Tests\TestCase;

/**
 * Rendu des écrans du Call Center.
 *
 * Deux régressions de la suppression des emoji sont épinglées ici : la note de
 * satisfaction, dont les étoiles avaient disparu, et les liens du module, qui
 * étaient écrits en chemins absolus.
 */
final class CallCenterRenduTest extends TestCase
{
    public function test_le_tableau_de_bord_se_rend_a_vide(): void
    {
        $html = CallCenterEcrans::tableauDeBordPage([], [], [], false);

        self::assertStringContainsString('finea-shell', $html);
        self::assertStringContainsString('Aucun appel', $html);
        self::assertStringContainsString('Aucun litige', $html);
    }

    public function test_les_actions_de_gestion_sont_reservees(): void
    {
        $sans = CallCenterEcrans::tableauDeBordPage([], [], [], false);
        $avec = CallCenterEcrans::tableauDeBordPage([], [], [], true);

        self::assertStringNotContainsString('Enregistrer un appel', $sans);
        self::assertStringContainsString('Enregistrer un appel', $avec);
    }

    /**
     * Les étoiles étaient écrites avec les caractères U+2605 et U+2606, retirés
     * par la purge des emoji : la colonne s'affichait vide pour toutes les notes.
     */
    public function test_la_note_de_satisfaction_est_toujours_visible(): void
    {
        for ($note = 1; $note <= 5; $note++) {
            $html = CallCenterEcrans::etoiles($note);

            self::assertSame(5, substr_count($html, '<svg'), "Note {$note} : cinq étoiles attendues.");
            self::assertSame(
                $note,
                substr_count($html, 'fill="currentColor"'),
                "Note {$note} : autant d'étoiles pleines que de points."
            );
            self::assertStringContainsString('aria-label="' . $note . ' sur 5"', $html);
        }
    }

    public function test_une_note_absente_ne_dessine_pas_d_etoile(): void
    {
        self::assertStringNotContainsString('<svg', CallCenterEcrans::etoiles(null));
        self::assertStringNotContainsString('<svg', CallCenterEcrans::etoiles(0));
    }

    public function test_la_note_hors_bornes_reste_sur_cinq_etoiles(): void
    {
        $html = CallCenterEcrans::etoiles(9);

        self::assertSame(5, substr_count($html, '<svg'));
        self::assertSame(5, substr_count($html, 'fill="currentColor"'));
    }

    /**
     * Les vues d'origine écrivaient href="/call-center/...". Ces chemins
     * pointent à côté dès que l'ERP n'est pas servi à la racine du domaine.
     */
    public function test_aucun_lien_du_module_n_est_ecrit_en_chemin_absolu(): void
    {
        /*
         * À la racine du domaine, View::url renvoie exactement le chemin
         * absolu : les deux écritures sont alors indiscernables. On simule donc
         * une installation en sous-répertoire, le seul cas où l'écart se voit —
         * et c'est précisément celui qui casse.
         */
        $scriptInitial = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/ERP_LBP_REFACTO/index.php';

        try {
            $pages = [
                'tableau de bord' => CallCenterEcrans::tableauDeBordPage([], [], [], true),
                'rayons' => CallCenterEcrans::rayonsPage([], [], [], null, 0, '01/01/2026 à 08:00'),
                'journal des appels' => CallCenterEcrans::journalAppelsPage([], [], '', '', '', true, true),
            ];

            foreach ($pages as $nom => $html) {
                self::assertDoesNotMatchRegularExpression(
                    '#(?:href|action)="/(?:call-center|logistique|colisage|finance)/#',
                    $html,
                    "L'écran « {$nom} » écrit un chemin absolu au lieu de passer par View::url : "
                        . 'le lien pointera à côté sur une installation en sous-répertoire.'
                );
                self::assertStringContainsString(
                    '/ERP_LBP_REFACTO/call-center/',
                    $html,
                    "L'écran « {$nom} » devrait produire au moins un lien préfixé."
                );
            }
        } finally {
            $_SERVER['SCRIPT_NAME'] = $scriptInitial;
        }
    }

    public function test_le_journal_des_appels_affiche_ses_colonnes(): void
    {
        $html = CallCenterEcrans::journalAppelsPage(
            [[
                'id' => 12,
                'client_name' => 'Société Alpha',
                'type_appel' => 'reclamation',
                'numero_tracking' => 'LB-CI-0726-001',
                'agent_name' => 'M. Diarra',
                'satisfaction_score' => 4,
                'statut' => 'a_rappeler',
                'created_at' => '2026-09-10 14:25:00',
            ]],
            [],
            '',
            '',
            '',
            false,
            false
        );

        self::assertStringContainsString('Société Alpha', $html);
        self::assertStringContainsString('Réclamation', $html, 'Le code brut ne doit pas s\'afficher.');
        self::assertStringContainsString('À rappeler', $html);
        self::assertStringContainsString('LB-CI-0726-001', $html);
        self::assertStringContainsString('10/09/2026 14:25', $html);
    }

    public function test_le_formulaire_d_appel_n_apparait_que_pour_qui_peut_gerer(): void
    {
        $sans = CallCenterEcrans::journalAppelsPage([], [], '', '', '', false, false);
        $avec = CallCenterEcrans::journalAppelsPage([], [], '', '', '', true, false);

        self::assertStringNotContainsString('appels/enregistrer', $sans);
        self::assertStringContainsString('appels/enregistrer', $avec);
        self::assertStringContainsString('_csrf_token', $avec);
    }

    // ------------------------------------------------------------------
    // Relances : le defaut de l'apostrophe
    // ------------------------------------------------------------------

    /**
     * Les données du colis passaient par un gestionnaire onclick interpolé.
     * htmlspecialchars encode l'apostrophe en &#039;, que le navigateur décode
     * avant que JavaScript ne lise l'attribut : lancerAppel(1, 2, 'N'DRIN')
     * est une erreur de syntaxe, et le bouton ne faisait plus rien. Les noms à
     * apostrophe sont courants ici (N'Dri, N'Guessan).
     */
    public function test_un_nom_a_apostrophe_ne_casse_pas_les_boutons_de_relance(): void
    {
        $html = CallCenterEcrans::suiviPage(
            [[
                'id' => 7,
                'numero_tracking' => 'LB-CI-0726-001',
                'destinataire_id' => 3,
                'destinataire_nom' => "N'DRIN REGIS",
                'destinataire_tel' => '+2250700000000',
                'statut' => 'ARRIVÉ',
                'type_notification' => null,
            ]],
            '',
            true
        );

        self::assertStringNotContainsString('onclick=', $html, 'Aucune donnée ne doit transiter par un gestionnaire inline.');
        self::assertStringContainsString('data-cc-nom="N&#039;DRIN REGIS"', $html);
        self::assertStringContainsString('data-cc-action="appel"', $html);
    }

    public function test_le_point_d_enregistrement_des_relances_passe_par_view_url(): void
    {
        // Ce fetch était écrit en chemin absolu : la relance n'était jamais
        // enregistrée sur une installation en sous-répertoire.
        $scriptInitial = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/ERP_LBP_REFACTO/index.php';

        try {
            $html = CallCenterEcrans::suiviPage([], '', true);

            self::assertStringContainsString(
                'data-cc-notifier="/ERP_LBP_REFACTO/call-center/suivi/notifier"',
                $html
            );
            self::assertStringNotContainsString('fetch(\'/call-center', $html);
        } finally {
            $_SERVER['SCRIPT_NAME'] = $scriptInitial;
        }
    }

    public function test_les_relances_ne_sont_pas_rendues_sans_habilitation(): void
    {
        $html = CallCenterEcrans::suiviPage(
            [[
                'id' => 7,
                'numero_tracking' => 'LB-1',
                'destinataire_id' => 3,
                'destinataire_nom' => 'Awa',
                'destinataire_tel' => '+225070',
                'statut' => 'ARRIVÉ',
                'type_notification' => null,
            ]],
            '',
            false
        );

        self::assertStringNotContainsString('data-cc-action', $html);
        // « cc-appel » figure aussi dans la feuille de style : c'est le panneau
        // lui-même, identifié, qui ne doit pas exister.
        self::assertStringNotContainsString('id="cc-appel"', $html, 'Le panneau d\'appel ne doit pas être rendu.');
        self::assertStringNotContainsString('data-cc-jeton', $html, 'Aucun formulaire de relance : aucun jeton à exposer.');
    }

    public function test_l_etat_de_relance_resume_ce_qui_a_ete_fait(): void
    {
        $html = CallCenterEcrans::suiviPage(
            [[
                'id' => 7,
                'numero_tracking' => 'LB-1',
                'destinataire_id' => 3,
                'destinataire_nom' => 'Awa',
                'destinataire_tel' => '+225070',
                'statut' => 'ARRIVÉ',
                'type_notification' => 'appel',
                'notification_date' => '2026-09-10 09:15:00',
                'agent_name' => 'M. Diarra',
                'duree_appel' => 95,
                'notification_desc' => 'Client prévenu',
            ]],
            '',
            false
        );

        self::assertStringContainsString('Notifié par appel', $html);
        self::assertStringContainsString('10/09/2026 09:15', $html);
        self::assertStringContainsString('durée 01:35', $html);
        self::assertStringContainsString('Client prévenu', $html);
    }

    // ------------------------------------------------------------------
    // Bilan des departs
    // ------------------------------------------------------------------

    public function test_le_bilan_des_departs_compte_complets_et_partiels(): void
    {
        $html = CallCenterEcrans::suiviDepartsPage(
            [$this->groupe(2, 0), $this->groupe(1, 1)],
            [],
            '',
            null,
            false,
            false
        );

        self::assertStringContainsString('Envois suivis', $html);
        // Un groupe sans reste est complet, l'autre est partiel.
        self::assertStringContainsString('Envois complets', $html);
        self::assertStringContainsString('Resté en agence', $html);
    }

    public function test_le_message_de_synthese_est_compose_cote_serveur(): void
    {
        $html = CallCenterEcrans::suiviDepartsPage([$this->groupe(1, 1)], [], '', null, true, false);

        self::assertStringContainsString('data-cc-message="', $html);
        self::assertStringContainsString('LA BELLE PORTE LOGISTICS', $html);
        self::assertStringContainsString('motif : Manque de place', $html);
        self::assertStringNotContainsString('generateMessage', $html);
        self::assertStringNotContainsString('onclick=', $html);
    }

    public function test_l_export_excel_est_reserve(): void
    {
        $sans = CallCenterEcrans::suiviDepartsPage([], [], '', null, false, false);
        $avec = CallCenterEcrans::suiviDepartsPage([], [], '', null, false, true);

        self::assertStringNotContainsString('export-excel', $sans);
        self::assertStringContainsString('export-excel', $avec);
        self::assertStringContainsString('export-pdf', $sans, 'Le PDF reste ouvert à tous.');
    }

    /**
     * @return array<string, mixed>
     */
    private function groupe(int $partis, int $restes): array
    {
        $colis = [];
        for ($i = 0; $i < $partis; $i++) {
            $colis[] = [
                'colis_id' => 100 + $i,
                'numero_tracking' => 'LB-P' . $i,
                'destinataire_name' => 'Moussa',
                'poids_total' => 12.5,
                'statut' => 'EN_TRANSIT',
                'statut_depart' => 'PARTI',
                'motif_reste' => null,
            ];
        }
        for ($i = 0; $i < $restes; $i++) {
            $colis[] = [
                'colis_id' => 200 + $i,
                'numero_tracking' => 'LB-R' . $i,
                'destinataire_name' => 'Moussa',
                'poids_total' => 8.0,
                'statut' => 'RÉCEPTIONNÉ',
                'statut_depart' => 'RESTE',
                'motif_reste' => 'Manque de place',
            ];
        }

        return [
            'expediteur_id' => 5,
            'expediteur_name' => 'Société Alpha',
            'expediteur_phone' => '+2250700000000',
            'destinataire_name' => 'Moussa',
            'destinataire_phone' => '+2250700000001',
            'type_expediteur' => 'Groupage',
            'trajet' => 'LB-CI',
            'agence_depart' => 'Abidjan',
            'total_colis' => $partis + $restes,
            'nb_partis' => $partis,
            'nb_restes' => $restes,
            'nb_attente' => 0,
            'colis' => $colis,
        ];
    }

    // ------------------------------------------------------------------
    // Litiges
    // ------------------------------------------------------------------

    public function test_le_formulaire_de_resolution_ne_s_ouvre_que_pour_le_litige_demande(): void
    {
        // La version precedente cachait un formulaire par litige ouvert dans la
        // page. Un seul doit desormais etre rendu, celui designe par l'URL.
        $litige = [
            'id' => 42,
            'client_name' => 'Société Alpha',
            'type_litige' => 'retard',
            'gravite' => 'elevee',
            'statut' => 'nouveau',
            'description' => 'Colis annoncé depuis dix jours',
            'date_ouverture' => '2026-09-01',
            'numero_tracking' => 'LB-1',
        ];

        $sans = CallCenterEcrans::litigesPage([$litige], [], [], '', '', true, false, null);
        $avec = CallCenterEcrans::litigesPage([$litige], [], [], '', '', true, false, $litige);

        self::assertStringNotContainsString('/42/resoudre', $sans);
        self::assertSame(1, substr_count($avec, '/42/resoudre'));
        self::assertStringContainsString('Traiter le litige #42', $avec);
        self::assertStringContainsString('Colis annoncé depuis dix jours', $avec);
    }

    public function test_un_litige_clos_ne_propose_plus_de_traitement(): void
    {
        $html = CallCenterEcrans::litigesPage(
            [[
                'id' => 9,
                'client_name' => 'Beta',
                'type_litige' => 'autre',
                'gravite' => 'faible',
                'statut' => 'resolu',
                'date_ouverture' => '2026-08-01',
                'date_resolution' => '2026-08-05',
                'numero_tracking' => null,
            ]],
            [],
            [],
            '',
            '',
            true,
            false,
            null
        );

        self::assertStringNotContainsString('traiter=9', $html);
        self::assertStringContainsString('Clos le 05/08/2026', $html);
    }

    public function test_la_vue_rayons_explique_l_absence_de_rayon(): void
    {
        $html = CallCenterEcrans::rayonsPage([], [], [], null, 0, '01/01/2026 à 08:00');

        self::assertStringContainsString('Aucun rayon configuré', $html);
        self::assertStringContainsString('Configurer les rayons', $html);
    }

    public function test_un_rayon_en_maintenance_n_est_pas_juge_sur_son_remplissage(): void
    {
        $html = CallCenterEcrans::rayonsPage(
            [],
            [[
                'id' => 1,
                'code_rayon' => 'R1',
                'nom_rayon' => 'Rayon 1',
                'agence_nom' => 'Abidjan',
                'capacite_occupee' => 48,
                'capacite_max' => 50,
                'statut' => 'MAINTENANCE',
            ]],
            [],
            null,
            0,
            '01/01/2026 à 08:00'
        );

        self::assertStringContainsString('Maintenance', $html);
        self::assertStringNotContainsString('Plein', $html);
    }

    public function test_les_colis_hors_delai_sont_signales(): void
    {
        $html = CallCenterEcrans::rayonsPage(
            [],
            [[
                'id' => 1,
                'code_rayon' => 'R1',
                'nom_rayon' => 'Rayon 1',
                'agence_nom' => 'Abidjan',
                'capacite_occupee' => 2,
                'capacite_max' => 50,
                'statut' => 'ACTIF',
            ]],
            [1 => [
                ['numero_tracking' => 'LB-1', 'destinataire_nom' => 'Awa', 'destinataire_phone' => '0700', 'jours_retard' => 4, 'date_limite_retrait' => '2026-09-01'],
                ['numero_tracking' => 'LB-2', 'destinataire_nom' => 'Moussa', 'destinataire_phone' => null, 'jours_retard' => 0, 'date_limite_retrait' => '2026-10-01'],
            ]],
            null,
            1,
            '01/01/2026 à 08:00'
        );

        self::assertStringContainsString('1 colis hors délai', $html);
        self::assertStringContainsString('+4 j', $html);
        self::assertStringContainsString('Limite : 01/10', $html);
    }
}
