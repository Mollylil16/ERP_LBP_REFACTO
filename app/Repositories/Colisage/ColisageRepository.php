<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;

class ColisageRepository
{
    public function __construct(private PDO $pdo) {}

    // ==========================================
    // CLIENTS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getClients(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM lbp_clients ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findClientById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_clients WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function createClient(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_clients (name, phone, email, address, type, created_at)
            VALUES (:name, :phone, :email, :address, :type, NOW())
        ");
        $stmt->execute([
            'name' => trim((string) ($data['name'] ?? '')),
            'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
            'email' => isset($data['email']) ? trim((string) $data['email']) : null,
            'address' => isset($data['address']) ? trim((string) $data['address']) : null,
            'type' => trim((string) ($data['type'] ?? 'standard')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ==========================================
    // COLIS / PARCELS
    // ==========================================

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getParcels(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT c.*,
                   exp.name AS expediteur_name,
                   dest.name AS destinataire_name,
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            JOIN lbp_clients exp ON c.expediteur_id = exp.id
            JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (c.numero_tracking LIKE :q OR exp.name LIKE :q OR dest.name LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = :statut";
            $params['statut'] = $filters['statut'];
        }
        if (!empty($filters['type_expediteur'])) {
            $sql .= " AND c.type_expediteur = :type_expediteur";
            $params['type_expediteur'] = $filters['type_expediteur'];
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $filters */
    public function getParcelsCount(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM lbp_colis c
            JOIN lbp_clients exp ON c.expediteur_id = exp.id
            JOIN lbp_clients dest ON c.destinataire_id = dest.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (c.numero_tracking LIKE :q OR exp.name LIKE :q OR dest.name LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = :statut";
            $params['statut'] = $filters['statut'];
        }
        if (!empty($filters['type_expediteur'])) {
            $sql .= " AND c.type_expediteur = :type_expediteur";
            $params['type_expediteur'] = $filters['type_expediteur'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findParcelById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   exp.name AS expediteur_name, exp.phone AS expediteur_phone, exp.address AS expediteur_address, exp.email AS expediteur_email,
                   dest.name AS destinataire_name, dest.phone AS destinataire_phone, dest.address AS destinataire_address, dest.email AS destinataire_email,
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            JOIN lbp_clients exp ON c.expediteur_id = exp.id
            JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findParcelByTracking(string $tracking): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   exp.name AS expediteur_name,
                   dest.name AS destinataire_name,
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            JOIN lbp_clients exp ON c.expediteur_id = exp.id
            JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE c.numero_tracking = :tracking
        ");
        $stmt->execute(['tracking' => $tracking]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function createParcel(array $data): int
    {
        // Calculate EUR conversion — dynamic rate from company_settings
        $montantTotal = (float) ($data['montant_total'] ?? $data['valeur_declaree'] ?? 0.0);
        $devise = trim((string) ($data['devise'] ?? 'XOF'));
        $montantEur = null;
        if ($devise === 'XOF' && $montantTotal > 0) {
            $tauxChangeEur = 655.957; // fallback
            try {
                $rateStmt = $this->pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
                if ($rateStmt) {
                    $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rateRow && is_numeric($rateRow['setting_value'])) {
                        $tauxChangeEur = (float) $rateRow['setting_value'];
                    }
                }
            } catch (\Exception $e) {}
            $montantEur = round($montantTotal / $tauxChangeEur, 2);
        } elseif ($devise === 'EUR' && $montantTotal > 0) {
            $montantEur = $montantTotal;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_colis (
                numero_tracking, expediteur_id, destinataire_id, poids_total, nombre_colis,
                valeur_declaree, montant_total, montant_total_eur, devise,
                agence_depart_id, agence_arrivee_id,
                statut, type_expediteur, trafic, created_at
            ) VALUES (
                :numero_tracking, :expediteur_id, :destinataire_id, :poids_total, :nombre_colis,
                :valeur_declaree, :montant_total, :montant_total_eur, :devise,
                :agence_depart_id, :agence_arrivee_id,
                'RÉCEPTIONNÉ', :type_expediteur, :trafic, NOW()
            )
        ");
        $stmt->execute([
            'numero_tracking' => trim((string) $data['numero_tracking']),
            'expediteur_id' => (int) $data['expediteur_id'],
            'destinataire_id' => (int) $data['destinataire_id'],
            'poids_total' => (float) ($data['poids_total'] ?? 0.0),
            'nombre_colis' => (int) ($data['nombre_colis'] ?? 1),
            'valeur_declaree' => (float) ($data['valeur_declaree'] ?? 0.0),
            'montant_total' => $montantTotal,
            'montant_total_eur' => $montantEur,
            'devise' => $devise,
            'agence_depart_id' => isset($data['agence_depart_id']) ? (int) $data['agence_depart_id'] : null,
            'agence_arrivee_id' => isset($data['agence_arrivee_id']) ? (int) $data['agence_arrivee_id'] : null,
            'type_expediteur' => trim((string) $data['type_expediteur']),
            'trafic' => trim((string) ($data['trafic'] ?? '')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateParcelStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE lbp_colis SET statut = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /** @param array<string, mixed> $data */
    public function recordWithdrawal(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET statut = 'RETIRÉ',
                recup_nom = :recup_nom,
                recup_cni = :recup_cni,
                recup_telephone = :recup_telephone,
                frais_gardiennage_appliques = :frais_gardiennage,
                recup_date_heure = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'recup_nom' => trim((string) ($data['recup_nom'] ?? '')),
            'recup_cni' => trim((string) ($data['recup_cni'] ?? '')),
            'recup_telephone' => trim((string) ($data['recup_telephone'] ?? '')),
            'frais_gardiennage' => (float) ($data['frais_gardiennage_appliques'] ?? 0.0),
        ]);
    }

    // ==========================================
    // MARCHANDISES
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getMarchandisesForParcel(int $parcelId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_marchandises WHERE colis_id = :colis_id");
        $stmt->execute(['colis_id' => $parcelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createMarchandise(array $data): void
    {
        $poidsUnitaire = (float) ($data['poids_unitaire'] ?? 0.0);
        $prixKg = (float) ($data['prix_kg'] ?? 0.0);
        $qte = (int) ($data['quantite'] ?? 1);
        $totalLigne = $prixKg > 0 ? round($prixKg * $poidsUnitaire * $qte, 2) : 0.0;

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_marchandises (
                colis_id, description, emballage, quantite, nbre_colis,
                qte_emballage, poids_unitaire, prix_kg, total_ligne, created_at
            ) VALUES (
                :colis_id, :description, :emballage, :quantite, :nbre_colis,
                :qte_emballage, :poids_unitaire, :prix_kg, :total_ligne, NOW()
            )
        ");
        $stmt->execute([
            'colis_id' => (int) $data['colis_id'],
            'description' => trim((string) $data['description']),
            'emballage' => isset($data['emballage']) ? trim((string) $data['emballage']) : null,
            'quantite' => $qte,
            'nbre_colis' => (int) ($data['nbre_colis'] ?? 1),
            'qte_emballage' => (int) ($data['qte_emballage'] ?? 1),
            'poids_unitaire' => $poidsUnitaire,
            'prix_kg' => $prixKg,
            'total_ligne' => $totalLigne,
        ]);
    }



    // ==========================================
    // LIVREURS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getLivreurs(): array
    {
        $stmt = $this->pdo->query("
            SELECT l.*, u.full_name AS user_name
            FROM lbp_livreurs l
            JOIN users u ON l.user_id = u.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ==========================================
    // INVENTAIRES
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getInventories(): array
    {
        $stmt = $this->pdo->query("
            SELECT i.*, s.name AS agence_name, u.full_name AS creator_name
            FROM lbp_inventaires i
            JOIN company_sites s ON i.agence_id = s.id
            LEFT JOIN users u ON i.cree_par = u.id
            ORDER BY i.date_inventaire DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createInventory(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_inventaires (agence_id, date_inventaire, statut, commentaires, cree_par)
            VALUES (:agence_id, NOW(), 'BROUILLON', :commentaires, :cree_par)
        ");
        $stmt->execute([
            'agence_id' => (int) $data['agence_id'],
            'commentaires' => isset($data['commentaires']) ? trim((string) $data['commentaires']) : null,
            'cree_par' => isset($data['cree_par']) ? (int) $data['cree_par'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function addInventoryLine(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_inventaire_lignes (inventaire_id, colis_id, etat, commentaires)
            VALUES (:inventaire_id, :colis_id, :etat, :commentaires)
        ");
        $stmt->execute([
            'inventaire_id' => (int) $data['inventaire_id'],
            'colis_id' => (int) $data['colis_id'],
            'etat' => trim((string) ($data['etat'] ?? 'PRÉSENT')),
            'commentaires' => isset($data['commentaires']) ? trim((string) $data['commentaires']) : null,
        ]);
    }

    // ==========================================
    // PRODUCTS CATALOG
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getProducts(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM lbp_produits WHERE actif = 1 ORDER BY nom ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findProductByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_produits WHERE nom = :nom LIMIT 1");
        $stmt->execute(['nom' => trim(strtoupper($name))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string, mixed> $data */
    public function createProduct(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_produits (nom, categorie, nature, prix_unitaire, prix_forfaitaire, description, actif, unite, created_at)
            VALUES (:nom, :categorie, :nature, :prix_unitaire, :prix_forfaitaire, :description, 1, :unite, NOW())
        ");
        $stmt->execute([
            'nom' => trim(strtoupper((string) $data['nom'])),
            'categorie' => isset($data['categorie']) ? trim((string) $data['categorie']) : 'DIVERS',
            'nature' => isset($data['nature']) ? trim((string) $data['nature']) : 'PRIX_UNITAIRE',
            'prix_unitaire' => (float) ($data['prix_unitaire'] ?? 0.0),
            'prix_forfaitaire' => isset($data['prix_forfaitaire']) ? (float) $data['prix_forfaitaire'] : null,
            'description' => isset($data['description']) ? trim((string) $data['description']) : 'Ajouté à la volée',
            'unite' => trim((string) ($data['unite'] ?? 'kg')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ==========================================
    // GROUPAGE / EXPEDITIONS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function getExpeditions(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.*, 
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_expeditions e
            JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
            JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
            ORDER BY e.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findExpeditionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, 
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_expeditions e
            JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
            JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
            WHERE e.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getParcelsForExpedition(int $expeditionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, 
                   cli_exp.name AS expediteur_name,
                   cli_dest.name AS destinataire_name
            FROM lbp_colis c
            JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
            JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
            WHERE c.expedition_id = :expedition_id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['expedition_id' => $expeditionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getParcelsAvailableForGroupage(int $agencyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, 
                   cli_exp.name AS expediteur_name,
                   cli_dest.name AS destinataire_name
            FROM lbp_colis c
            JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
            JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
            WHERE c.agence_depart_id = :agency_id 
              AND c.statut IN ('RÉCEPTIONNÉ', 'EN_PRÉPARATION')
              AND c.expedition_id IS NULL
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['agency_id' => $agencyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateExpeditionStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_expeditions
            SET statut = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id, 'status' => trim(strtoupper($status))]);
    }

    public function updateParcelsStatusForExpedition(int $expeditionId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET statut = :status, updated_at = NOW()
            WHERE expedition_id = :expedition_id
        ");
        $stmt->execute(['expedition_id' => $expeditionId, 'status' => trim(strtoupper($status))]);
    }

    /** @param array<string, mixed> $data */
    public function createExpedition(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_expeditions (reference, type_transport, agence_depart_id, agence_arrivee_id, date_depart_prevue, date_arrivee_estimee, statut, created_at)
            VALUES (:reference, :type_transport, :agence_depart_id, :agence_arrivee_id, :date_depart_prevue, :date_arrivee_estimee, 'BROUILLON', NOW())
        ");
        $stmt->execute([
            'reference' => trim((string) $data['reference']),
            'type_transport' => trim((string) ($data['type_transport'] ?? 'AÉRIEN')),
            'agence_depart_id' => (int) $data['agence_depart_id'],
            'agence_arrivee_id' => (int) $data['agence_arrivee_id'],
            'date_depart_prevue' => !empty($data['date_depart_prevue']) ? $data['date_depart_prevue'] : null,
            'date_arrivee_estimee' => !empty($data['date_arrivee_estimee']) ? $data['date_arrivee_estimee'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function assignParcelToExpedition(int $parcelId, int $expeditionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET expedition_id = :expedition_id, statut = 'EN_PRÉPARATION', updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $parcelId,
            'expedition_id' => $expeditionId,
        ]);
    }

    public function countParcelsWithTrackingPrefix(string $prefix): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM lbp_colis WHERE numero_tracking LIKE :prefix");
        $stmt->execute(['prefix' => $prefix . '%']);
        return (int) $stmt->fetchColumn();
    }

    public function getProductNameById(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT nom FROM lbp_produits WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $name = $stmt->fetchColumn();
        return $name ? (string) $name : null;
    }

    public function getAgencyNameById(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $name = $stmt->fetchColumn();
        return $name ? (string) $name : null;
    }
}
