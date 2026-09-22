<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use PDO;

class GroupageCodesNonPayesController extends LogistiqueBaseController
{
    private const ROLES_AUTORISES = [
        'responsable_groupage',
        'agent_groupage',
        'caissiere_principale',
        'dg',
        'assistant_dg',
        'assistante_dg',
        'comptable',
        'superviseur_general',
        'admin',
    ];

    /**
     * Page principale : Tableau des Codes Non Payés (Groupages).
     */
    public function index(): void
    {
        RoleMiddleware::check(self::ROLES_AUTORISES);

        $pdo = Database::getConnection();

        // Récupération des agences pour le filtre
        $agencesStmt = $pdo->query("SELECT id, name, code FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $agences = $agencesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Filtres
        $dateDebut = trim((string) ($_GET['date_debut'] ?? date('Y-m-d')));
        $dateFin = trim((string) ($_GET['date_fin'] ?? date('Y-m-d')));
        if ($dateDebut === '') {
            $dateDebut = date('Y-m-d');
        }
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        $selectedAgence = trim((string) ($_GET['agence_id'] ?? 'all'));
        $typeTransport = trim((string) ($_GET['type_transport'] ?? 'all'));
        $dateFilterType = trim((string) ($_GET['date_filter_type'] ?? 'facture'));
        $search = trim((string) ($_GET['q'] ?? ''));

        $data = $this->fetchData($pdo, [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'agence_id' => $selectedAgence,
            'type_transport' => $typeTransport,
            'date_filter_type' => $dateFilterType,
            'search' => $search,
        ]);

        $this->logistiqueView(
            'logistique/codes_non_payes',
            'Tableau des Codes Non Payés — Groupages',
            'codes_non_payes',
            [],
            array_merge($data, [
                'agences' => $agences,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'selectedAgence' => $selectedAgence,
                'typeTransport' => $typeTransport,
                'dateFilterType' => $dateFilterType,
                'search' => $search,
            ])
        );
    }

    /**
     * Mise à jour en ligne (AJAX) du Groupe / Lot d'un colis.
     */
    public function updateGroupe(): void
    {
        RoleMiddleware::check(self::ROLES_AUTORISES);

        header('Content-Type: application/json');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
            exit;
        }

        $colisId = (int) ($_POST['colis_id'] ?? 0);
        $groupeCode = strtoupper(trim((string) ($_POST['groupe_code'] ?? '')));

        if ($colisId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identifiant colis invalide']);
            exit;
        }

        $pdo = Database::getConnection();

        // Vérifier l'existence de la colonne groupe_code
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM lbp_colis LIKE 'groupe_code'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE lbp_colis ADD COLUMN groupe_code VARCHAR(50) NULL");
            }

            $stmt = $pdo->prepare("UPDATE lbp_colis SET groupe_code = :groupe WHERE id = :id");
            $stmt->execute([
                'groupe' => $groupeCode !== '' ? $groupeCode : null,
                'id' => $colisId,
            ]);

