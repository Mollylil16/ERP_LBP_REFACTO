<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\ApproCaisseAcces;
use App\Services\Finance\ApproCaisseService;
use App\View\Components\ApproCaisse;
use Tests\TestCase;

/**
 * Appro caisse : l'argent que le siège remet à une agence.
 *
 * La Gestion des Fonds ne connaissait que les décaissements — l'argent qui
 * sort. Rien ne disait ce qui entrait dans un tiroir, si bien qu'une agence
 * approvisionnée comptait le soir plus que le logiciel n'attendait, et
 * s'entendait reprocher un écart qu'elle n'avait pas fait.
 *
 * Décidé par la direction le 30/09/2026 : la caissière principale saisit, le
 * comptable valide, et l'appro validé entre dans l'attendu du point de caisse
 * au jour de la remise.
 */
final class ApproCaisseTest extends TestCase
{
    /** @return array<string, mixed> */
    private function donnees(bool $peutSaisir = true, bool $peutValider = false): array
    {
        $lignes = [
            [
                'id' => 1, 'numero' => 'APP-202609-001', 'agence_id' => 3400, 'agence' => 'Abobo Dokui',
                'montant' => 250000.0, 'devise' => 'XOF', 'source' => 'SIEGE',
                'motif' => 'Fonds de caisse de la semaine', 'date_effet' => '2026-09-30',
                'statut' => 'validee', 'demandeur' => 'OUEDRAOGO KADIDIATOU', 'validateur' => 'Comptable LBP',
                'motif_rejet' => null,
            ],
            [
                'id' => 2, 'numero' => 'APP-202609-002', 'agence_id' => 3402, 'agence' => 'Aéroport',
                'montant' => 90000.0, 'devise' => 'XOF', 'source' => 'BANQUE',
                'motif' => 'Appoint', 'date_effet' => '2026-09-30',
                'statut' => 'en_attente', 'demandeur' => 'OUEDRAOGO KADIDIATOU', 'validateur' => null,
                'motif_rejet' => null,
            ],
        ];

        $service = new ApproCaisseService($this->createStub(\PDO::class));

        return [
            'filtres' => ['du' => '2026-09-01', 'au' => '2026-09-30', 'agence_id' => 0, 'statut' => ''],
            'lignes' => $lignes,
            'totaux' => $service->totaux($lignes),
            'agences' => [['id' => 3400, 'name' => 'Abobo Dokui'], ['id' => 3402, 'name' => 'Aéroport']],
            'peutSaisir' => $peutSaisir,
            'peutValider' => $peutValider,
        ];
    }

    // ------------------------------------------------------------------
    // Deux mains, deux rôles
    // ------------------------------------------------------------------

    /**
     * Celui qui remet l'argent n'est pas celui qui l'inscrit aux comptes : la
     * caissière principale saisit, le comptable valide.
     */
    public function test_la_saisie_et_la_validation_ne_sont_pas_la_meme_main(): void
    {
        self::assertSame(['caissiere_principale'], ApproCaisseAcces::ROLES_SAISIE);
        self::assertSame(['comptable'], ApproCaisseAcces::ROLES_VALIDATION);

        self::assertNotContains('comptable', ApproCaisseAcces::ROLES_SAISIE);
        self::assertNotContains('caissiere_principale', ApproCaisseAcces::ROLES_VALIDATION);
    }

    /**
     * L'agence doit voir ce qu'elle a reçu : depuis qu'un appro validé entre
     * dans l'attendu de son point de caisse, le lui cacher reviendrait à lui
     * réclamer le soir un argent dont elle ignore l'origine.
     */
    public function test_l_agence_voit_ce_qu_elle_a_recu(): void
    {
        foreach (['chef_agence', 'caissiere', 'agent_saisie', 'gestionnaire_caisse'] as $role) {
            self::assertContains($role, ApproCaisseAcces::ROLES_LECTURE, $role);
        }
    }

