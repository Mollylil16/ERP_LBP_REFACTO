<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;

final class FinanceDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('finance');
    }

    /**
     * Get rich financial statistics for the dashboard
     *
     * @return array<string, mixed>
     */
    /**
     * Ce qui attend une décision, aujourd'hui, dans le module Finance.
     *
     * Le tableau de bord disait l'argent ; il ne disait pas le travail. Ces
     * quatre lignes sont la liste du matin : une caisse jamais comptée, un
     * approvisionnement qui dort, un envoi non rapproché, une demande de fonds
     * sans réponse. Chacune renvoie à l'écran où l'on tranche.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enAttente(): array
    {
        $hier = date('Y-m-d', strtotime('-1 day'));
        $moisDebut = date('Y-m-01');

        $caisses = (int) $this->compte("
            SELECT COUNT(DISTINCT jours.agence_id)
            FROM (
                SELECT DATE(p.date_paiement) AS jour, COALESCE(p.agence_id, u.agence_id, f.agence_id) AS agence_id
                FROM lbp_paiements p
                JOIN lbp_factures f ON f.id = p.facture_id
                LEFT JOIN users u ON u.id = p.caissiere_id
                WHERE DATE(p.date_paiement) = :jour AND p.devise = 'XOF'
                GROUP BY jour, agence_id
            ) AS jours
            LEFT JOIN lbp_etats_journaliers e
                   ON e.agence_id = jours.agence_id AND e.date_jour = jours.jour
                  AND e.statut IN ('soumis', 'consolide')
            WHERE e.id IS NULL
        ", ['jour' => $hier]);

        $appros = (int) $this->compte("SELECT COUNT(*) FROM lbp_appros_caisse WHERE statut = 'en_attente'");

        $envois = (int) $this->compte(
            'SELECT COUNT(*) FROM lbp_rappro_envois WHERE date_envoi >= :debut AND colis_factures IS NULL',
            ['debut' => $moisDebut]
        );

        $fonds = (int) $this->compte("SELECT COUNT(*) FROM lbp_demandes_fonds WHERE statut = 'en_attente'");

        return [
            [
                'libelle' => 'Caisses non comptées hier',
                'valeur' => $caisses,
                'detail' => $caisses > 0 ? 'Ces agences sont bloquées ce matin' : 'Toutes les caisses ont été soumises',
                'href' => 'finance/controle-caisse?jour=' . $hier,
                'urgent' => $caisses > 0,
            ],
            [
                'libelle' => 'Approvisionnements à valider',
                'valeur' => $appros,
                'detail' => "Saisis par la caissière principale",
                'href' => 'finance/appro-caisse?statut=en_attente',
                'urgent' => $appros > 0,
            ],
            [
                'libelle' => 'Envois à rapprocher',
                'valeur' => $envois,
                'detail' => "La facture de la compagnie n'est pas encore saisie",
                'href' => 'finance/rapprochement-envois',
                'urgent' => false,
            ],
            [
                'libelle' => 'Demandes de fonds en attente',
                'valeur' => $fonds,
                'detail' => 'À valider ou à rejeter',
                'href' => 'finance/fonds?statut=en_attente',
                'urgent' => $fonds > 0,
            ],
        ];
    }

    /**
     * Un compteur qui ne fait pas tomber l'écran si la table n'existe pas
     * encore sur cette base.
     *
     * @param array<string, mixed> $parametres
     */
    private function compte(string $sql, array $parametres = []): float
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public function getFinanceStats(): array
    {
        // Dynamic exchange rate
        $tauxChange = 655.957;
        try {
            $stmtRate = $this->pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
            if ($stmtRate) {
                $val = $stmtRate->fetchColumn();
                if (is_numeric($val) && (float)$val > 0) {
                    $tauxChange = (float) $val;
                }
            }
        } catch (\Throwable $e) {}

        // 1. Sum total facturé, encaissé et restant dû par devise
        $stmt = $this->pdo->query("
            SELECT devise,
                   COALESCE(SUM(montant_total), 0) as total_facture,
                   COALESCE(SUM(montant_encaisse), 0) as total_encaisse,
                   COALESCE(SUM(montant_restant), 0) as total_restant
            FROM lbp_factures
            WHERE statut <> 'annulee'
            GROUP BY devise
        ");
        $factureTotals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $kpis = [
            'facture_xof' => 0.0,
            'facture_eur' => 0.0,
            'encaisse_xof' => 0.0,
            'encaisse_eur' => 0.0,
            'restant_xof' => 0.0,
            'restant_eur' => 0.0,
            'taux_change_eur' => $tauxChange,
            'is_eur_agency' => false,
        ];

        foreach ($factureTotals as $row) {
            $devise = strtoupper($row['devise']);
            if ($devise === 'XOF') {
                $kpis['facture_xof'] = (float) $row['total_facture'];
                $kpis['encaisse_xof'] = (float) $row['total_encaisse'];
                $kpis['restant_xof'] = (float) $row['total_restant'];
            } elseif ($devise === 'EUR') {
                $kpis['facture_eur'] = (float) $row['total_facture'];
                $kpis['encaisse_eur'] = (float) $row['total_encaisse'];
                $kpis['restant_eur'] = (float) $row['total_restant'];
            }
        }

        // Check user agency currency
        $userAgId = \App\Helpers\Auth::agenceId();
        if ($userAgId !== null && $userAgId > 0) {
            try {
                $stmtAg = $this->pdo->prepare("SELECT code, country_code FROM company_sites WHERE id = :id LIMIT 1");
                $stmtAg->execute(['id' => $userAgId]);
                $ag = $stmtAg->fetch(PDO::FETCH_ASSOC);
                if ($ag && (str_contains(strtoupper((string) $ag['code']), 'FR') || strtoupper((string) ($ag['country_code'] ?? '')) === 'FR')) {
                    $kpis['is_eur_agency'] = true;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Count pending supplier payouts
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_demandes_paiement_prestataires WHERE statut = 'en_attente'
        ");
        $kpis['pending_payouts'] = (int) $stmt->fetchColumn();

        // 3. Count daily closures pending consolidation
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_etats_journaliers WHERE statut = 'soumis'
        ");
        $kpis['pending_closures'] = (int) $stmt->fetchColumn();

        return $kpis;
    }

    /**
     * Get recent invoices
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFactures(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.name as client_name
            FROM lbp_factures f
            LEFT JOIN lbp_clients c ON f.client_id = c.id
            ORDER BY f.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent daily closures
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEtats(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.name as agence_name
            FROM lbp_etats_journaliers e
            LEFT JOIN company_sites s ON e.agence_id = s.id
            ORDER BY e.date_jour DESC, e.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent double-entry accounting entries
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEcritures(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM lbp_ecritures_comptables
            ORDER BY id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    /**
     * Get encaissements trend data for the last 30 days
     * @return array<int, array<string, mixed>>
     */
    public function getEncaissementsTrendData(): array
    {
        $stmt = $this->pdo->query("
            SELECT DATE(date_paiement) as date_p,
                   mode_paiement,
                   SUM(montant) as total
            FROM lbp_paiements
            WHERE date_paiement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND devise = 'XOF'
            GROUP BY DATE(date_paiement), mode_paiement
            ORDER BY date_p ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Structure graph dates for last 30 days
        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $dayStr = date('Y-m-d', strtotime("-{$i} days"));
            $dayLabel = date('d/m', strtotime("-{$i} days"));
            $trend[$dayStr] = [
                'label' => $dayLabel,
                'especes' => 0.0,
                'mobile' => 0.0,
                'total' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $d = $row['date_p'] ?? '';
            if (isset($trend[$d])) {
                $montant = (float) $row['total'];
                $mode = strtoupper(trim((string)($row['mode_paiement'] ?? 'ESPECES')));
                if (in_array($mode, ['ESPECES', 'CASH'], true)) {
                    $trend[$d]['especes'] += $montant;
                } else {
                    $trend[$d]['mobile'] += $montant;
                }
                $trend[$d]['total'] += $montant;
            }
        }

        return array_values($trend);
    }
}
