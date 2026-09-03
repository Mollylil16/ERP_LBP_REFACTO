<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use App\Models\Finance\DemandeFonds;
use App\Models\Finance\ImputationFonds;
use PDO;

final class DemandeFondsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Génère une référence unique normée : D-MMAA-XXXX (ex: D-0926-10198).
     */
    public function generateNumeroDemande(): string
    {
        $prefix = 'D-' . date('my') . '-';
        $stmt = $this->pdo->prepare("SELECT numero_demande FROM lbp_demandes_fonds WHERE numero_demande LIKE :prefix ORDER BY id DESC LIMIT 1");
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if ($last && preg_match('/^D-\d{4}-(\d+)$/', (string) $last, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 10001;
        }

        return $prefix . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Liste paginée des demandes avec filtres multicritères.
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, DemandeFonds>, total: int, page: int, perPage: int, totalPages: int}
     */
    public function paginateDemandes(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params = [];

        // Filtre agence
        if (!empty($filters['agence_id']) && (int) $filters['agence_id'] > 0) {
            $conditions[] = 'df.agence_id = :agence_id';
            $params['agence_id'] = (int) $filters['agence_id'];
        }

        // Filtre statut
        if (!empty($filters['statut'])) {
            $conditions[] = 'df.statut = :statut';
            $params['statut'] = (string) $filters['statut'];
        }

        // Filtre cadre
        if (!empty($filters['cadre'])) {
            $conditions[] = 'df.cadre = :cadre';
            $params['cadre'] = (string) $filters['cadre'];
        }

        // Filtre période (Date début)
        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(df.created_at) >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }

        // Filtre période (Date fin)
        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(df.created_at) <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }

        // Filtre recherche mot-clé (numéro, motif, dossier, demandeur)
        if (!empty($filters['q'])) {
            $conditions[] = '(df.numero_demande LIKE :q OR df.motif LIKE :q OR df.dossier_num LIKE :q OR u_dem.full_name LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $whereSql = implode(' AND ', $conditions);

        // Count total
        $countSql = "
            SELECT COUNT(*) 
            FROM lbp_demandes_fonds df
            LEFT JOIN users u_dem ON df.demandeur_id = u_dem.id
            WHERE {$whereSql}
        ";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Items
        $sql = "
            SELECT 
                df.*,
                s.name AS agence_nom,
                u_dem.full_name AS demandeur_nom,
                u_val.full_name AS validateur_nom,
                u_cai.full_name AS caissiere_nom
            FROM lbp_demandes_fonds df
            LEFT JOIN company_sites s ON df.agence_id = s.id
            LEFT JOIN users u_dem ON df.demandeur_id = u_dem.id
            LEFT JOIN users u_val ON df.validateur_id = u_val.id
            LEFT JOIN users u_cai ON df.caissiere_id = u_cai.id
            WHERE {$whereSql}
            ORDER BY df.created_at DESC, df.id DESC
            LIMIT :offset, :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = array_map(fn(array $row) => $this->mapToDemande($row), $rows);

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Trouver une demande par son identifiant ID avec hydratation complète.
     */
    public function findDemandeById(int $id): ?DemandeFonds
    {
        $sql = "
            SELECT 
                df.*,
                s.name AS agence_nom,
                u_dem.full_name AS demandeur_nom,
                u_val.full_name AS validateur_nom,
                u_cai.full_name AS caissiere_nom
            FROM lbp_demandes_fonds df
            LEFT JOIN company_sites s ON df.agence_id = s.id
            LEFT JOIN users u_dem ON df.demandeur_id = u_dem.id
            LEFT JOIN users u_val ON df.validateur_id = u_val.id
            LEFT JOIN users u_cai ON df.caissiere_id = u_cai.id
            WHERE df.id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $demande = $this->mapToDemande($row);

        // Charger l'imputation si existante
        $stmtImp = $this->pdo->prepare("
            SELECT i.*, u.full_name AS impute_par_nom 
            FROM lbp_imputations_fonds i 
            LEFT JOIN users u ON i.impute_par_id = u.id 
            WHERE i.demande_fonds_id = :demande_id 
            ORDER BY i.id DESC LIMIT 1
        ");
        $stmtImp->execute(['demande_id' => $id]);
        $impRow = $stmtImp->fetch(PDO::FETCH_ASSOC);
        if ($impRow) {
            $demande->imputation = new ImputationFonds(
                id: (int) $impRow['id'],
                demandeFondsId: (int) $impRow['demande_fonds_id'],
                montantEngage: (float) $impRow['montant_engage'],
                montantReelDepense: (float) $impRow['montant_reel_depense'],
                montantReliquatRestitue: (float) $impRow['montant_reliquat_restitue'],
                piecesJustificatives: $impRow['pieces_justificatives'] ?? null,
                commentaires: $impRow['commentaires'] ?? null,
                imputeParId: (int) $impRow['impute_par_id'],
                statutImputation: (string) ($impRow['statut_imputation'] ?? 'conforme'),
                dateImputation: $impRow['date_imputation'] ?? null,
                imputeParNom: $impRow['impute_par_nom'] ?? null
            );
        }

        return $demande;
    }

    /**
     * Création d'une nouvelle demande de fonds.
     */
    public function createDemande(array $data): int
    {
        $numero = $this->generateNumeroDemande();

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_demandes_fonds (
                numero_demande, agence_id, cadre, dossier_num, motif, 
                montant, devise, demandeur_id, statut, created_at
            ) VALUES (
                :numero_demande, :agence_id, :cadre, :dossier_num, :motif, 
                :montant, :devise, :demandeur_id, 'en_attente', NOW()
            )
        ");

        $stmt->execute([
            'numero_demande' => $numero,
            'agence_id'     => (int) ($data['agence_id'] ?? 1),
            'cadre'         => (string) ($data['cadre'] ?? 'traitement_dossier'),
            'dossier_num'   => !empty($data['dossier_num']) ? trim((string) $data['dossier_num']) : null,
            'motif'         => trim((string) ($data['motif'] ?? '')),
            'montant'       => (float) ($data['montant'] ?? 0.0),
            'devise'        => (string) ($data['devise'] ?? 'XOF'),
            'demandeur_id'  => (int) ($data['demandeur_id'] ?? 0),
        ]);

        $demandeId = (int) $this->pdo->lastInsertId();

        $this->logHistorique(
            demandeId: $demandeId,
            userId: (int) ($data['demandeur_id'] ?? 0),
            action: 'CREATION',
            statutAvant: null,
            statutApres: 'en_attente',
            commentaire: 'Création de la demande de fonds ' . $numero . ' pour un montant de ' . number_format((float) ($data['montant'] ?? 0), 0, ',', ' ') . ' ' . ($data['devise'] ?? 'XOF')
        );

        return $demandeId;
    }

    /**
     * Modification d'une demande de fonds (possible uniquement si en attente).
     */
    public function updateDemande(int $id, array $data, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_demandes_fonds 
            SET agence_id = :agence_id,
                cadre = :cadre,
                dossier_num = :dossier_num,
                motif = :motif,
                montant = :montant,
                devise = :devise,
                updated_at = NOW()
            WHERE id = :id AND statut = 'en_attente'
        ");

        $res = $stmt->execute([
            'id'          => $id,
            'agence_id'   => (int) ($data['agence_id'] ?? 1),
            'cadre'       => (string) ($data['cadre'] ?? 'traitement_dossier'),
            'dossier_num' => !empty($data['dossier_num']) ? trim((string) $data['dossier_num']) : null,
            'motif'       => trim((string) ($data['motif'] ?? '')),
            'montant'     => (float) ($data['montant'] ?? 0.0),
            'devise'      => (string) ($data['devise'] ?? 'XOF'),
        ]);

        if ($res && $stmt->rowCount() > 0) {
            $this->logHistorique(
                demandeId: $id,
                userId: $userId,
                action: 'MODIFICATION',
                statutAvant: 'en_attente',
                statutApres: 'en_attente',
                commentaire: 'Mise à jour des informations de la demande (Montant: ' . number_format((float) ($data['montant'] ?? 0), 0, ',', ' ') . ' ' . ($data['devise'] ?? 'XOF') . ')'
            );
            return true;
        }

        return false;
    }

    /**
     * Suppression d'une demande (possible uniquement si en attente).
     */
    public function deleteDemande(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM lbp_demandes_fonds WHERE id = :id AND statut = 'en_attente'");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Validation officielle de la demande par l'Assistante DG, le DG ou l'Admin.
     */
    public function validerDemande(int $id, int $validateurId, ?string $commentaire = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_demandes_fonds 
            SET statut = 'validee',
                validateur_id = :validateur_id,
                date_validation = NOW(),
                updated_at = NOW()
            WHERE id = :id AND statut = 'en_attente'
        ");
        $res = $stmt->execute([
            'id'            => $id,
            'validateur_id' => $validateurId,
        ]);

        if ($res && $stmt->rowCount() > 0) {
            $this->logHistorique(
                demandeId: $id,
                userId: $validateurId,
                action: 'VALIDATION',
                statutAvant: 'en_attente',
                statutApres: 'validee',
                commentaire: $commentaire ?? 'Validation et autorisation de décaissement par la Direction.'
            );
            return true;
        }

        return false;
    }

    /**
     * Rejet officiel de la demande par l'Assistante DG, le DG ou l'Admin.
     */
    public function rejeterDemande(int $id, int $validateurId, string $motifRejet): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_demandes_fonds 
            SET statut = 'rejetee',
                validateur_id = :validateur_id,
                date_validation = NOW(),
                motif_rejet = :motif_rejet,
                updated_at = NOW()
            WHERE id = :id AND statut = 'en_attente'
        ");
        $res = $stmt->execute([
            'id'            => $id,
            'validateur_id' => $validateurId,
            'motif_rejet'   => $motifRejet,
        ]);

        if ($res && $stmt->rowCount() > 0) {
            $this->logHistorique(
                demandeId: $id,
                userId: $validateurId,
                action: 'REJET',
                statutAvant: 'en_attente',
                statutApres: 'rejetee',
                commentaire: 'Rejet de la demande : ' . $motifRejet
            );
            return true;
        }

        return false;
    }

    /**
     * Prise en compte et décaissement physique en Caisse.
     */
    public function decaisserDemande(int $id, int $caissiereId, string $modePaiement = 'Espèces'): bool
    {
        $refBon = 'BON-CS-' . date('ymd') . '-' . $id;

        $stmt = $this->pdo->prepare("
            UPDATE lbp_demandes_fonds 
            SET statut = 'decaissee',
                caissiere_id = :caissiere_id,
                date_decaissement = NOW(),
                mode_paiement = :mode_paiement,
                reference_bon_caisse = :ref_bon,
                updated_at = NOW()
            WHERE id = :id AND statut = 'validee'
        ");
        $res = $stmt->execute([
            'id'            => $id,
            'caissiere_id'  => $caissiereId,
            'mode_paiement' => $modePaiement,
            'ref_bon'       => $refBon,
        ]);

        if ($res && $stmt->rowCount() > 0) {
            $this->logHistorique(
                demandeId: $id,
                userId: $caissiereId,
                action: 'DECAISSEMENT',
                statutAvant: 'validee',
                statutApres: 'decaissee',
                commentaire: "Prise en compte et décaissement en caisse ({$modePaiement}). Réf Bon: {$refBon}"
            );
            return true;
        }

        return false;
    }

    /**
     * Imputation comptable et régularisation des reliquats.
     */
    public function imputerDemande(int $id, array $imputationData, int $userId): bool
    {
        $demande = $this->findDemandeById($id);
        if (!$demande || $demande->statut !== 'decaissee') {
            return false;
        }

        $montantEngage = $demande->montant;
        $montantReel = (float) ($imputationData['montant_reel_depense'] ?? $montantEngage);
        $reliquat = max(0.0, $montantEngage - $montantReel);

        $statutImp = 'conforme';
        if ($montantReel < $montantEngage) {
            $statutImp = 'reliquat_encaisse';
        } elseif ($montantReel > $montantEngage) {
            $statutImp = 'ecart_constate';
        }

        // 1. Insertion imputation
        $stmtImp = $this->pdo->prepare("
            INSERT INTO lbp_imputations_fonds (
                demande_fonds_id, montant_engage, montant_reel_depense,
                montant_reliquat_restitue, pieces_justificatives, commentaires,
                impute_par_id, statut_imputation, date_imputation
            ) VALUES (
                :demande_id, :montant_engage, :montant_reel,
                :reliquat, :pieces, :commentaires,
                :impute_par_id, :statut_imp, NOW()
            )
        ");

        $stmtImp->execute([
            'demande_id'     => $id,
            'montant_engage' => $montantEngage,
            'montant_reel'   => $montantReel,
            'reliquat'       => $reliquat,
            'pieces'         => $imputationData['pieces_justificatives'] ?? null,
            'commentaires'   => $imputationData['commentaires'] ?? null,
            'impute_par_id'  => $userId,
            'statut_imp'     => $statutImp,
        ]);

        // 2. Mise à jour statut demande
        $stmtUp = $this->pdo->prepare("UPDATE lbp_demandes_fonds SET statut = 'imputee', updated_at = NOW() WHERE id = :id");
        $stmtUp->execute(['id' => $id]);

        // 3. Journalisation
        $this->logHistorique(
            demandeId: $id,
            userId: $userId,
            action: 'IMPUTATION',
            statutAvant: 'decaissee',
            statutApres: 'imputee',
            commentaire: "Imputation validée. Montant réel dépensé: " . number_format($montantReel, 0, ',', ' ') . " {$demande->devise}. Reliquat restitué en caisse: " . number_format($reliquat, 0, ',', ' ') . " {$demande->devise}."
        );

        return true;
    }

    /**
     * Récupère le journal d'audit et la chronologie d'une demande.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistorique(int $demandeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                h.*,
                u.full_name AS user_nom,
                u.email AS user_email
            FROM lbp_demandes_fonds_historique h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.demande_fonds_id = :demande_id
            ORDER BY h.created_at ASC, h.id ASC
        ");
        $stmt->execute(['demande_id' => $demandeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enregistre un événement dans l'historique d'audit.
     */
    public function logHistorique(
        int $demandeId,
        int $userId,
        string $action,
        ?string $statutAvant,
        ?string $statutApres,
        ?string $commentaire = null
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_demandes_fonds_historique (
                    demande_fonds_id, user_id, action, statut_avant, statut_apres, commentaire, created_at
                ) VALUES (
                    :demande_id, :user_id, :action, :statut_avant, :statut_apres, :commentaire, NOW()
                )
            ");
            $stmt->execute([
                'demande_id'   => $demandeId,
                'user_id'      => $userId,
                'action'       => $action,
                'statut_avant' => $statutAvant,
                'statut_apres' => $statutApres,
                'commentaire'  => $commentaire,
            ]);
        } catch (\Throwable $e) {
            error_log('[DemandeFondsRepository] logHistorique error: ' . $e->getMessage());
        }
    }

    /**
     * Récupère la liste des agences actives pour les sélecteurs.
     *
     * @return array<int, array{id: int, name: string, code: string}>
     */
    public function getAgences(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, code FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Récupère les dossiers de transit récents pour l'autocomplétion / suggestions.
     *
     * @return array<int, string>
     */
    public function getDossiersTransitRecents(int $limit = 40): array
    {
        $dossiers = [];

        // 1. Depuis les dossiers existants dans lbp_demandes_fonds
        try {
            $stmt1 = $this->pdo->query("SELECT DISTINCT dossier_num FROM lbp_demandes_fonds WHERE dossier_num IS NOT NULL AND dossier_num != '' ORDER BY id DESC LIMIT {$limit}");
            if ($stmt1) {
                while ($val = $stmt1->fetchColumn()) {
                    if (!empty($val)) $dossiers[] = (string) $val;
                }
            }
        } catch (\Throwable $e) {}

        // 2. Depuis les numéros de tracking colis
        try {
            $stmt2 = $this->pdo->query("SELECT numero_tracking FROM lbp_colis WHERE numero_tracking IS NOT NULL ORDER BY id DESC LIMIT 20");
            if ($stmt2) {
                while ($val = $stmt2->fetchColumn()) {
                    if (!empty($val)) $dossiers[] = (string) $val;
                }
            }
        } catch (\Throwable $e) {}

        return array_values(array_unique(array_filter($dossiers)));
    }

    /**
     * Récupère les compteurs globaux pour les indicateurs (KPIs).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getStats(array $filters = []): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['agence_id']) && (int) $filters['agence_id'] > 0) {
            $conditions[] = 'agence_id = :agence_id';
            $params['agence_id'] = (int) $filters['agence_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(created_at) >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(created_at) <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = "
            SELECT 
                COUNT(*) AS total_demandes,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS total_en_attente,
                SUM(CASE WHEN statut = 'validee' THEN 1 ELSE 0 END) AS total_validees,
                SUM(CASE WHEN statut = 'decaissee' THEN 1 ELSE 0 END) AS total_decaissees,
                SUM(CASE WHEN statut = 'imputee' THEN 1 ELSE 0 END) AS total_imputees,
                SUM(CASE WHEN statut = 'rejetee' THEN 1 ELSE 0 END) AS total_rejetees,
                COALESCE(SUM(montant), 0) AS montant_total_demande,
                COALESCE(SUM(CASE WHEN statut IN ('decaissee', 'imputee') THEN montant ELSE 0 END), 0) AS montant_total_decaisse
            FROM lbp_demandes_fonds
            WHERE {$whereSql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_demandes' => 0,
            'total_en_attente' => 0,
            'total_validees' => 0,
            'total_decaissees' => 0,
            'total_imputees' => 0,
            'total_rejetees' => 0,
            'montant_total_demande' => 0,
            'montant_total_decaisse' => 0,
        ];
    }

    private function mapToDemande(array $row): DemandeFonds
    {
        return new DemandeFonds(
            id: isset($row['id']) ? (int) $row['id'] : null,
            numeroDemande: (string) ($row['numero_demande'] ?? ''),
            agenceId: (int) ($row['agence_id'] ?? 1),
            cadre: (string) ($row['cadre'] ?? 'traitement_dossier'),
            dossierNum: !empty($row['dossier_num']) ? (string) $row['dossier_num'] : null,
            motif: (string) ($row['motif'] ?? ''),
            montant: (float) ($row['montant'] ?? 0.0),
            devise: (string) ($row['devise'] ?? 'XOF'),
            demandeurId: (int) ($row['demandeur_id'] ?? 0),
            statut: (string) ($row['statut'] ?? 'en_attente'),
            motifRejet: $row['motif_rejet'] ?? null,
            validateurId: isset($row['validateur_id']) ? (int) $row['validateur_id'] : null,
            dateValidation: $row['date_validation'] ?? null,
            caissiereId: isset($row['caissiere_id']) ? (int) $row['caissiere_id'] : null,
            dateDecaissement: $row['date_decaissement'] ?? null,
            modePaiement: (string) ($row['mode_paiement'] ?? 'Espèces'),
            referenceBonCaisse: $row['reference_bon_caisse'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            agenceNom: $row['agence_nom'] ?? null,
            demandeurNom: $row['demandeur_nom'] ?? null,
            validateurNom: $row['validateur_nom'] ?? null,
            caissiereNom: $row['caissiere_nom'] ?? null
        );
    }
}
