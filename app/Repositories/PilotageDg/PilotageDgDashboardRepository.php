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
                       sv.name AS service_name, fn.name AS function_name, s.name AS site_name,
                       u.id AS user_id
                FROM rh_employees e
                LEFT JOIN rh_services sv ON e.service_id = sv.id
                LEFT JOIN rh_functions fn ON e.function_id = fn.id
                LEFT JOIN company_sites s ON e.site_id = s.id
                LEFT JOIN users u ON u.rh_employee_id = e.id
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

        $latestGpsByEmployee = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT g.*
                FROM lbp_employee_presence_gps g
                INNER JOIN (
                    SELECT user_id, MAX(created_at) AS max_created
                    FROM lbp_employee_presence_gps
                    WHERE user_id IN ($placeholders)
                    GROUP BY user_id
                ) latest ON latest.user_id = g.user_id AND latest.max_created = g.created_at
            ");
            $stmt->execute($employeeIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $latestGpsByEmployee[(int) $row['user_id']] = $row;
            }
        } catch (Throwable $e) {
        }

        // Signaux anti-fraude reliés au compte de connexion de l'employé (users.rh_employee_id) :
        // écarts de caisse en tant que chef d'agence, modifications de factures verrouillées,
        // et colis systématiquement sous-déclarés au kg. Un employé sans compte utilisateur lié
        // n'a simplement aucun de ces signaux (pas d'accès système, donc pas d'action à tracer).
        $userIds = array_values(array_unique(array_filter(array_map(
            fn($e) => $e['user_id'] !== null ? (int) $e['user_id'] : null,
            $employees
        ))));

        $ecartCaisseByUser = [];
        $facturesModifByUser = [];
        $colisRatioByUser = [];
        $rapprochementByUser = [];
        $scoresByUser = [];

        if (!empty($userIds)) {
            $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));

            try {
                $stmt = $this->pdo->prepare("
                    SELECT user_id, score_global FROM lbp_scores_employes
                    WHERE user_id IN ($userPlaceholders)
                ");
                $stmt->execute($userIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $scoresByUser[(int) $row['user_id']] = (float) $row['score_global'];
                }
            } catch (Throwable $e) {}

            try {
                $stmt = $this->pdo->prepare("
                    SELECT chef_agence_id AS user_id,
                           SUM(ABS(ecart_caisse)) AS total_ecart,
                           MAX(ABS(ecart_caisse)) AS max_ecart,
                           COUNT(*) AS nb_ecarts
                    FROM lbp_etats_journaliers
                    WHERE ecart_caisse != 0 AND chef_agence_id IN ($userPlaceholders)
                      AND date_jour >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    GROUP BY chef_agence_id
                ");
                $stmt->execute($userIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $ecartCaisseByUser[(int) $row['user_id']] = $row;
                }
            } catch (Throwable $e) {
            }

            try {
                $stmt = $this->pdo->prepare("
                    SELECT modifie_par AS user_id, COUNT(*) AS nb_modifications
                    FROM factures_audit_log
                    WHERE modifie_par IN ($userPlaceholders)
                    GROUP BY modifie_par
                    HAVING nb_modifications >= 3
                ");
                $stmt->execute($userIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $facturesModifByUser[(int) $row['user_id']] = (int) $row['nb_modifications'];
                }
            } catch (Throwable $e) {
            }

            try {
                $colisRatioByUser = $this->colisRatePerKgByAgent()['byAgent'];
            } catch (Throwable $e) {
            }

            try {
                foreach ($this->rapprochementCaisseIndependant() as $r) {
                    $uid = $r['user_id'] !== null ? (int) $r['user_id'] : null;
                    if ($uid === null || !in_array($uid, $userIds, true)) {
                        continue;
                    }
                    $calcule = (float) $r['montant_espece_calcule'];
                    $ecartPhysique = abs(((float) $r['solde_physique_declare']) - $calcule);
                    $ecartDeclare = abs(((float) $r['total_encaisse_xof']) - $calcule);

                    if (!isset($rapprochementByUser[$uid])) {
                        $rapprochementByUser[$uid] = ['maxEcartPhysique' => 0.0, 'maxEcartDeclare' => 0.0];
                    }
                    $rapprochementByUser[$uid]['maxEcartPhysique'] = max($rapprochementByUser[$uid]['maxEcartPhysique'], $ecartPhysique);
                    $rapprochementByUser[$uid]['maxEcartDeclare'] = max($rapprochementByUser[$uid]['maxEcartDeclare'], $ecartDeclare);
                }
            } catch (Throwable $e) {
            }
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
            $gps = $latestGpsByEmployee[$id] ?? null;

            $userId = $emp['user_id'] !== null ? (int) $emp['user_id'] : null;
            $ecartCaisse = $userId !== null ? ($ecartCaisseByUser[$userId] ?? null) : null;
            $nbModifsFactures = $userId !== null ? ($facturesModifByUser[$userId] ?? 0) : 0;
            $colisRatio = $userId !== null ? ($colisRatioByUser[$userId] ?? null) : null;
            $rappro = $userId !== null ? ($rapprochementByUser[$userId] ?? null) : null;

            // Calcul du Score d'Intégrité & Performance (0 à 100 PTS)
            if ($userId !== null && isset($scoresByUser[$userId])) {
                $scoreIntegrite = (int) $scoresByUser[$userId];
            } else {
                $scoreIntegrite = 85;
                if ($tauxPresence !== null) {
                    $scoreIntegrite += ($tauxPresence >= 95 ? 10 : ($tauxPresence >= 80 ? 5 : -15));
                }
                if ($progressionMoyenne !== null) {
                    $scoreIntegrite += ($progressionMoyenne >= 80 ? 5 : -10);
                }
                if ($nbMesures > 0) {
                    $scoreIntegrite -= ($nbMesures * 20);
                }
                if ($gps && ($gps['statut_presence'] ?? '') === 'hors_site') {
                    $scoreIntegrite -= 15;
                }
                if ($ecartCaisse !== null) {
                    $maxEcart = (float) $ecartCaisse['max_ecart'];
                    $scoreIntegrite -= ($maxEcart >= 50000 ? 25 : ($maxEcart >= 10000 ? 15 : 5));
                }
                if ($nbModifsFactures > 0) {
                    $scoreIntegrite -= 15;
                }
                if ($colisRatio !== null && $colisRatio['ratio_vs_moyenne'] < 85) {
                    $ratio = $colisRatio['ratio_vs_moyenne'] / 100;
                    $scoreIntegrite -= ($ratio < 0.50 ? 25 : ($ratio < 0.70 ? 15 : 5));
                }
                if ($rappro !== null && $rappro['maxEcartPhysique'] >= 5000) {
                    $scoreIntegrite -= ($rappro['maxEcartPhysique'] >= 50000 ? 25 : ($rappro['maxEcartPhysique'] >= 10000 ? 15 : 5));
                }
                if ($rappro !== null && $rappro['maxEcartDeclare'] >= 5000) {
                    $scoreIntegrite -= ($rappro['maxEcartDeclare'] >= 50000 ? 15 : ($rappro['maxEcartDeclare'] >= 10000 ? 10 : 5));
                }
                $scoreIntegrite = max(0, min(100, $scoreIntegrite));
            }

            $emp['taux_presence'] = $tauxPresence;
            $emp['derniere_evaluation'] = $eval['overall_score'] ?? null;
            $emp['derniere_evaluation_periode'] = $eval['period_label'] ?? null;
            $emp['progression_objectifs'] = $progressionMoyenne;
            $emp['nb_mesures_disciplinaires'] = $nbMesures;
            $emp['score_integrite'] = $scoreIntegrite;
            $emp['gps_lat'] = $gps ? (float) $gps['latitude'] : null;
            $emp['gps_lng'] = $gps ? (float) $gps['longitude'] : null;
            $emp['gps_statut'] = $gps ? $gps['statut_presence'] : 'inconnu';
            $emp['gps_distance_km'] = $gps ? (float) $gps['distance_site_km'] : null;
            $emp['gps_date'] = $gps ? $gps['created_at'] : null;

            if ($tauxPresence !== null && $tauxPresence < 70) {
                $alerts[] = ['type' => 'Absentéisme', 'employee' => (string) $emp['full_name'], 'detail' => "Taux de présence de {$tauxPresence}% sur les 30 derniers jours"];
            }
            if ($progressionMoyenne !== null && $progressionMoyenne < 50) {
                $alerts[] = ['type' => 'Objectifs', 'employee' => (string) $emp['full_name'], 'detail' => "Progression moyenne des objectifs : {$progressionMoyenne}%"];
            }
            if ($nbMesures >= 2) {
                $alerts[] = ['type' => 'Discipline', 'employee' => (string) $emp['full_name'], 'detail' => "{$nbMesures} mesure(s) disciplinaire(s) sur les 12 derniers mois"];
            }
            if ($ecartCaisse !== null) {
                $alerts[] = ['type' => 'Écart de Caisse', 'employee' => (string) $emp['full_name'], 'detail' => "{$ecartCaisse['nb_ecarts']} écart(s) de caisse sur 180 jours, jusqu'à " . number_format((float) $ecartCaisse['max_ecart'], 0, ',', ' ') . ' XOF'];
            }
            if ($nbModifsFactures > 0) {
                $alerts[] = ['type' => 'Factures Modifiées', 'employee' => (string) $emp['full_name'], 'detail' => "{$nbModifsFactures} modification(s) post-émission sur des factures verrouillées"];
            }
            if ($colisRatio !== null && $colisRatio['ratio_vs_moyenne'] < 85) {
                $alerts[] = ['type' => 'Colis Suspects', 'employee' => (string) $emp['full_name'], 'detail' => "Prix moyen au kg à {$colisRatio['ratio_vs_moyenne']}% de la moyenne des pairs sur {$colisRatio['nb_colis']} colis (180 derniers jours)"];
            }
            if ($rappro !== null && $rappro['maxEcartPhysique'] >= 5000) {
                $alerts[] = ['type' => 'Rapprochement Caisse', 'employee' => (string) $emp['full_name'], 'detail' => 'Écart caisse physique / registre des paiements jusqu\'à ' . number_format($rappro['maxEcartPhysique'], 0, ',', ' ') . ' XOF sur 90 jours'];
            }
            if ($rappro !== null && $rappro['maxEcartDeclare'] >= 5000) {
                $alerts[] = ['type' => 'Rapport Caisse Incohérent', 'employee' => (string) $emp['full_name'], 'detail' => 'Total encaissé déclaré incohérent avec le registre des paiements, jusqu\'à ' . number_format($rappro['maxEcartDeclare'], 0, ',', ' ') . ' XOF d\'écart sur 90 jours'];
            }
        }
        unset($emp);

        // Trier les employés par score d'intégrité décroissant (Tableau d'Honneur)
        $topHonnetes = $employees;
        usort($topHonnetes, fn($a, $b) => $b['score_integrite'] <=> $a['score_integrite']);

        return ['employees' => $employees, 'topHonnetes' => array_slice($topHonnetes, 0, 5), 'alerts' => $alerts];
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
     * Prix moyen au kg facturé par agent enregistreur de colis (created_by), comparé à la moyenne
     * globale sur 180 jours. Partagé entre anomalies() et personnelSupervision() pour éviter
     * de recalculer deux fois la même agrégation.
     *
     * @return array{globalRate: float, byAgent: array<int, array<string, mixed>>}
     */
    private function colisRatePerKgByAgent(): array
    {
        $globalRate = (float) $this->pdo->query("
            SELECT AVG(montant_total / NULLIF(poids_total, 0))
            FROM lbp_colis
            WHERE poids_total > 0 AND created_by IS NOT NULL
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
        ")->fetchColumn();

        $byAgent = [];
        if ($globalRate > 0) {
            $stmt = $this->pdo->query("
                SELECT c.created_by AS user_id, u.full_name AS user_name,
                       COUNT(*) AS nb_colis,
                       AVG(c.montant_total / NULLIF(c.poids_total, 0)) AS prix_kg_moyen_agent
                FROM lbp_colis c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.created_by IS NOT NULL AND c.poids_total > 0
                  AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                GROUP BY c.created_by, u.full_name
                HAVING nb_colis >= 5
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $row['ratio_vs_moyenne'] = round(((float) $row['prix_kg_moyen_agent']) / $globalRate * 100, 1);
                $byAgent[(int) $row['user_id']] = $row;
            }
        }

        return ['globalRate' => $globalRate, 'byAgent' => $byAgent];
    }

    /**
     * Rapprochement de caisse indépendant : pour chaque état journalier avec comptage physique,
     * compare le total déclaré et le comptage physique (blind_count) au montant réellement enregistré
     * dans lbp_paiements (mode espèces) pour l'agence et le jour concernés. Partagé entre anomalies()
     * et personnelSupervision() pour éviter de dupliquer cette agrégation.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rapprochementCaisseIndependant(): array
    {
        $stmt = $this->pdo->query("
            SELECT ej.id, ej.date_jour, ej.agence_id, s.name AS agence_name,
                   u.full_name AS chef_agence_name, ej.chef_agence_id AS user_id,
                   ej.total_encaisse_xof, ej.solde_physique_declare,
                   COALESCE(esp.montant_espece_calcule, 0) AS montant_espece_calcule
            FROM lbp_etats_journaliers ej
            LEFT JOIN company_sites s ON ej.agence_id = s.id
            LEFT JOIN users u ON ej.chef_agence_id = u.id
            LEFT JOIN (
                SELECT f.agence_id, DATE(p.date_paiement) AS date_jour,
                       SUM(p.montant) AS montant_espece_calcule
                FROM lbp_paiements p
                INNER JOIN lbp_factures f ON p.facture_id = f.id
                WHERE p.mode = 'especes'
                GROUP BY f.agence_id, DATE(p.date_paiement)
            ) esp ON esp.agence_id = ej.agence_id AND esp.date_jour = ej.date_jour
            WHERE ej.date_jour >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
              AND ej.solde_physique_declare IS NOT NULL
            ORDER BY ej.date_jour DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Anomalies & anti-fraude : signalements par degrés de gravité, écarts de caisse, agents suspects.
     *
     * @return array<string,mixed>
     */
    public function anomalies(): array
    {
        $signalements = [];
        $ecartsCaisse = [];
        $agentsSuspects = [];
        $agencesImpayes = [];
        $colisSuspects = [];
        $rapprochementIndependant = [];

        // 1. Écarts de caisse
        try {
            $stmt = $this->pdo->query("
                SELECT ej.id, ej.date_jour, ej.ecart_caisse, ej.explication_ecart, s.name AS agence_name,
                       u.full_name AS chef_agence_name, u.id AS user_id
                FROM lbp_etats_journaliers ej
                LEFT JOIN company_sites s ON ej.agence_id = s.id
                LEFT JOIN users u ON ej.chef_agence_id = u.id
                WHERE ej.ecart_caisse != 0
                ORDER BY ABS(ej.ecart_caisse) DESC
                LIMIT 30
            ");
            $ecartsCaisse = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($ecartsCaisse as $e) {
                $absEcart = abs((float) $e['ecart_caisse']);
                $degre = 2;
                $graviteLabel = 'MOYEN';
                $badgeTone = 'warning';

                if ($absEcart >= 50000) {
                    $degre = 4;
                    $graviteLabel = 'TRÈS GRAVE';
                    $badgeTone = 'danger';
                } elseif ($absEcart >= 10000) {
                    $degre = 3;
                    $graviteLabel = 'GRAVE';
                    $badgeTone = 'warning';
                }

                $signalements[] = [
                    'id' => 'EC-' . $e['id'],
                    'degre' => $degre,
                    'gravite' => $graviteLabel,
                    'badgeTone' => $badgeTone,
                    'employee' => $e['chef_agence_name'] ?? 'Chef d\'agence non identifié',
                    'user_id' => $e['user_id'] ?? 0,
                    'agence' => $e['agence_name'] ?? 'Agence',
                    'type' => 'Écart de Caisse non conforme',
                    'description' => 'Disparité financière de ' . number_format((float)$e['ecart_caisse'], 0, ',', ' ') . ' XOF sur le solde théorique de caisse. Explication transmise : ' . ($e['explication_ecart'] ?: 'Aucune justification fournie.'),
                    'montant' => abs((float)$e['ecart_caisse']),
                    'date' => $e['date_jour'] . ' 18:00:00',
                ];
            }
        } catch (Throwable $e) {
        }

        // 2. Audit factures (modifications répétées / suspicions)
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
            foreach ($agentsSuspects as $as) {
                $signalements[] = [
                    'id' => 'AS-' . $as['modifie_par'],
                    'degre' => 3,
                    'gravite' => 'GRAVE',
                    'badgeTone' => 'warning',
                    'employee' => $as['user_name'] ?? 'Agent Inconnu',
                    'user_id' => $as['modifie_par'],
                    'agence' => 'Opérationnel',
                    'type' => 'Modifications Répétées de Factures',
                    'description' => 'Cet employé a effectué ' . $as['nb_modifications'] . ' modifications post-émission sur les montants ou libellés de factures.',
                    'montant' => 0.0,
                    'date' => date('Y-m-d H:i:s'),
                ];
            }
        } catch (Throwable $e) {
        }

        // 3. Agences à impayés anormaux
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

        // 4. Colis systématiquement sous-déclarés par agent (prix/kg anormalement bas vs la moyenne
        // des pairs sur la même période — indice de sous-facturation pour empocher la différence).
        try {
            $colisRates = $this->colisRatePerKgByAgent();
            $colisSuspects = array_values($colisRates['byAgent']);

            foreach ($colisSuspects as $cs) {
                $ratio = $cs['ratio_vs_moyenne'] / 100;
                if ($ratio >= 0.85) {
                    continue; // variance normale, pas un signal
                }

                $degre = 2;
                $graviteLabel = 'MOYEN';
                $badgeTone = 'warning';
                if ($ratio < 0.50) {
                    $degre = 4;
                    $graviteLabel = 'TRÈS GRAVE';
                    $badgeTone = 'danger';
                } elseif ($ratio < 0.70) {
                    $degre = 3;
                    $graviteLabel = 'GRAVE';
                    $badgeTone = 'warning';
                }

                $signalements[] = [
                    'id' => 'CS-' . $cs['user_id'],
                    'degre' => $degre,
                    'gravite' => $graviteLabel,
                    'badgeTone' => $badgeTone,
                    'employee' => $cs['user_name'] ?? 'Agent Inconnu',
                    'user_id' => $cs['user_id'],
                    'agence' => 'Enregistrement Colis',
                    'type' => 'Colis Systématiquement Sous-Déclarés',
                    'description' => 'Prix moyen au kg facturé par cet agent = ' . round((float) $cs['prix_kg_moyen_agent'], 0) . ' XOF/kg, soit ' . $cs['ratio_vs_moyenne'] . '% de la moyenne des autres agents (' . round($colisRates['globalRate'], 0) . ' XOF/kg) sur ' . $cs['nb_colis'] . ' colis (180 derniers jours).',
                    'montant' => 0.0,
                    'date' => date('Y-m-d H:i:s'),
                ];
            }
        } catch (Throwable $e) {
        }

        // 5. Rapprochement de caisse indépendant : compare le comptage physique (blind_count) et le
        // total encaissé auto-déclaré par le chef d'agence à un troisième chiffre qu'il ne maîtrise
        // pas — les paiements espèces réellement enregistrés dans lbp_paiements. L'écart de caisse
        // classique (bloc 1) ne compare que deux nombres saisis par la même personne ; si elle omet
        // un encaissement dans son rapport, cet écart reste à zéro. C'est ce trou que ce contrôle comble.
        try {
            $rapprochementIndependant = $this->rapprochementCaisseIndependant();

            foreach ($rapprochementIndependant as $r) {
                $calcule = (float) $r['montant_espece_calcule'];
                $physique = (float) $r['solde_physique_declare'];
                $declare = (float) $r['total_encaisse_xof'];

                // 5a. Caisse physique vs registre des paiements : un manquant ici n'a jamais été
                // signalé par ecart_caisse si le chef d'agence a déclaré un total cohérent avec son
                // propre comptage — c'est exactement le schéma d'un détournement dissimulé.
                $ecartPhysique = $physique - $calcule;
                if (abs($ecartPhysique) >= 5000) {
                    $absEcart = abs($ecartPhysique);
                    $degre = 2;
                    $graviteLabel = 'MOYEN';
                    $badgeTone = 'warning';
                    if ($absEcart >= 50000) {
                        $degre = 4;
                        $graviteLabel = 'TRÈS GRAVE';
                        $badgeTone = 'danger';
                    } elseif ($absEcart >= 10000) {
                        $degre = 3;
                        $graviteLabel = 'GRAVE';
                        $badgeTone = 'warning';
                    }

                    $sens = $ecartPhysique < 0
                        ? 'Caisse physique INFÉRIEURE au registre des paiements — détournement possible.'
                        : 'Caisse physique SUPÉRIEURE au registre des paiements — encaissement non tracé dans le système.';

                    $signalements[] = [
                        'id' => 'RI-P-' . $r['id'],
                        'degre' => $degre,
                        'gravite' => $graviteLabel,
                        'badgeTone' => $badgeTone,
                        'employee' => $r['chef_agence_name'] ?? 'Chef d\'agence non identifié',
                        'user_id' => $r['user_id'] ?? 0,
                        'agence' => $r['agence_name'] ?? 'Agence',
                        'type' => 'Rapprochement Caisse — Physique vs Registre',
                        'description' => $sens . ' Comptage physique blind : ' . number_format($physique, 0, ',', ' ') . ' XOF. Paiements espèces enregistrés dans le système : ' . number_format($calcule, 0, ',', ' ') . ' XOF. Écart : ' . number_format($ecartPhysique, 0, ',', ' ') . ' XOF.',
                        'montant' => $absEcart,
                        'date' => $r['date_jour'] . ' 18:00:00',
                    ];
                }

                // 5b. Total déclaré vs registre des paiements : le chef d'agence a-t-il déclaré un
                // total encaissé cohérent avec ce que le système a réellement enregistré ce jour-là ?
                $ecartDeclare = $declare - $calcule;
                if (abs($ecartDeclare) >= 5000) {
                    $absEcart = abs($ecartDeclare);
                    $degre = 2;
                    $graviteLabel = 'MOYEN';
                    $badgeTone = 'warning';
                    if ($absEcart >= 50000) {
                        $degre = 4;
                        $graviteLabel = 'TRÈS GRAVE';
                        $badgeTone = 'danger';
                    } elseif ($absEcart >= 10000) {
                        $degre = 3;
                        $graviteLabel = 'GRAVE';
                        $badgeTone = 'warning';
                    }

                    $signalements[] = [
                        'id' => 'RI-D-' . $r['id'],
                        'degre' => $degre,
                        'gravite' => $graviteLabel,
                        'badgeTone' => $badgeTone,
                        'employee' => $r['chef_agence_name'] ?? 'Chef d\'agence non identifié',
                        'user_id' => $r['user_id'] ?? 0,
                        'agence' => $r['agence_name'] ?? 'Agence',
                        'type' => 'Rapport de Caisse Incohérent avec le Registre',
                        'description' => 'Total encaissé déclaré par ce chef d\'agence : ' . number_format($declare, 0, ',', ' ') . ' XOF. Paiements espèces réellement enregistrés dans le système : ' . number_format($calcule, 0, ',', ' ') . ' XOF. Écart : ' . number_format($ecartDeclare, 0, ',', ' ') . ' XOF — vérifier si des encaissements ont été omis ou le rapport falsifié.',
                        'montant' => $absEcart,
                        'date' => $r['date_jour'] . ' 18:00:00',
                    ];
                }
            }
        } catch (Throwable $e) {
        }

        // Trier par gravité décroissante (Degré 4 en premier)
        usort($signalements, function ($a, $b) {
            if ($a['degre'] !== $b['degre']) {
                return $b['degre'] <=> $a['degre'];
            }
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return [
            'signalements' => $signalements,
            'ecartsCaisse' => $ecartsCaisse,
            'agentsSuspects' => $agentsSuspects,
            'agencesImpayes' => $agencesImpayes,
            'colisSuspects' => $colisSuspects,
            'rapprochementIndependant' => $rapprochementIndependant,
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
