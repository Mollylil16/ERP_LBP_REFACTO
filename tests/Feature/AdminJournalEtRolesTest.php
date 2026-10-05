<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\AdminComptesJournal as Journal;
use App\View\Components\AdminRoles as Roles;
use Tests\TestCase;

/**
 * Le module Administration apprend à répondre à deux questions.
 *
 * « Qui a changé ça ? » — aucun geste d'administration ne laissait de trace
 * lisible. La purge du 05/08/2026 et les rôles du personnel réécrits le
 * 30/09/2026 n'ont jamais trouvé de coupable, faute de journal.
 *
 * « Qui porte quel rôle ? » — il fallait ouvrir les quarante fiches une à une.
 * Personne ne le faisait, et l'anomalie du 30/09 n'a été vue qu'aux plaintes.
 */
final class AdminJournalEtRolesTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function gestes(): array
    {
        return [
            [
                'id' => 9, 'action' => 'set_roles', 'action_libelle' => 'Rôles changés',
                'entity_id' => 31, 'user_id' => 1,
                'cible_nom' => 'KOUAKOU SALES', 'cible_email' => 'sales@labelleporte.ci',
                'acteur_nom' => 'BRUNELL OMEPIEU',
                'avant_texte' => 'rôles : caissiere, agent_saisie',
                'apres_texte' => 'rôles : agent_saisie',
                'ip_address' => '160.155.240.145', 'created_at' => '2026-10-05 16:12:00',
            ],
            [
                'id' => 8, 'action' => 'reset_password', 'action_libelle' => 'Mot de passe réinitialisé',
                'entity_id' => 22, 'user_id' => 1,
                'cible_nom' => 'Mme AGBADAN', 'cible_email' => 'carine.abou@labelleporte.ci',
                'acteur_nom' => 'BRUNELL OMEPIEU',
                'avant_texte' => '', 'apres_texte' => 'par : reinitialisation administrateur',
                'ip_address' => '160.155.240.145', 'created_at' => '2026-10-05 14:40:00',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function filtres(): array
    {
        return ['du' => '2026-10-01', 'au' => '2026-10-05', 'action' => '', 'cible_id' => '', 'acteur_id' => '', 'q' => ''];
    }

    public function test_le_journal_montre_l_avant_et_l_apres(): void
    {
        /*
         * Savoir qu'un rôle a changé ne sert à rien. Savoir qu'il est passé de
         * « caissiere, agent_saisie » à « agent_saisie » permet de rétablir.
         */
        $html = Journal::page($this->gestes(), $this->filtres(), [], []);

        self::assertStringContainsString('caissiere, agent_saisie', $html);
        self::assertStringContainsString('rôles : agent_saisie', $html);
        self::assertStringContainsString('>Avant</th>', $html);
        self::assertStringContainsString('>Après</th>', $html);
    }

    public function test_le_journal_dit_qui_quand_et_d_ou(): void
    {
        $html = Journal::page($this->gestes(), $this->filtres(), [], []);

        self::assertStringContainsString('BRUNELL OMEPIEU', $html);
        self::assertStringContainsString('05/10/2026 à 16:12', $html);
        self::assertStringContainsString('160.155.240.145', $html);
    }

    public function test_le_mot_de_passe_lui_meme_n_est_jamais_inscrit(): void
    {
        // Un journal qui porterait le secret serait une porte de plus.
        $service = (string) file_get_contents(BASE_PATH . '/app/Services/Admin/AdminService.php');

        $extrait = substr($service, (int) strpos($service, 'public function resetPassword'), 600);

        self::assertStringContainsString("AuditLogService::log('reset_password'", $extrait);
        self::assertStringNotContainsString('$defaultHash]', $extrait);
        self::assertStringNotContainsString("'password'", $extrait);
    }

    public function test_les_deux_exports_suivent_le_filtre(): void
    {
        $html = Journal::page($this->gestes(), $this->filtres(), [], []);

        self::assertStringContainsString('admin/journal/pdf', $html);
        self::assertStringContainsString('admin/journal/excel', $html);
        self::assertStringContainsString('du=2026-10-01', $html);
    }

    public function test_le_journal_ecrit_dans_la_chaine_existante(): void
    {
        /*
         * lbp_audit_logs est chaînée en SHA-256 et vérifiée par
         * VerifyAuditIntegrity. Une seconde table aurait coupé l'audit en deux,
         * et seule l'une des moitiés aurait été contrôlée.
         */
        $depot = (string) file_get_contents(BASE_PATH . '/app/Repositories/Admin/ComptesAuditRepository.php');
        $service = (string) file_get_contents(BASE_PATH . '/app/Services/Admin/AdminService.php');

        self::assertStringContainsString('lbp_audit_logs', $depot);
        self::assertStringNotContainsString('CREATE TABLE', $depot);
        self::assertGreaterThanOrEqual(5, substr_count($service, 'AuditLogService::log('));
    }

    public function test_l_ecran_des_roles_nomme_les_anomalies(): void
    {
        $roles = [
            ['code' => 'comptable', 'libelle' => 'Comptable', 'hors_catalogue' => false,
             'porteurs' => [['id' => 9, 'nom' => 'Mme KOFFI', 'statut' => 'active']]],
            ['code' => 'responsable_groupage', 'libelle' => 'Responsable Groupage', 'hors_catalogue' => false,
             'porteurs' => []],
            ['code' => 'agent_ancien', 'libelle' => 'agent_ancien', 'hors_catalogue' => true,
             'porteurs' => [['id' => 35, 'nom' => 'DIABATE Moussa', 'statut' => 'active']]],
        ];

        $html = Roles::page(
            $roles,
            [['id' => 36, 'nom' => 'YAO Bernard', 'email' => 'yao@labelleporte.ci']],
            [['id' => 34, 'nom' => 'TRAORE Ali', 'email' => 'ali@labelleporte.ci', 'last_login_at' => null]],
            [['id' => 1, 'nom' => 'BRUNELL OMEPIEU', 'email' => 'b@labelleporte.ci', 'last_login_at' => null]]
        );

        self::assertStringContainsString('Comptes sans aucun rôle', $html);
        self::assertStringContainsString('YAO Bernard', $html);
        self::assertStringContainsString('Hors catalogue', $html);
        self::assertStringContainsString('agent_ancien', $html);
        self::assertStringContainsString('Comptes dormants', $html);
        self::assertStringContainsString('Non attribué', $html);
    }

    public function test_un_compte_inactif_se_distingue_dans_la_liste_des_porteurs(): void
    {
        // Un rôle porté par trois personnes dont deux parties n'est pas
        // vraiment porté par trois personnes.
        $roles = [[
            'code' => 'agent_saisie', 'libelle' => 'Agent de Saisie', 'hors_catalogue' => false,
            'porteurs' => [
                ['id' => 31, 'nom' => 'KOUAKOU SALES', 'statut' => 'active'],
                ['id' => 34, 'nom' => 'TRAORE Ali', 'statut' => 'inactive'],
            ],
        ]];

        $html = Roles::page($roles, [], [], []);

        self::assertStringContainsString('is-inactif', $html);
        self::assertStringContainsString('Compte inactif', $html);
    }

    public function test_le_tableau_de_bord_demande_une_decision(): void
    {
        $vue = (string) file_get_contents(BASE_PATH . '/views/admin/dashboard.php');

        self::assertStringContainsString('Admin::fileDAttente', $vue);
        // Trois compteurs qui ne faisaient agir personne ont cédé la place.
        self::assertStringNotContainsString("'label' => 'Utilisateurs'", $vue);
        self::assertStringNotContainsString("'label' => 'Accès restreints'", $vue);
    }

    public function test_la_derniere_connexion_est_enregistree_et_affichee(): void
    {
        $connexion = (string) file_get_contents(BASE_PATH . '/app/Controllers/Auth/AuthController.php');
        $composant = (string) file_get_contents(BASE_PATH . '/app/View/Components/Admin.php');

        self::assertStringContainsString('last_login_at = NOW()', $connexion);
        self::assertStringContainsString('>Dernière connexion</th>', $composant);
        // « Jamais » se dit en toutes lettres : un tiret laisserait croire à
        // une donnée manquante, alors que c'est l'information elle-même.
        self::assertStringContainsString('Jamais', $composant);
    }

    public function test_les_deux_ecrans_sont_reserves_a_l_administrateur(): void
    {
        $controleur = (string) file_get_contents(BASE_PATH . '/app/Controllers/Admin/AdminJournalController.php');

        self::assertSame(4, substr_count($controleur, 'AdminMiddleware::check()'));
    }
}
