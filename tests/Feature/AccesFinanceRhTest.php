<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La direction RH a l'accès complet au module Finance.
 *
 * Décidé par la direction le 01/10/2026. Le module compte quarante et un
 * contrôles de rôles : l'y ajouter un par un l'aurait oubliée au premier écran
 * suivant. La règle est donc posée une fois, dans le middleware.
 *
 * Une exception, héritée d'une décision antérieure : la surveillance des
 * caisses reste au directeur et à l'administrateur. L'écran dit ce que la
 * direction contrôle et quand ; il avait été fermé pour cela, même au
 * comptable.
 */
final class AccesFinanceRhTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Middleware/RoleMiddleware.php');
    }

    /** La règle est posée une fois, et non recopiée écran par écran. */
    public function test_la_regle_est_posee_une_seule_fois(): void
    {
        $source = $this->source();

        self::assertStringContainsString('estLaDirectionRhDansFinance()', $source);
        self::assertStringContainsString("Auth::hasAnyRole(['responsable_rh'])", $source);
        self::assertSame(
            1,
            substr_count($source, "hasAnyRole(['responsable_rh'])"),
            'Une seule porte, pour qu\'un nouvel écran ne l\'oublie pas.'
        );
    }

    /** Elle ne vaut que sous /finance : le reste du logiciel ne bouge pas. */
    public function test_elle_ne_vaut_que_dans_finance(): void
    {
        $source = $this->source();

        self::assertStringContainsString("str_contains(\$chemin, '/finance')", $source);
        self::assertStringContainsString('REQUEST_URI', $source);
    }

    /** La surveillance des caisses reste fermée : elle dit quand la direction regarde. */
    public function test_la_surveillance_des_caisses_reste_fermee(): void
    {
        self::assertStringContainsString(
            "!str_contains(\$chemin, '/finance/controle-caisse')",
            $this->source()
        );
    }

    /**
     * Les droits qui ne sont pas des portes d'écran ne changent pas : saisir un
     * appro reste à la caissière principale, le valider au comptable.
     */
    public function test_la_regle_n_ouvre_que_des_ecrans(): void
    {
        $appro = (string) file_get_contents(BASE_PATH . '/app/Security/ApproCaisseAcces.php');

        self::assertSame(['caissiere_principale'], \App\Security\ApproCaisseAcces::ROLES_SAISIE);
        self::assertSame(['comptable'], \App\Security\ApproCaisseAcces::ROLES_VALIDATION);
        self::assertStringNotContainsString('responsable_rh', $appro);
    }
}
