<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;

final class ColisageDhlController extends ColisageBaseController
{
    private ColisageRepository $repository;

    public function __construct()
    {
        $this->repository = new ColisageRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'statut' => trim((string) ($_GET['statut'] ?? '')),
            'agence_id' => !empty($_GET['agence_id']) ? (int) $_GET['agence_id'] : '',
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        // Scope agence pour les non-globaux
        $userAgId = Auth::agenceId();
        $isGlobalRole = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);
        if (!$isGlobalRole && !empty($userAgId)) {
            $filters['agence_id'] = $userAgId;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = $this->repository->getDhlRentabilite($filters, $page, 20);

        // Fetch sites
        $sitesStmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/dhl/rentabilite', 'Rentabilité & Suivi DHL Express', 'op_dhl_rentabilite', [
            'data' => $data,
            'filters' => $filters,
            'sites' => $sites,
        ]);
    }

    public function exportCsv(): void
    {
        AuthMiddleware::check();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'statut' => trim((string) ($_GET['statut'] ?? '')),
            'agence_id' => !empty($_GET['agence_id']) ? (int) $_GET['agence_id'] : '',
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $userAgId = Auth::agenceId();
        $isGlobalRole = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);
        if (!$isGlobalRole && !empty($userAgId)) {
            $filters['agence_id'] = $userAgId;
        }

        $result = $this->repository->getDhlRentabilite($filters, 1, 0);
        $items = $result['items'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_rentabilite_dhl_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8

        fputcsv($output, [
            'N° Tracking LBP',
            'N° AWB DHL (LTA)',
            'Date Enregistrement',
            'Expéditeur',
            'Téléphone Expéditeur',
            'Destinataire',
            'Téléphone Destinataire',
            'Agence Départ',
            'Agence Arrivée',
            'Poids (kg)',
            'Prix Vente LBP (FCFA)',
            'Coût Achat DHL (FCFA)',
            'Bénéfice Net LBP (FCFA)',
            'Taux Marge (%)',
            'Statut Colis',
            'N° Facture',
            'Statut Facture'
        ], ';');

        foreach ($items as $item) {
            $prixVente = (float) $item['montant_total'];
            $coutAchat = (float) $item['cout_achat_dhl'];
            $marge = (float) $item['marge_lbp'];
            $tauxMarge = $prixVente > 0 ? round(($marge / $prixVente) * 100, 1) : 0.0;

            fputcsv($output, [
                $item['numero_tracking'],
                $item['awb_dhl'] ?: 'N/A',
                $item['created_at'],
                $item['expediteur_name'],
                $item['expediteur_phone'] ?? '',
                $item['destinataire_name'],
                $item['destinataire_phone'] ?? '',
                $item['agence_depart_name'] ?? '',
                $item['agence_arrivee_name'] ?? '',
                number_format((float) $item['poids_total'], 2, '.', ''),
                number_format($prixVente, 0, '.', ''),
                number_format($coutAchat, 0, '.', ''),
                number_format($marge, 0, '.', ''),
                $tauxMarge . '%',
                $item['statut'],
                $item['numero_facture'] ?? 'Non facturé',
                $item['facture_statut'] ?? 'N/A'
            ], ';');
        }

        fclose($output);
        exit;
    }
}
