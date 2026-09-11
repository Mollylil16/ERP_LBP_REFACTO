<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

/**
 * Suit la délégation d'une vue vers ses composants.
 *
 * Les vues ne contiennent plus de HTML : elles appellent un composant. Vérifier
 * l'adoption d'un composant en lisant seulement le fichier de vue reviendrait
 * donc à chercher le code là où il n'est plus, et à conclure à tort qu'il a
 * disparu. On lit la vue, puis les composants qu'elle importe, et on juge sur
 * l'ensemble.
 */
trait ResoudLesComposantsDeVue
{
    /**
     * Source de la vue, suivie de celle des composants qu'elle utilise.
     */
    private function sourceAvecComposants(string $cheminRelatif): string
    {
        $source = (string) file_get_contents(BASE_PATH . '/' . $cheminRelatif);
        $sources = [$source];

        // Un seul niveau de délégation : au-delà, l'assertion deviendrait
        // toujours vraie puisque presque tous les composants finissent par
        // toucher Dashboard ou Ui.
        preg_match_all('/App\\\\View\\\\Components\\\\([A-Za-z0-9_]+)/', $source, $trouves);

        foreach (array_unique($trouves[1]) as $composant) {
            $chemin = BASE_PATH . '/app/View/Components/' . $composant . '.php';
            if (is_file($chemin)) {
                $sources[] = (string) file_get_contents($chemin);
            }
        }

        return implode("\n", $sources);
    }

    /**
     * Tableaux de bord des modules, tous construits sur le même socle.
     *
     * @return array<int, string>
     */
    private function dashboardViews(): array
    {
        return [
            'views/finance/dashboard.php',
            'views/rh/dashboard.php',
            'views/admin/dashboard.php',
            'views/employee/dashboard.php',
            'views/colisage/dashboard.php',
            'views/logistique/dashboard.php',
            'views/crm/dashboard.php',
            'views/tickets/dashboard.php',
            'views/site_admin/dashboard.php',
            'views/transit_douane/dashboard.php',
            'views/tracking_colis/dashboard.php',
            'views/facturation/dashboard.php',
            'views/entrepots/dashboard.php',
            'views/flotte_transport/dashboard.php',
            'views/portefeuille_clients/dashboard.php',
            'views/agents_correspondants/dashboard.php',
            'views/pilotage_dg/dashboard.php',
            'views/dashboard/index.php',
        ];
    }
}