    // ------------------------------------------------------------------
    // Ce que l'appro change en caisse
    // ------------------------------------------------------------------

    /**
     * L'attendu du point de caisse additionne les espèces encaissées et l'appro
     * validé du jour. Sans cela, l'agence compte plus que le logiciel.
     */
    public function test_l_appro_valide_entre_dans_l_attendu_du_point_de_caisse(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/EtatJournalierRepository.php');

        self::assertStringContainsString("'solde_caisse_agence_xof' => \$encaisseEspecesXof + \$approCaisseXof", $source);
        self::assertStringContainsString('ApproCaisseService::montantValide(', $source);
    }

    /** Seul un appro validé compte : un appro annoncé n'est pas un appro remis. */
    public function test_seul_un_appro_valide_compte(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Finance/ApproCaisseService.php');

        self::assertStringContainsString("statut = 'validee'", $source);
        self::assertStringContainsString('date_effet = :jour', $source);
    }

    /**
     * La table peut manquer sur une base que les migrations n'ont pas touchée :
     * un point de caisse ne doit pas tomber pour autant.
     */
    public function test_un_point_de_caisse_ne_tombe_pas_si_la_table_manque(): void
    {
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('table absente'));

        self::assertSame(0.0, ApproCaisseService::montantValide($pdo, 3400, '2026-09-30'));
    }

    /**
     * Paris compte en euros : un appro saisi en francs y serait attendu dans
     * la mauvaise caisse, et le comptage du soir ne tomberait jamais juste.
     */
    public function test_la_caisse_de_paris_compte_en_euros(): void
    {
        self::assertSame(['XOF' => 'FCFA', 'EUR' => 'EUR'], ApproCaisseService::DEVISES);

        $source = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/EtatJournalierRepository.php');
        self::assertStringContainsString("'solde_caisse_agence_eur' => \$encaisseEspecesEur + \$approCaisseEur", $source);
        self::assertStringContainsString("montantValide(\$this->pdo, \$agenceId, \$date, 'EUR')", $source);

        // Le formulaire laisse choisir la monnaie, il ne la devine pas.
        $html = ApproCaisse::page($this->donnees(true));
        self::assertStringContainsString('name="devise"', $html);
    }

    // ------------------------------------------------------------------
    // Les totaux et l'écran
    // ------------------------------------------------------------------

    /** Ce qui est validé, ce qui attend, ce qui est rejeté : trois cumuls distincts. */
    public function test_les_cumuls_separent_le_valide_de_l_attente(): void
    {
        $totaux = $this->donnees()['totaux'];

        self::assertSame(250000.0, $totaux['valide']);
        self::assertSame(90000.0, $totaux['en_attente']);
        self::assertSame(0.0, $totaux['rejete']);
        self::assertSame(1, $totaux['a_valider']);
    }

    /** Le formulaire de saisie n'apparaît qu'à qui a le droit de saisir. */
    public function test_le_formulaire_est_reserve_a_la_caissiere_principale(): void
    {
        self::assertStringContainsString('Nouvel approvisionnement', ApproCaisse::page($this->donnees(true)));
        self::assertStringNotContainsString('Nouvel approvisionnement', ApproCaisse::page($this->donnees(false)));
    }

    /** Les boutons de décision n'apparaissent qu'au comptable, et sur ce qui attend. */
    public function test_la_decision_est_reservee_au_comptable(): void
    {
        $sansDroit = ApproCaisse::page($this->donnees(true, false));
        $avecDroit = ApproCaisse::page($this->donnees(false, true));

        // La classe vit aussi dans la feuille de style : c'est l'action du
        // formulaire qui dit si le bouton est réellement offert.
        self::assertStringNotContainsString('/valider"', $sansDroit);
        self::assertStringContainsString('appro-caisse/2/valider"', $avecDroit);

        // Une seule ligne attend : une seule décision proposée.
        self::assertSame(1, substr_count($avecDroit, '/valider"'));
    }

    /** Un rejet doit dire pourquoi : la caissière principale corrigera dessus. */
    public function test_le_rejet_exige_un_motif(): void
    {
        $html = ApproCaisse::page($this->donnees(false, true));

        self::assertStringContainsString('name="motif_rejet"', $html);
        self::assertStringContainsString('required', $html);
    }

    /** L'écran dit ce que l'agence doit trouver dans son tiroir, et quand. */
    public function test_l_ecran_explique_ce_qui_entre_en_caisse(): void
    {
        $html = ApproCaisse::page($this->donnees());

        self::assertStringContainsString('point de caisse', $html);
        self::assertStringContainsString('date de remise', $html);
        self::assertStringContainsString('APP-202609-001', $html);
        self::assertStringContainsString('250 000', $html);
    }

    /** La tuile du menu suit l'habilitation. */
    public function test_la_tuile_suit_l_habilitation(): void
    {
        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');

        self::assertStringContainsString("'available' => \\App\\Security\\ApproCaisseAcces::peutOuvrir()", $navigation);
    }

    // ------------------------------------------------------------------
    // Les autres validations décidées le même jour
    // ------------------------------------------------------------------

    /** Le comptable valide les décaissements au même titre que la Direction. */
    public function test_le_comptable_valide_aussi_les_demandes_de_fonds(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/DemandesFondsController.php');

        self::assertSame(
            3,
            substr_count($source, "Auth::hasAnyRole(['dg', 'assistant_dg', 'comptable'])"),
            'Valider, rejeter, et le bouton qui les propose.'
        );

        // La responsable RH entre dans la gestion des fonds.
        self::assertStringContainsString("'responsable_rh'", $source);
    }

    /**
     * Demander des fournitures est ouvert à toutes les agences.
     *
     * L'écran exigeait la permission « Fournitures de bureau », qui décrit la
     * validation et non la demande : Adjamé, qui n'a que ses droits de saisie,
     * se voyait refuser l'accès et ne pouvait rien commander.
     */
    public function test_toutes_les_agences_peuvent_demander_des_fournitures(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Colisage/ExploitationController.php');
        $ecran = substr($source, strpos($source, 'public function fournitures(): void') ?: 0, 2000);

        self::assertStringNotContainsString('checkPermission(PermissionEntityRegistry::EXPLOITATION_FOURNITURES)', $ecran);
        self::assertStringContainsString('AuthMiddleware::check();', $ecran);

        // La tuile du menu ne dépend plus, elle non plus, de cette permission.
        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');
        self::assertStringNotContainsString('Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_FOURNITURES)', $navigation);
    }

    /** Une agence ne voit et ne commande que pour elle-même. */
    public function test_une_agence_ne_voit_que_ses_demandes(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Colisage/ExploitationController.php');

        self::assertStringContainsString('WHERE f.agency_id = :agence', $source);
        self::assertStringContainsString('voitToutesLesDemandes()', $source);
        // Une requête forgée ne commande pas au nom d'une autre agence.
        self::assertStringContainsString('$agenceId = $agenceUtilisateur;', $source);
    }

    /**
     * Fournitures : le superviseur régional approuve, le comptable confirme, et
     * la livraison n'est possible qu'après les deux signatures.
     */
    public function test_les_fournitures_demandent_deux_signatures(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Colisage/ExploitationController.php');

        self::assertStringContainsString("Auth::hasAnyRole(['superviseur_regional', 'dg', 'assistant_dg'])", $source);
        self::assertStringContainsString("Auth::hasAnyRole(['comptable'])", $source);
        self::assertStringContainsString("\$status === 'CONFIRMEE' && \$etat !== 'APPROUVEE'", $source);
        self::assertStringContainsString("\$status === 'LIVREE' && \$etat !== 'CONFIRMEE'", $source);

        // Chaque signature garde son nom et son heure.
        self::assertStringContainsString('confirmed_by = :acteur', $source);
        self::assertStringContainsString('validated_by = :acteur', $source);
    }
}
