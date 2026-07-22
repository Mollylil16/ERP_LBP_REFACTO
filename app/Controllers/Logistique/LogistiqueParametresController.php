<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Logistique\LogistiqueSettingsRepository;
use App\Repositories\Logistique\LogistiqueDashboardRepository;
use App\View\Pages\Logistique\ParametresPage;
use App\Helpers\Session;
use App\Helpers\View;
use PDO;

final class LogistiqueParametresController extends LogistiqueBaseController
{
    private LogistiqueSettingsRepository $settingsRepository;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->settingsRepository = new LogistiqueSettingsRepository($pdo);
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $agenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : null;
        $settings = $this->settingsRepository->getSettings($agenceId);
        $sites = $this->getSites();

        $dashboardRepo = new LogistiqueDashboardRepository(Database::getConnection());
        $module = $dashboardRepo->dashboard();

        $page = new ParametresPage(
            $settings,
            $sites,
            Session::getFlash('success')
        );

        $this->logistiqueView(
            'logistique/parametres',
            'Délais & Gardiennage - Logistique',
            'parametres',
            $module,
            [
                'page' => $page,
                'dashboardModule' => $module,
            ]
        );
    }

    public function store(): void
    {
        AuthMiddleware::check();

        $agenceId = isset($_POST['agence_id']) && (int) $_POST['agence_id'] > 0 ? (int) $_POST['agence_id'] : null;

        $data = [
            'delai_gratuit_jours' => (int) ($_POST['delai_gratuit_jours'] ?? 7),
            'frais_gardiennage_par_jour' => (float) ($_POST['frais_gardiennage_par_jour'] ?? 500.0),
            'auto_assign_rayon' => isset($_POST['auto_assign_rayon']) ? 1 : 0,
        ];

        $this->settingsRepository->saveSettings($data, $agenceId);
        Session::flash('success', 'Les paramètres de délai de récupération et de gardiennage ont été mis à jour avec succès.');

        header('Location: ' . View::url('logistique/parametres'));
        exit;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSites(): array
    {
        try {
            $stmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
            $sites = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($sites)) {
                return $sites;
            }
        } catch (\Throwable $e) {
            // Ignorer si la table n'existe pas
        }

        return [
            ['id' => 1, 'name' => 'Agence Principale'],
        ];
    }
}
