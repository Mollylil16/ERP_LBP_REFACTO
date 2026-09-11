<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardAdoptionTest extends TestCase
{
    use ResoudLesComposantsDeVue;

    public function test_module_dashboards_use_shared_dashboard_component(): void
    {
        foreach ($this->dashboardViews() as $file) {
            $source = $this->sourceAvecComposants($file);

            // Un composant du même namespace appelle Dashboard sans instruction
            // « use » : exiger l'import reviendrait à interdire de placer le
            // rendu dans un composant, ce que la règle des vues impose.
            self::assertTrue(
                str_contains($source, 'Components\\Dashboard') || str_contains($source, 'Dashboard::'),
                $file . ' ne s\'appuie sur le composant Dashboard ni directement ni par délégation.'
            );
            self::assertTrue(
                str_contains($source, 'Dashboard::kpis')
                || str_contains($source, 'Dashboard::businessModuleDashboard'),
                $file . ' doit utiliser les composants Dashboard.'
            );
        }
    }
}
