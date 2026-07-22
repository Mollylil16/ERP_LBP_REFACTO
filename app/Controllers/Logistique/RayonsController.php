<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Logistique\RayonRepository;
use App\Repositories\Logistique\LogistiqueDashboardRepository;
use App\Services\Logistique\RayonService;
use App\View\Pages\Logistique\RayonsPage;
use App\Helpers\Session;
use App\Helpers\View;
use PDO;

final class RayonsController extends LogistiqueBaseController
{
    private RayonRepository $rayonRepository;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->rayonRepository = new RayonRepository($pdo);
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $agenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : null;
        $rayons = $this->rayonRepository->getAllRayons($agenceId);

        $sites = $this->getSites();
        $dashboardRepo = new LogistiqueDashboardRepository(Database::getConnection());
        $module = $dashboardRepo->dashboard();

        $page = new RayonsPage(
            $rayons,
            $sites,
            Session::getFlash('success'),
            Session::getFlash('error')
        );

        $this->logistiqueView(
            'logistique/rayons',
            'Gestion des Rayons - Logistique',
            'rayons',
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

        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'agence_id' => (int) ($_POST['agence_id'] ?? 1),
            'code_rayon' => trim((string) ($_POST['code_rayon'] ?? '')),
            'nom_rayon' => trim((string) ($_POST['nom_rayon'] ?? '')),
            'capacite_max' => (int) ($_POST['capacite_max'] ?? 50),
            'statut' => (string) ($_POST['statut'] ?? 'ACTIF'),
        ];

        if (empty($data['code_rayon']) || empty($data['nom_rayon'])) {
            Session::flash('error', 'Le code et le nom du rayon sont obligatoires.');
            header('Location: ' . View::url('logistique/rayons'));
            exit;
        }

        if ($id > 0) {
            $this->rayonRepository->updateRayon($id, $data);
            Session::flash('success', 'Le rayon ' . $data['code_rayon'] . ' a été mis à jour.');
        } else {
            $this->rayonRepository->createRayon($data);
            Session::flash('success', 'Le nouveau rayon ' . $data['code_rayon'] . ' a été créé avec succès.');
        }

        header('Location: ' . View::url('logistique/rayons'));
        exit;
    }

    public function delete(string $id): void
    {
        AuthMiddleware::check();
        $rayonId = (int) $id;

        if ($rayonId > 0) {
            $this->rayonRepository->deleteRayon($rayonId);
            Session::flash('success', 'Le rayon a été supprimé.');
        }

        header('Location: ' . View::url('logistique/rayons'));
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
