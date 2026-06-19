<?php

namespace App\Controllers\Rh;

use App\Middleware\AuthMiddleware;
use App\View\Pages\Rh\ModulePage;

class RhModuleController extends RhBaseController
{
    public function attendance(): void
    {
        AuthMiddleware::check();
        $this->renderModule('Pointage RH', 'attendance', 'PT', [
            ['Import badgeuse', 'Importer ou synchroniser les pointages journaliers depuis la badgeuse.'],
            ['Corrections contrôlées', 'Régulariser une entrée, une sortie, un retard ou une absence avec motif et trace.'],
            ['Exports', 'Préparer les états mensuels de présence, retards et heures supplémentaires.'],
        ]);
    }

    public function contracts(): void
    {
        AuthMiddleware::check();
        $this->renderModule('Contrats RH', 'contracts', 'CT', [
            ['Contrats actifs', 'Suivre CDI, CDD, stages, essais, renouvellements et échéances.'],
            ['Documents contractuels', 'Centraliser les contrats signés et avenants dans le dossier personnel.'],
            ['Alertes', 'Remonter les contrats proches de fin et les dossiers incomplets.'],
        ]);
    }

    public function payroll(): void
    {
        AuthMiddleware::check();
        $this->renderModule('Paie RH', 'payroll', 'PA', [
            ['Variables de paie', 'Préparer présences, absences, primes, retenues et heures supplémentaires.'],
            ['Bulletins', 'Générer les bulletins après validation des variables RH.'],
            ['Contrôles', 'Vérifier cohérence CNPS, statut, contrat et données personnelles.'],
        ]);
    }

    private function renderModule(string $title, string $active, string $code, array $cards): void
    {
        $this->rhView('rh/module-page', $title, $active, [
            'page' => new ModulePage($title, $code, $cards),
        ]);
    }
}
