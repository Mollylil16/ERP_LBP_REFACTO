<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Controllers\Finance\FinanceController;
use ReflectionClass;
use Tests\TestCase;

/**
 * Qui tient le guichet.
 *
 * Dans les agences sans caissière dédiée — Adjamé aujourd'hui — c'est l'agent
 * lui-même qui établit la facture et prend l'argent. Lui refuser l'encaissement
 * empêchait l'agence de travailler.
 *
 * Ce test fixe la frontière : ce que l'agent de guichet peut faire, et ce qui
 * reste un acte de contrôle réservé au chef d'agence.
 */
final class RolesGuichetTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function guichet(): array
    {
        // La constante est privée : getConstant() ne la voit pas, il faut passer
        // par la constante réfléchie.
        $constante = (new ReflectionClass(FinanceController::class))
            ->getReflectionConstant('ROLES_GUICHET');

        self::assertNotFalse($constante, 'FinanceController::ROLES_GUICHET est introuvable.');

        /** @var array<int, string> $roles */
        $roles = (array) $constante->getValue();

        return $roles;
    }

    public function test_l_agent_de_saisie_tient_le_guichet(): void
    {
        $guichet = $this->guichet();

        // agent_saisie est le rôle le plus répandu de l'entreprise, et celui des
        // agences qui n'ont pas encore de caissière.
        self::assertContains('agent_saisie', $guichet);
        self::assertContains('agent_enregistrement', $guichet);
        self::assertContains('caissiere', $guichet);
        self::assertContains('chef_agence', $guichet);
    }

    public function test_les_actes_de_controle_restent_hors_du_guichet(): void
    {
        $source = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Finance/FinanceController.php'
        );

        /*
         * Ces trois actions défont ou valident le travail d'un autre. Les ouvrir
         * au guichet reviendrait à ce que celui qui encaisse puisse aussi
         * effacer sa propre trace.
         */
        foreach (['factureDelete', 'factureReinitialiser', 'clotureConsolider'] as $action) {
            $position = strpos($source, 'function ' . $action . '(');
            self::assertNotFalse($position, "Action {$action} introuvable.");

            $extrait = substr($source, $position, 600);
            self::assertStringNotContainsString(
                'ROLES_GUICHET',
                $extrait,
                "{$action} ne doit pas être ouverte au guichet : c'est un acte de contrôle."
            );
        }
    }

    public function test_le_parametrage_du_gardiennage_reste_au_chef_d_agence(): void
    {
        // Ce réglage fixe ce qui sera facturé aux clients en frais de garde.
        $source = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Logistique/LogistiqueParametresController.php'
        );

        self::assertStringContainsString("RoleMiddleware::check(['admin', 'chef_agence'])", $source);
        self::assertStringNotContainsString('agent_saisie', $source);
    }

    /**
     * La règle s'intitule « Même utilisateur qui crée, valide ET encaisse » :
     * elle décrit trois rôles. Le code en exigeait deux, et signalait donc comme
     * grave tout agent qui facturait puis encaissait son client — le
     * fonctionnement normal d'une agence sans caissière.
     */
    public function test_le_cumul_de_roles_ne_signale_qu_a_partir_de_trois(): void
    {
        $source = (string) file_get_contents(
            BASE_PATH . '/app/Services/Shared/IntegrityRuleEngine.php'
        );

        self::assertMatchesRegularExpression(
            "/\\\$params\\['min_roles_cumules'\\]\s*\?\?\s*3/",
            $source,
            'Le seuil par défaut doit être de trois rôles cumulés.'
        );
    }

    public function test_les_roles_du_guichet_existent_reellement(): void
    {
        $connus = array_merge(
            array_keys(\App\Services\Admin\AdminService::AVAILABLE_ROLES),
            \App\Security\ModuleAccess::ROLES_HORS_CATALOGUE
        );

        foreach ($this->guichet() as $role) {
            self::assertContains(
                $role,
                $connus,
                "Le rôle {$role} n'existe pas : il n'ouvrirait rien et ne lèverait aucune erreur."
            );
        }
    }
}