            echo json_encode([
                'success' => true,
                'colis_id' => $colisId,
                'groupe_code' => $groupeCode,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Export / Impression PDF conforme au document Excel S.T.T-CI.
     */
    public function exportPdf(): void
    {
        RoleMiddleware::check(self::ROLES_AUTORISES);

        $pdo = Database::getConnection();

        $dateDebut = trim((string) ($_GET['date_debut'] ?? date('Y-m-d')));
        $dateFin = trim((string) ($_GET['date_fin'] ?? date('Y-m-d')));
        if ($dateDebut === '') {
            $dateDebut = date('Y-m-d');
        }
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        $selectedAgence = trim((string) ($_GET['agence_id'] ?? 'all'));
        $typeTransport = trim((string) ($_GET['type_transport'] ?? 'all'));
        $dateFilterType = trim((string) ($_GET['date_filter_type'] ?? 'facture'));
        $search = trim((string) ($_GET['q'] ?? ''));

        $data = $this->fetchData($pdo, [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'agence_id' => $selectedAgence,
            'type_transport' => $typeTransport,
            'date_filter_type' => $dateFilterType,
            'search' => $search,
        ]);

        // Nom de l'agence sélectionnée
        $nomAgence = 'Toutes les agences';
        if ($selectedAgence !== 'all' && (int) $selectedAgence > 0) {
            $agStmt = $pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $agStmt->execute(['id' => (int) $selectedAgence]);
            $nomAgence = $agStmt->fetchColumn() ?: ('Agence #' . $selectedAgence);
        }

        extract(array_merge($data, [
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'nomAgence' => $nomAgence,
            'selectedAgence' => $selectedAgence,
            'typeTransport' => $typeTransport,
        ]));

        require BASE_PATH . '/views/logistique/codes_non_payes_pdf.php';
        exit;
    }

    /**
     * Export Excel (format CSV UTF-8 avec BOM pour compatibilité Excel totale).
     */
    public function exportExcel(): void
    {
        RoleMiddleware::check(self::ROLES_AUTORISES);

        $pdo = Database::getConnection();

        $dateDebut = trim((string) ($_GET['date_debut'] ?? date('Y-m-d')));
        $dateFin = trim((string) ($_GET['date_fin'] ?? date('Y-m-d')));
        if ($dateDebut === '') {
            $dateDebut = date('Y-m-d');
        }
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        $selectedAgence = trim((string) ($_GET['agence_id'] ?? 'all'));
        $typeTransport = trim((string) ($_GET['type_transport'] ?? 'all'));
        $dateFilterType = trim((string) ($_GET['date_filter_type'] ?? 'facture'));
        $search = trim((string) ($_GET['q'] ?? ''));

        $data = $this->fetchData($pdo, [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'agence_id' => $selectedAgence,
            'type_transport' => $typeTransport,
            'date_filter_type' => $dateFilterType,
            'search' => $search,
        ]);

        $filename = 'Codes_Non_Payes_' . ($dateDebut === $dateFin ? $dateDebut : $dateDebut . '_au_' . $dateFin) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // BOM pour UTF-8 dans Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // En-tête
        fputcsv($output, ['S.T.T-CI — GROUPAGES — CODES NON PAYES'], ';');
        fputcsv($output, ['Période : ' . ($dateDebut === $dateFin ? $dateDebut : "Du {$dateDebut} au {$dateFin}")], ';');
        fputcsv($output, [], ';');

        // Lignes de colonnes
        fputcsv($output, [
            'DATE',
            'GROUPE',
            'CODE / TRACKING',
            'AGENCE',
            'EXPÉDITEUR',
            'DESTINATAIRE',
            'TYPE ENVOI',
            'MONTANT EURO (€)',
            'MONTANT À CRÉDIT (FCFA)',
            'MONTANT PAYÉ (FCFA)',
            'MONTANT RESTANT (FCFA)',
            'STATUT FACTURE',
        ], ';');

        foreach ($data['groupes'] as $groupeNom => $lignes) {
            foreach ($lignes as $row) {
                fputcsv($output, [
                    $row['date_formatted'],
                    $row['groupe_display'],
                    $row['code'],
                    $row['agence_nom'],
                    $row['expediteur_nom'] ?? '',
                    $row['destinataire_nom'] ?? '',
                    $row['type_envoi_label'],
                    $row['montant_eur'] > 0 ? number_format($row['montant_eur'], 2, ',', ' ') : '-',
                    number_format($row['montant_total'], 0, ',', ' '),
                    number_format($row['montant_encaisse'], 0, ',', ' '),
                    number_format($row['montant_restant'], 0, ',', ' '),
                    $row['facture_statut_label'],
                ], ';');
            }
            // Sous-total
            $sub = $data['sousTotauxGroupes'][$groupeNom] ?? null;
            if ($sub) {
                fputcsv($output, [
                    '',
                    'SOUS-TOTAL ' . $groupeNom,
                    $sub['count'] . ' colis',
                    '',
                    '',
                    '',
                    '',
                    $sub['montant_eur'] > 0 ? number_format($sub['montant_eur'], 2, ',', ' ') : '-',
                    number_format($sub['montant_total'], 0, ',', ' '),
                    number_format($sub['montant_encaisse'], 0, ',', ' '),
                    number_format($sub['montant_restant'], 0, ',', ' '),
                    '',
                ], ';');
            }
            fputcsv($output, [], ';');
        }

        // Section Colis Export si séparée
        if (!empty($data['colisExport'])) {
            fputcsv($output, ['--- SECTION COLIS EXPORT ---'], ';');
            foreach ($data['colisExport'] as $row) {
                fputcsv($output, [
                    $row['date_formatted'],
                    $row['groupe_display'],
                    $row['code'],
                    $row['agence_nom'],
                    $row['expediteur_nom'] ?? '',
                    $row['destinataire_nom'] ?? '',
                    $row['type_envoi_label'],
                    $row['montant_eur'] > 0 ? number_format($row['montant_eur'], 2, ',', ' ') : '-',
                    number_format($row['montant_total'], 0, ',', ' '),
                    number_format($row['montant_encaisse'], 0, ',', ' '),
                    number_format($row['montant_restant'], 0, ',', ' '),
                    $row['facture_statut_label'],
                ], ';');
            }
            fputcsv($output, [
                '',
                'SOUS-TOTAL COLIS EXPORT',
                $data['totauxExport']['count'] . ' colis',
                '',
                '',
                '',
                '',
                $data['totauxExport']['montant_eur'] > 0 ? number_format($data['totauxExport']['montant_eur'], 2, ',', ' ') : '-',
                number_format($data['totauxExport']['montant_total'], 0, ',', ' '),
                number_format($data['totauxExport']['montant_encaisse'], 0, ',', ' '),
                number_format($data['totauxExport']['montant_restant'], 0, ',', ' '),
                '',
            ], ';');
            fputcsv($output, [], ';');
        }

        // Total général
        fputcsv($output, [
            '',
            'TOTAL GÉNÉRAL',
            $data['totaux']['count'] . ' colis au total',
            '',
            '',
            '',
            '',
            $data['totaux']['montant_eur'] > 0 ? number_format($data['totaux']['montant_eur'], 2, ',', ' ') : '-',
            number_format($data['totaux']['montant_total'], 0, ',', ' '),
            number_format($data['totaux']['montant_encaisse'], 0, ',', ' '),
            number_format($data['totaux']['montant_restant'], 0, ',', ' '),
            '',
        ], ';');

        fclose($output);
        exit;
    }

    /**
     * Extraction et structuration des factures non payées pour le tableau CNP.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function fetchData(PDO $pdo, array $params): array
    {
        $dateDebut = $params['date_debut'];
        $dateFin = $params['date_fin'];
        $selectedAgence = $params['agence_id'];
        $typeTransport = $params['type_transport'];
        $dateFilterType = $params['date_filter_type'];
        $search = $params['search'];

        // Colonne de filtrage date
        $dateCol = ($dateFilterType === 'colis') 
            ? "COALESCE(c.date_depart_prevue, DATE(c.created_at))" 
            : "DATE(f.date_emission)";

        $where = ["f.statut IN ('emise', 'partiellement_payee', 'en_retard')"];
        $bindings = [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ];

        $where[] = "{$dateCol} BETWEEN :date_debut AND :date_fin";

        // Filtre agence
        if ($selectedAgence !== 'all' && $selectedAgence !== '') {
            $agId = (int) $selectedAgence;
            $where[] = "(f.agence_id = :ag_id OR c.agence_depart_id = :ag_id OR c.agence_arrivee_id = :ag_id)";
            $bindings['ag_id'] = $agId;
        }

        // Filtre recherche
        if ($search !== '') {
            $where[] = "(c.numero_tracking LIKE :search OR f.numero_facture LIKE :search OR cli_exp.name LIKE :search OR cli_dest.name LIKE :search OR c.groupe_code LIKE :search)";
            $bindings['search'] = '%' . $search . '%';
        }

        // Filtre transport
        if ($typeTransport === 'export') {
            $where[] = "(c.type_expediteur LIKE '%export%' OR t.type_transport LIKE '%export%' OR t.code LIKE '%EXP%' OR c.trajet LIKE '%export%')";
        } elseif ($typeTransport === 'cargo') {
            $where[] = "(c.type_expediteur LIKE '%cargo%' OR t.type_transport LIKE '%cargo%' OR t.code LIKE '%CARGO%' OR c.trajet LIKE '%cargo%')";
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                f.id AS facture_id,
                f.numero_facture,
                f.statut AS facture_statut,
                f.montant_total,
                f.montant_encaisse,
                f.montant_restant,
                f.devise,
                f.taux_change,
                f.date_emission,
                c.id AS colis_id,
                c.numero_tracking AS code,
                c.poids_total,
                c.nombre_colis,
                c.montant_total_eur,
                c.valeur_declaree,
                c.groupe_code,
                c.trajet AS colis_trajet,
                c.trafic AS colis_trafic,
                c.type_expediteur,
                c.statut AS colis_statut,
                c.statut_depart,
                c.date_depart_prevue,
                c.created_at AS colis_date_creation,
                cli_exp.name AS expediteur_nom,
                cli_exp.phone AS expediteur_tel,
                cli_dest.name AS destinataire_nom,
                cli_dest.phone AS destinataire_tel,
                dep.id AS agence_depart_id,
                dep.name AS agence_depart_nom,
                arr.id AS agence_arrivee_id,
                arr.name AS agence_arrivee_nom,
                fact_ag.id AS agence_facture_id,
                fact_ag.name AS agence_facture_nom,
                exp.id AS expedition_id,
                exp.reference AS expedition_reference,
                t.code AS trajet_code,
                t.libelle AS trajet_libelle,
                t.type_transport AS trajet_type_transport
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            LEFT JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
            LEFT JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
            LEFT JOIN company_sites dep ON c.agence_depart_id = dep.id
            LEFT JOIN company_sites arr ON c.agence_arrivee_id = arr.id
            LEFT JOIN company_sites fact_ag ON f.agence_id = fact_ag.id
            LEFT JOIN lbp_expeditions exp ON c.expedition_id = exp.id
            LEFT JOIN trajets t ON c.trajet_id = t.id
            WHERE {$whereSql}
            ORDER BY 
                CASE 
                    WHEN c.groupe_code IS NOT NULL AND c.groupe_code != '' THEN c.groupe_code
                    WHEN exp.reference IS NOT NULL AND exp.reference != '' THEN exp.reference
                    ELSE 'ZZZ'
                END ASC,
                f.date_emission DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Structuration des groupes et sous-totaux
        $groupes = [];
        $colisExport = [];
        $sousTotauxGroupes = [];

        $totaux = [
            'count' => 0,
            'montant_eur' => 0.0,
            'montant_total' => 0.0,
            'montant_encaisse' => 0.0,
            'montant_restant' => 0.0,
        ];

        $totauxExport = [
            'count' => 0,
            'montant_eur' => 0.0,
            'montant_total' => 0.0,
            'montant_encaisse' => 0.0,
            'montant_restant' => 0.0,
        ];

        foreach ($rows as &$r) {
            // Groupe affiché : priorité au groupe_code saisi (ex: A1, A2), puis à l'expédition ERP, sinon 'Sans Groupe'
            $groupeDisplay = !empty($r['groupe_code'])
                ? trim($r['groupe_code'])
                : (!empty($r['expedition_reference']) ? trim($r['expedition_reference']) : 'Sans Groupe');

            $r['groupe_display'] = $groupeDisplay;

            // Date formatée
            $rawDate = !empty($r['date_emission']) ? $r['date_emission'] : $r['colis_date_creation'];
            $r['date_formatted'] = date('d/m/Y', strtotime($rawDate));

            // Agence d'affichage
            $r['agence_nom'] = $r['agence_facture_nom'] ?? $r['agence_depart_nom'] ?? 'N/A';

            // Détection Export
            $isExport = str_contains(strtolower(($r['type_expediteur'] ?? '') . ' ' . ($r['trajet_type_transport'] ?? '') . ' ' . ($r['trajet_code'] ?? '') . ' ' . ($r['colis_trajet'] ?? '')), 'export');
            $r['is_export'] = $isExport;

            // Libellé type
            $r['type_envoi_label'] = $isExport ? 'COLIS EXPORT' : ($r['trajet_libelle'] ?? $r['type_expediteur'] ?? 'Fret');

            // Conversion Euro / Montants
            $montantEur = 0.0;
            if (!empty($r['montant_total_eur']) && (float) $r['montant_total_eur'] > 0) {
                $montantEur = (float) $r['montant_total_eur'];
            } elseif ($r['devise'] === 'EUR') {
                $montantEur = (float) $r['montant_total'];
            } elseif (!empty($r['taux_change']) && (float) $r['taux_change'] > 0) {
                $montantEur = (float) $r['montant_total'] / (float) $r['taux_change'];
            }
            $r['montant_eur'] = $montantEur;

            // Statut Facture Badge
            $statutLabels = [
                'emise' => 'Non payée',
                'partiellement_payee' => 'Partielle',
                'en_retard' => 'En retard',
            ];
            $r['facture_statut_label'] = $statutLabels[$r['facture_statut']] ?? ucfirst($r['facture_statut']);

            // Totaux généraux
            $totaux['count']++;
            $totaux['montant_eur'] += $montantEur;
            $totaux['montant_total'] += (float) $r['montant_total'];
            $totaux['montant_encaisse'] += (float) $r['montant_encaisse'];
            $totaux['montant_restant'] += (float) $r['montant_restant'];

            // Regroupement par lot / groupe
            if (!isset($groupes[$groupeDisplay])) {
                $groupes[$groupeDisplay] = [];
                $sousTotauxGroupes[$groupeDisplay] = [
                    'count' => 0,
                    'montant_eur' => 0.0,
                    'montant_total' => 0.0,
                    'montant_encaisse' => 0.0,
                    'montant_restant' => 0.0,
                ];
            }
            $groupes[$groupeDisplay][] = $r;
            $sousTotauxGroupes[$groupeDisplay]['count']++;
            $sousTotauxGroupes[$groupeDisplay]['montant_eur'] += $montantEur;
            $sousTotauxGroupes[$groupeDisplay]['montant_total'] += (float) $r['montant_total'];
            $sousTotauxGroupes[$groupeDisplay]['montant_encaisse'] += (float) $r['montant_encaisse'];
            $sousTotauxGroupes[$groupeDisplay]['montant_restant'] += (float) $r['montant_restant'];

            // Lignes Export pour la section dédiée
            if ($isExport) {
                $colisExport[] = $r;
                $totauxExport['count']++;
                $totauxExport['montant_eur'] += $montantEur;
                $totauxExport['montant_total'] += (float) $r['montant_total'];
                $totauxExport['montant_encaisse'] += (float) $r['montant_encaisse'];
                $totauxExport['montant_restant'] += (float) $r['montant_restant'];
            }
        }
        unset($r);

        return [
            'rows' => $rows,
            'groupes' => $groupes,
            'colisExport' => $colisExport,
            'sousTotauxGroupes' => $sousTotauxGroupes,
            'totaux' => $totaux,
            'totauxExport' => $totauxExport,
        ];
    }
}
