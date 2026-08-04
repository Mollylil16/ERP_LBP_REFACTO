<?php

declare(strict_types=1);

namespace App\Repositories\PilotageDg;

use PDO;
use Throwable;

final class PilotageDgDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * Métadonnées du module (libellé, navigation, thème) sans recalcul des KPIs —
     * utilisé par les pages secondaires (Personnel, Validations, Anomalies, Audit).
     *
     * @return array<string,mixed>
     */
    public function moduleMeta(): array
    {
        return $this->dashboardFor('pilotage-dg');
    }

    /**
     * Vue Exécutive Globale : KPIs cross-module réels + répartition du CA par agence.
     *
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('pilotage-dg');

        $caEncaisseJour = 0.0;
        $caEncaisseMois = 0.0;
        $totalImpaye = 0.0;
        $colisEnTransit = 0;
        $colisAujourdhui = 0;
        $effectifActif = 0;
        $presentsAujourdhui = 0;
        $ticketsOuverts = 0;
        $sitesActifs = 0;

        try {
            $caEncaisseJour = (float) $this->pdo->query("SELECT COALESCE(SUM(montant), 0) FROM lbp_paiements WHERE DATE(date_paiement) = CURDATE()")->fetchColumn();
            $caEncaisseMois = (float) $this->pdo->query("SELECT COALESCE(SUM(montant), 0) FROM lbp_paiements WHERE YEAR(date_paiement) = YEAR(CURDATE()) AND MONTH(date_paiement) = MONTH(CURDATE())")->fetchColumn();
            $totalImpaye = (float) $this->pdo->query("SELECT COALESCE(SUM(montant_restant), 0) FROM lbp_factures WHERE statut IN ('emise', 'partiellement_payee', 'en_retard')")->fetchColumn();
            $colisEnTransit = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE statut = 'en_transit'")->fetchColumn();
            $colisAujourdhui = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $effectifActif = (int) $this->pdo->query("SELECT COUNT(*) FROM rh_employees WHERE is_active = 1")->fetchColumn();
            $presentsAujourdhui = (int) $this->pdo->query("SELECT COUNT(*) FROM rh_attendance_daily WHERE attendance_date = CURDATE() AND attendance_status = 'present'")->fetchColumn();
            $ticketsOuverts = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'assigned', 'in_progress', 'waiting')")->fetchColumn();
            $sitesActifs = (int) $this->pdo->query("SELECT COUNT(*) FROM company_sites WHERE is_active = 1")->fetchColumn();
        } catch (Throwable $e) {
            // Fallback silencieux : un module transverse ne doit jamais planter si une table est absente.
        }

        $data['kpis'] = [
            ['label' => 'Encaissé aujourd\'hui', 'value' => number_format($caEncaisseJour, 0, ',', ' ') . ' XOF', 'meta' => 'Tous modes de paiement confondus', 'tone' => 'success'],
            ['label' => 'Encaissé ce mois', 'value' => number_format($caEncaisseMois, 0, ',', ' ') . ' XOF', 'meta' => 'Cumul du mois en cours'],
            ['label' => 'Impayés en cours', 'value' => number_format($totalImpaye, 0, ',', ' ') . ' XOF', 'meta' => 'Factures émises non soldées', 'tone' => $totalImpaye > 0 ? 'warning' : 'success'],
            ['label' => 'Colis en transit', 'value' => (string) $colisEnTransit, 'meta' => 'Manifestes en cours de route'],
            ['label' => 'Colis enregistrés aujourd\'hui', 'value' => (string) $colisAujourdhui, 'meta' => 'Toutes agences confondues'],
            ['label' => 'Effectif actif', 'value' => (string) $effectifActif, 'meta' => 'Employés en poste'],
            ['label' => 'Présents aujourd\'hui', 'value' => (string) $presentsAujourdhui, 'meta' => sprintf('Sur %d employés actifs', $effectifActif)],
            ['label' => 'Tickets ouverts', 'value' => (string) $ticketsOuverts, 'meta' => 'Réclamations & incidents en cours', 'tone' => $ticketsOuverts > 0 ? 'warning' : 'success'],
        ];

        $data['sitesActifs'] = $sitesActifs;

        try {
            $stmt = $this->pdo->query("
                SELECT s.name AS agence_name, COUNT(f.id) AS nb_factures,
                       COALESCE(SUM(f.montant_total), 0) AS ca_total,
                       COALESCE(SUM(f.montant_restant), 0) AS impaye
                FROM company_sites s
                LEFT JOIN lbp_factures f ON f.agence_id = s.id
                    AND YEAR(f.date_emission) = YEAR(CURDATE()) AND MONTH(f.date_emission) = MONTH(CURDATE())
                WHERE s.is_active = 1
                GROUP BY s.id, s.name
                ORDER BY ca_total DESC
            ");
            $data['agenceStats'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $data['agenceStats'] = [];
        }

        return $data;
    }

    /**
     * Supervision du Personnel : présence, évaluations, objectifs, discipline, par employé actif.
     *
     * @return array{employees: array<int, array<string, mixed>>, alerts: array<int, array<string, string>>}
     */
    public function personnelSupervision(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT e.id, e.full_name, e.is_active,
                       sv.name AS service_name, fn.name AS function_name, s.name AS site_name
                FROM rh_employees e
                LEFT JOIN rh_services sv ON e.service_id = sv.id
                LEFT JOIN rh_functions fn ON e.function_id = fn.id
                LEFT JOIN company_sites s ON e.site_id = s.id
                WHERE e.is_active = 1
                ORDER BY e.full_name ASC
            ");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['employees' => [], 'alerts' => []];
        }

        if (empty($employees)) {
            return ['employees' => [], 'alerts' => []];
        }

        $employeeIds = array_map('intval', array_column($employees, 'id'));
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));

        $attendanceByEmployee = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT employee_id,
                       COUNT(*) AS total_jours,
                       SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) AS jours_presents
                FROM rh_attendance_daily
                WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND employee_id IN ($placeholders)
                GROUP BY employee_id
            ");
            $stmt->execute($employeeIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $attendanceByEmployee[(int) $row['employee_id']] = $row;
            }
        } catch (Throwable $e) {
            // Table éventuellement vide sur un environnement neuf : on continue sans ce signal.
        }

        $evalByEmployee = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT re.employee_id, re.overall_score, re.period_label
                FROM rh_evaluations re
                INNER JOIN (
                    SELECT employee_id, MAX(created_at) AS max_created
                    FROM rh_evaluations
                    WHERE status = 'completed' AND employee_id IN ($placeholders)
                    GROUP BY employee_id
                ) latest ON latest.employee_id = re.employee_id AND latest.max_created = re.created_at
            ");
            $stmt->execute($employeeIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $evalByEmployee[(int) $row['employee_id']] = $row;
            }
        } catch (Throwable $e) {
        }

        $objectifsByEmployee = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT employee_id, AVG(progress) AS progression_moyenne
                FROM rh_objectives
                WHERE status IN ('active', 'completed') AND employee_id IN ($placeholders)
                GROUP BY employee_id
            ");
            $stmt->execute($employeeIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $objectifsByEmployee[(int) $row['employee_id']] = $row;
            }
        } catch (Throwable $e) {
        }

        $disciplineByEmployee = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT employee_id, COUNT(*) AS nb_mesures
                FROM rh_disciplinary_actions
                WHERE action_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND employee_id IN ($placeholders)
                GROUP BY employee_id
            ");
            $stmt->execute($employeeIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $disciplineByEmployee[(int) $row['employee_id']] = (int) $row['nb_mesures'];
            }
        } catch (Throwable $e) {
        }

        $alerts = [];
        foreach ($employees as &$emp) {
            $id = (int) $emp['id'];
            $att = $attendanceByEmployee[$id] ?? null;
            $totalJours = $att ? (int) $att['total_jours'] : 0;
            $joursPresents = $att ? (int) $att['jours_presents'] : 0;
            $tauxPresence = $totalJours > 0 ? round(($joursPresents / $totalJours) * 100, 1) : null;

            $eval = $evalByEmployee[$id] ?? null;
            $obj = $objectifsByEmployee[$id] ?? null;
            $progressionMoyenne = ($obj && $obj['progression_moyenne'] !== null) ? round((float) $obj['progression_moyenne'], 1) : null;
            $nbMesures = $disciplineByEmployee[$id] ?? 0;

            $emp['taux_presence'] = $tauxPresence;
            $emp['derniere_evaluation'] = $eval['overall_score'] ?? null;
            $emp['derniere_evaluation_periode'] = $eval['period_label'] ?? null;
            $emp['progression_objectifs'] = $progressionMoyenne;
            $emp['nb_mesures_disciplinaires'] = $nbMesures;

            if ($tauxPresence !== null && $tauxPresence < 70) {
                $alerts[] = ['type' => 'Absentéisme', 'employee' => (string) $emp['full_name'], 'detail' => "Taux de présence de {$tauxPresence}% sur les 30 derniers jours"];
            }
            if ($progressionMoyenne !== null && $progressionMoyenne < 50) {
                $alerts[] = ['type' => 'Objectifs', 'employee' => (string) $emp['full_name'], 'detail' => "Progression moyenne des objectifs : {$progressionMoyenne}%"];
            }
            if ($nbMesures >= 2) {
                $alerts[] = ['type' => 'Discipline', 'employee' => (string) $emp['full_name'], 'detail' => "{$nbMesures} mesure(s) disciplinaire(s) sur les 12 derniers mois"];
            }
        }
        unset($emp);

        return ['employees' => $employees, 'alerts' => $alerts];
    }

    /**
     * Centre de Validation : tout ce qui attend une décision du DG, tous modules confondus.
     *
     * @return array<string,mixed>
     */
    public function pendingValidations(): array
    {
        $workflows = [];
        $legalRequests = [];
        $paymentRequests = [];

        try {
            $stmt = $this->pdo->query("
                SELECT wr.id, wr.process_type, wr.current_step, wr.created_at, e.full_name AS employee_name
                FROM rh_workflow_requests wr
                LEFT JOIN rh_employees e ON wr.employee_id = e.id
                WHERE wr.status = 'pending'
                ORDER BY wr.created_at ASC
            ");
            $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        try {
            $stmt = $this->pdo->query("
                SELECT lr.id, lr.request_type, lr.status, lr.submitted_at, e.full_name AS employee_name
                FROM employee_legal_requests lr
                LEFT JOIN rh_employees e ON lr.employee_id = e.id
                WHERE lr.status IN ('submitted', 'manager_approved', 'hr_approved')
                ORDER BY lr.submitted_at ASC
            ");
            $legalRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        try {
            $stmt = $this->pdo->query("
                SELECT dp.id, dp.montant, dp.devise, dp.motif, dp.date_demande, p.name AS prestataire_name
                FROM lbp_demandes_paiement_prestataires dp
                LEFT JOIN lbp_prestataires p ON dp.prestataire_id = p.id
                WHERE dp.statut = 'en_attente'
                ORDER BY dp.date_demande ASC
            ");
            $paymentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        return [
            'workflows' => $workflows,
            'legalRequests' => $legalRequests,
            'paymentRequests' => $paymentRequests,
            'totalCount' => count($workflows) + count($legalRequests) + count($paymentRequests),
        ];
    }

    /**
     * Anomalies & anti-fraude : écarts de caisse, agents à modifications répétées, agences à impayés anormaux.
     *
     * @return array<string,mixed>
     */
    public function anomalies(): array
    {
        $ecartsCaisse = [];
        $agentsSuspects = [];
        $agencesImpayes = [];

        try {
            $stmt = $this->pdo->query("
                SELECT ej.id, ej.date_jour, ej.ecart_caisse, ej.explication_ecart, s.name AS agence_name,
                       u.full_name AS chef_agence_name
                FROM lbp_etats_journaliers ej
                LEFT JOIN company_sites s ON ej.agence_id = s.id
                LEFT JOIN users u ON ej.chef_agence_id = u.id
                WHERE ej.ecart_caisse != 0
                ORDER BY ABS(ej.ecart_caisse) DESC
                LIMIT 20
            ");
            $ecartsCaisse = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        try {
            $stmt = $this->pdo->query("
                SELECT fal.modifie_par, COUNT(*) AS nb_modifications, u.full_name AS user_name
                FROM factures_audit_log fal
                LEFT JOIN users u ON fal.modifie_par = u.id
                GROUP BY fal.modifie_par, u.full_name
                HAVING nb_modifications >= 3
                ORDER BY nb_modifications DESC
            ");
            $agentsSuspects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        try {
            $stmt = $this->pdo->query("
                SELECT s.name AS agence_name,
                       COUNT(f.id) AS nb_factures,
                       COALESCE(SUM(f.montant_total), 0) AS montant_total,
                       COALESCE(SUM(f.montant_restant), 0) AS montant_impaye
                FROM lbp_factures f
                INNER JOIN company_sites s ON f.agence_id = s.id
                GROUP BY s.id, s.name
                HAVING montant_total > 0
                ORDER BY (montant_impaye / montant_total) DESC
                LIMIT 10
            ");
            $agencesImpayes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        return [
            'ecartsCaisse' => $ecartsCaisse,
            'agentsSuspects' => $agentsSuspects,
            'agencesImpayes' => $agencesImpayes,
        ];
    }

    /**
     * Journal d'audit transverse filtrable (lbp_audit_logs), toutes actions/modules confondus.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function auditLog(array $filters, int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $conditions[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['start_date'])) {
            $conditions[] = 'al.created_at >= :start_date';
            $params['start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = 'al.created_at <= :end_date';
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM lbp_audit_logs al {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT al.*, u.full_name AS user_name
            FROM lbp_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$where}
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $entityTypes = [];
        try {
            $entityTypes = $this->pdo->query("SELECT DISTINCT entity_type FROM lbp_audit_logs ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
        }

        return [
            'logs' => $logs,
            'entityTypes' => $entityTypes,
            'pagination' => ['currentPage' => $page, 'totalPages' => $totalPages, 'totalItems' => $total, 'itemsPerPage' => $perPage],
        ];
    }
}
