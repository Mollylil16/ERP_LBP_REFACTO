<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Database;
use PDO;

final class RapportsController extends ColisageBaseController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // ==========================================
    // RAPPORT JOURNALIER
    // ==========================================

    public function journalier(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('rapports_agence') && !Auth::can('exploitation_synthese')) {
            Session::flash('error', 'Accès refusé aux rapports par agence.');
            $this->redirect('colisage/dashboard');
        }

        $date      = $_GET['date'] ?? date('Y-m-d');
        $agenceId  = !empty($_GET['agence_id']) ? (int) $_GET['agence_id'] : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Sites actifs
        $stmtSites = $this->pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $stmtSites ? $stmtSites->fetchAll(PDO::FETCH_ASSOC) : [];

        // Rapport journalier par agence
        $sqlColis = "
            SELECT
                s.id AS agence_id,
                s.name AS agence_name,
                COUNT(c.id) AS nb_colis,
                COALESCE(SUM(c.poids_total), 0) AS poids_total,
                COALESCE(SUM(CASE WHEN c.devise = 'XOF' THEN c.montant_total ELSE 0 END), 0) AS ca_xof,
                COALESCE(SUM(CASE WHEN c.devise = 'EUR' THEN c.montant_total ELSE 0 END), 0) AS ca_eur,
                COUNT(CASE WHEN c.statut = 'RÉCEPTIONNÉ' THEN 1 END) AS nb_receptiones,
                COUNT(CASE WHEN c.statut = 'RETIRÉ' THEN 1 END) AS nb_retires,
                COUNT(CASE WHEN c.statut NOT IN ('RETIRÉ', 'LIVRÉ', 'ANNULÉ') AND c.date_limite_retrait < NOW() THEN 1 END) AS nb_hors_delai
            FROM company_sites s
            LEFT JOIN lbp_colis c ON c.agence_depart_id = s.id AND DATE(c.created_at) = :date
            WHERE s.is_active = 1
        ";
        $paramsC = ['date' => $date];
        if ($agenceId !== null) {
            $sqlColis .= " AND s.id = :agence_id";
            $paramsC['agence_id'] = $agenceId;
        }
        $sqlColis .= " GROUP BY s.id ORDER BY s.name ASC";

        $stmtColis = $this->pdo->prepare($sqlColis);
        $stmtColis->execute($paramsC);
        $rapportColis = $stmtColis->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Crédits non réglés à la date (total courant par agence)
        $sqlCredits = "
            SELECT
                s.id AS agence_id,
                s.name AS agence_name,
                COALESCE(SUM(CASE WHEN ci.statut = 'NON_REGLE' AND ci.devise = 'XOF' THEN ci.montant ELSE 0 END), 0) AS credits_non_regle_xof,
                COALESCE(SUM(CASE WHEN ci.statut = 'NON_REGLE' AND ci.devise = 'EUR' THEN ci.montant ELSE 0 END), 0) AS credits_non_regle_eur,
                COALESCE(SUM(CASE WHEN ci.statut = 'REGLE' AND DATE(ci.updated_at) = :date AND ci.devise = 'XOF' THEN ci.montant ELSE 0 END), 0) AS credits_regle_xof_jour,
                COALESCE(SUM(CASE WHEN ci.statut = 'REGLE' AND DATE(ci.updated_at) = :date2 AND ci.devise = 'EUR' THEN ci.montant ELSE 0 END), 0) AS credits_regle_eur_jour,
                COUNT(CASE WHEN ci.statut = 'NON_REGLE' THEN 1 END) AS nb_credits_non_regle,
                COUNT(CASE WHEN ci.statut = 'REGLE' AND DATE(ci.updated_at) = :date3 THEN 1 END) AS nb_credits_regle_jour
            FROM company_sites s
            LEFT JOIN lbp_credits_interagence ci ON ci.agence_creanciere_id = s.id
            WHERE s.is_active = 1
        ";
        $paramsK = ['date' => $date, 'date2' => $date, 'date3' => $date];
        if ($agenceId !== null) {
            $sqlCredits .= " AND s.id = :agence_id";
            $paramsK['agence_id'] = $agenceId;
        }
        $sqlCredits .= " GROUP BY s.id ORDER BY s.name ASC";

        $stmtCredits = $this->pdo->prepare($sqlCredits);
        $stmtCredits->execute($paramsK);
        $rapportCredits = $stmtCredits->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Merge par agence_id
        $creditsMap = [];
        foreach ($rapportCredits as $cr) {
            $creditsMap[(int) $cr['agence_id']] = $cr;
        }

        // Totaux
        $totaux = [
            'nb_colis'                  => 0,
            'poids_total'               => 0.0,
            'ca_xof'                    => 0.0,
            'ca_eur'                    => 0.0,
            'nb_hors_delai'             => 0,
            'credits_non_regle_xof'     => 0.0,
            'credits_regle_xof_jour'    => 0.0,
        ];
        foreach ($rapportColis as $row) {
            $totaux['nb_colis']       += (int) $row['nb_colis'];
            $totaux['poids_total']    += (float) $row['poids_total'];
            $totaux['ca_xof']         += (float) $row['ca_xof'];
            $totaux['ca_eur']         += (float) $row['ca_eur'];
            $totaux['nb_hors_delai']  += (int) $row['nb_hors_delai'];

            $cr = $creditsMap[(int) $row['agence_id']] ?? [];
            $totaux['credits_non_regle_xof']  += (float) ($cr['credits_non_regle_xof'] ?? 0);
            $totaux['credits_regle_xof_jour'] += (float) ($cr['credits_regle_xof_jour'] ?? 0);
        }

        $this->colisageView('colisage/rapports/journalier', 'Rapport Journalier par Agence', 'reporting', [
            'date'          => $date,
            'agenceId'      => $agenceId,
            'sites'         => $sites,
            'rapportColis'  => $rapportColis,
            'creditsMap'    => $creditsMap,
            'totaux'        => $totaux,
        ]);
    }

    // ==========================================
    // RAPPORT MENSUEL
    // ==========================================

    public function mensuel(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('rapports_agence') && !Auth::can('exploitation_synthese')) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('colisage/dashboard');
        }

        $mois     = $_GET['mois'] ?? date('Y-m');
        $agenceId = !empty($_GET['agence_id']) ? (int) $_GET['agence_id'] : null;

        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $dateDebut = $mois . '-01';
        $dateFin   = date('Y-m-t', strtotime($dateDebut));

        $stmtSites = $this->pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $stmtSites ? $stmtSites->fetchAll(PDO::FETCH_ASSOC) : [];

        // Points journaliers du mois
        $sqlJournalier = "
            SELECT
                DATE(c.created_at) AS jour,
                s.name AS agence_name,
                COUNT(c.id) AS nb_colis,
                COALESCE(SUM(c.poids_total), 0) AS poids,
                COALESCE(SUM(CASE WHEN c.devise = 'XOF' THEN c.montant_total ELSE 0 END), 0) AS ca_xof,
                COALESCE(SUM(CASE WHEN c.devise = 'EUR' THEN c.montant_total ELSE 0 END), 0) AS ca_eur
            FROM lbp_colis c
            JOIN company_sites s ON c.agence_depart_id = s.id
            WHERE DATE(c.created_at) BETWEEN :debut AND :fin
        ";
        $paramsJ = ['debut' => $dateDebut, 'fin' => $dateFin];
        if ($agenceId !== null) {
            $sqlJournalier .= " AND s.id = :agence_id";
            $paramsJ['agence_id'] = $agenceId;
        }
        $sqlJournalier .= " GROUP BY DATE(c.created_at), s.id ORDER BY jour DESC, s.name ASC";

        $stmtJ = $this->pdo->prepare($sqlJournalier);
        $stmtJ->execute($paramsJ);
        $journaliers = $stmtJ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/rapports/journalier', 'Rapport Mensuel par Agence', 'reporting', [
            'date'         => $dateDebut,
            'mois'         => $mois,
            'agenceId'     => $agenceId,
            'sites'        => $sites,
            'journaliers'  => $journaliers,
            'vueMensuelle' => true,
            'rapportColis' => [],
            'creditsMap'   => [],
            'totaux'       => [],
        ]);
    }

    // ==========================================
    // EXPORT CSV
    // ==========================================

    public function exportCsv(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('rapports_agence') && !Auth::can('exploitation_synthese')) {
            http_response_code(403);
            echo 'Accès refusé';
            exit;
        }

        $date     = $_GET['date'] ?? date('Y-m-d');
        $agenceId = !empty($_GET['agence_id']) ? (int) $_GET['agence_id'] : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Données
        $sqlColis = "
            SELECT
                s.name AS agence,
                COUNT(c.id) AS nb_colis,
                COALESCE(SUM(c.poids_total), 0) AS poids_total,
                COALESCE(SUM(CASE WHEN c.devise = 'XOF' THEN c.montant_total ELSE 0 END), 0) AS ca_xof,
                COALESCE(SUM(CASE WHEN c.devise = 'EUR' THEN c.montant_total ELSE 0 END), 0) AS ca_eur,
                COUNT(CASE WHEN c.statut = 'RÉCEPTIONNÉ' THEN 1 END) AS nb_receptiones,
                COUNT(CASE WHEN c.statut = 'RETIRÉ' THEN 1 END) AS nb_retires
            FROM company_sites s
            LEFT JOIN lbp_colis c ON c.agence_depart_id = s.id AND DATE(c.created_at) = :date
            WHERE s.is_active = 1
        ";
        $params = ['date' => $date];
        if ($agenceId !== null) {
            $sqlColis .= " AND s.id = :agence_id";
            $params['agence_id'] = $agenceId;
        }
        $sqlColis .= " GROUP BY s.id ORDER BY s.name ASC";

        $stmtC = $this->pdo->prepare($sqlColis);
        $stmtC->execute($params);
        $rows = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Crédits
        $sqlK = "
            SELECT s.name AS agence,
                COALESCE(SUM(CASE WHEN ci.statut='NON_REGLE' AND ci.devise='XOF' THEN ci.montant ELSE 0 END),0) AS credits_non_regle_xof,
                COALESCE(SUM(CASE WHEN ci.statut='REGLE' AND DATE(ci.updated_at)=:date AND ci.devise='XOF' THEN ci.montant ELSE 0 END),0) AS credits_regle_xof
            FROM company_sites s
            LEFT JOIN lbp_credits_interagence ci ON ci.agence_creanciere_id = s.id
            WHERE s.is_active = 1
            GROUP BY s.id ORDER BY s.name ASC
        ";
        $stmtK = $this->pdo->prepare($sqlK);
        $stmtK->execute(['date' => $date]);
        $credRows = $stmtK->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $credMap = [];
        foreach ($credRows as $cr) {
            $credMap[$cr['agence']] = $cr;
        }

        // Headers CSV
        $filename = 'rapport_journalier_' . $date . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 pour Excel
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Date', 'Agence', 'Nb Colis', 'Poids Total (kg)',
            'CA XOF', 'CA EUR', 'Nb Réceptionnés', 'Nb Retirés',
            'Crédits Non Réglés (XOF)', 'Crédits Réglés Aujourd\'hui (XOF)'
        ], ';');

        foreach ($rows as $r) {
            $cr = $credMap[$r['agence']] ?? [];
            fputcsv($out, [
                $date,
                $r['agence'],
                $r['nb_colis'],
                number_format((float)$r['poids_total'], 2, '.', ''),
                number_format((float)$r['ca_xof'], 0, '.', ''),
                number_format((float)$r['ca_eur'], 2, '.', ''),
                $r['nb_receptiones'],
                $r['nb_retires'],
                number_format((float)($cr['credits_non_regle_xof'] ?? 0), 0, '.', ''),
                number_format((float)($cr['credits_regle_xof'] ?? 0), 0, '.', ''),
            ], ';');
        }

        fclose($out);
        exit;
    }
}
