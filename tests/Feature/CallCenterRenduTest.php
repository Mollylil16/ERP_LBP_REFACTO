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
