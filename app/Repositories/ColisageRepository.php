<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Database;
use PDO;

/**
 * Repository pour le module Colisage & Logistique Fret.
 * Gère : Colis, Marchandises, Expéditions, Tracking GPS, Inventaires.
 */
final class ColisageRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────
    //  COLIS
    // ─────────────────────────────────────────────

    public function getAllColis(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(c.tracking_number LIKE :search OR s.name LIKE :search OR r.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['agency_id'])) {
            $where[] = '(c.departure_agency_id = :agency_id OR c.arrival_agency_id = :agency_id)';
            $params['agency_id'] = $filters['agency_id'];
        }

        $sql = "
            SELECT c.*,
                   s.name AS sender_name,
                   r.name AS receiver_name,
                   da.name AS departure_agency,
                   aa.name AS arrival_agency
            FROM lbp_colis c
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            LEFT JOIN company_sites da ON da.id = c.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = c.arrival_agency_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   s.name AS sender_name, s.phone AS sender_phone, s.email AS sender_email,
                   r.name AS receiver_name, r.phone AS receiver_phone, r.email AS receiver_email,
                   da.name AS departure_agency,
                   aa.name AS arrival_agency
            FROM lbp_colis c
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            LEFT JOIN company_sites da ON da.id = c.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = c.arrival_agency_id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByTracking(string $trackingNumber): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   s.name AS sender_name, s.phone AS sender_phone,
                   r.name AS receiver_name, r.phone AS receiver_phone, r.email AS receiver_email,
                   da.name AS departure_agency,
                   aa.name AS arrival_agency
            FROM lbp_colis c
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            LEFT JOIN company_sites da ON da.id = c.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = c.arrival_agency_id
            WHERE c.tracking_number = :tracking
        ");
        $stmt->execute(['tracking' => $trackingNumber]);
        return $stmt->fetch() ?: null;
    }

    public function getMarchandises(int $colisId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_marchandises WHERE colis_id = :colis_id ORDER BY id");
        $stmt->execute(['colis_id' => $colisId]);
        return $stmt->fetchAll() ?: [];
    }

    public function createColis(array $data): int
    {
        $trackingNumber = $this->generateTrackingNumber();

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_colis (
                tracking_number, sender_id, receiver_id,
                departure_agency_id, arrival_agency_id,
                total_weight, declared_value, total_price, currency,
                description, notes, status
            ) VALUES (
                :tracking_number, :sender_id, :receiver_id,
                :departure_agency_id, :arrival_agency_id,
                :total_weight, :declared_value, :total_price, :currency,
                :description, :notes, 'RECEPTIONNE'
            )
        ");
        $stmt->execute([
            'tracking_number' => $trackingNumber,
            'sender_id' => $data['sender_id'],
            'receiver_id' => $data['receiver_id'],
            'departure_agency_id' => $data['departure_agency_id'],
            'arrival_agency_id' => $data['arrival_agency_id'],
            'total_weight' => $data['total_weight'] ?? 0,
            'declared_value' => $data['declared_value'] ?? 0,
            'total_price' => $data['total_price'] ?? 0,
            'currency' => $data['currency'] ?? 'XOF',
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $colisId = (int) $this->pdo->lastInsertId();

        // Créer automatiquement la facture client associée
        $stmtInvoice = $this->pdo->prepare("
            INSERT INTO invoices (
                reference, client_id, site_id, type, status,
                amount_ht, amount_ttc, currency, due_date
            ) VALUES (
                :reference, :client_id, :site_id, 'invoice', 'draft',
                :amount_ht, :amount_ttc, :currency, DATE_ADD(NOW(), INTERVAL 3 DAY)
            )
        ");
        $stmtInvoice->execute([
            'reference' => $trackingNumber,
            'client_id' => $data['sender_id'],
            'site_id' => $data['departure_agency_id'],
            'amount_ht' => ($data['total_price'] ?? 0) / 1.18,
            'amount_ttc' => $data['total_price'] ?? 0,
            'currency' => $data['currency'] ?? 'XOF',
        ]);

        return $colisId;
    }

    public function addMarchandise(int $colisId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_marchandises (colis_id, description, quantity, unit_weight)
            VALUES (:colis_id, :description, :quantity, :unit_weight)
        ");
        $stmt->execute([
            'colis_id' => $colisId,
            'description' => $data['description'],
            'quantity' => (int) ($data['quantity'] ?? 1),
            'unit_weight' => (float) ($data['unit_weight'] ?? 0),
        ]);
    }

    public function updateStatus(int $colisId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE lbp_colis SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $colisId]);
    }

    public function marquerRetire(int $colisId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis SET
                status = 'RETIRE',
                retrieval_name = :retrieval_name,
                retrieval_cni = :retrieval_cni,
                retrieval_phone = :retrieval_phone,
                retrieved_at = NOW(),
                retrieved_by = :retrieved_by,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'retrieval_name' => $data['retrieval_name'],
            'retrieval_cni' => $data['retrieval_cni'],
            'retrieval_phone' => $data['retrieval_phone'],
            'retrieved_by' => $data['retrieved_by'],
            'id' => $colisId,
        ]);
    }

    public function countByStatus(): array
    {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as total FROM lbp_colis GROUP BY status");
        $rows = $stmt->fetchAll() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    // ─────────────────────────────────────────────
    //  EXPÉDITIONS
    // ─────────────────────────────────────────────

    public function getAllExpeditions(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['transport_type'])) {
            $where[] = 'e.transport_type = :transport_type';
            $params['transport_type'] = $filters['transport_type'];
        }

        $stmt = $this->pdo->prepare("
            SELECT e.*,
                   da.name AS departure_agency,
                   aa.name AS arrival_agency,
                   COUNT(ce.colis_id) AS nb_colis
            FROM lbp_expeditions e
            LEFT JOIN company_sites da ON da.id = e.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = e.arrival_agency_id
            LEFT JOIN lbp_colis_expeditions ce ON ce.expedition_id = e.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY e.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findExpeditionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*,
                   da.name AS departure_agency,
                   aa.name AS arrival_agency
            FROM lbp_expeditions e
            LEFT JOIN company_sites da ON da.id = e.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = e.arrival_agency_id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getColisOfExpedition(int $expeditionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s.name AS sender_name, r.name AS receiver_name,
                   ce.added_at
            FROM lbp_colis_expeditions ce
            INNER JOIN lbp_colis c ON c.id = ce.colis_id
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            WHERE ce.expedition_id = :expedition_id
            ORDER BY ce.added_at ASC
        ");
        $stmt->execute(['expedition_id' => $expeditionId]);
        return $stmt->fetchAll() ?: [];
    }

    public function createExpedition(array $data): int
    {
        $reference = 'EXP-' . strtoupper($data['transport_type'][0]) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_expeditions (
                reference, transport_type,
                departure_agency_id, arrival_agency_id,
                departure_date, estimated_arrival_date,
                driver_user_id, notes, status
            ) VALUES (
                :reference, :transport_type,
                :departure_agency_id, :arrival_agency_id,
                :departure_date, :estimated_arrival_date,
                :driver_user_id, :notes, 'PLANIFIE'
            )
        ");
        $stmt->execute([
            'reference' => $reference,
            'transport_type' => $data['transport_type'],
            'departure_agency_id' => $data['departure_agency_id'],
            'arrival_agency_id' => $data['arrival_agency_id'],
            'departure_date' => $data['departure_date'] ?: null,
            'estimated_arrival_date' => $data['estimated_arrival_date'] ?: null,
            'driver_user_id' => $data['driver_user_id'] ?: null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function assignColisToExpedition(int $expeditionId, int $colisId): bool
    {
        // Vérifier que le colis n'est pas déjà dans une expédition
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM lbp_colis_expeditions WHERE colis_id = :colis_id");
        $check->execute(['colis_id' => $colisId]);
        if ($check->fetchColumn() > 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO lbp_colis_expeditions (expedition_id, colis_id) VALUES (:expedition_id, :colis_id)
        ");
        $stmt->execute(['expedition_id' => $expeditionId, 'colis_id' => $colisId]);

        // Mettre à jour le statut du colis
        $this->updateStatus($colisId, 'EN_PREPARATION');
        return true;
    }

    public function removeColisFromExpedition(int $expeditionId, int $colisId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM lbp_colis_expeditions WHERE expedition_id = :exp_id AND colis_id = :colis_id");
        $stmt->execute(['exp_id' => $expeditionId, 'colis_id' => $colisId]);
        $this->updateStatus($colisId, 'RECEPTIONNE');
    }

    public function updateExpeditionStatus(int $expeditionId, string $newStatus): void
    {
        $stmt = $this->pdo->prepare("UPDATE lbp_expeditions SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['status' => $newStatus, 'id' => $expeditionId]);

        // Propager le statut aux colis de l'expédition
        $colisStatus = match($newStatus) {
            'EN_COURS' => 'EN_TRANSIT',
            'ARRIVE' => 'ARRIVE',
            default => null,
        };

        if ($colisStatus !== null) {
            $stmt2 = $this->pdo->prepare("
                UPDATE lbp_colis c
                INNER JOIN lbp_colis_expeditions ce ON ce.colis_id = c.id
                SET c.status = :status, c.updated_at = NOW()
                WHERE ce.expedition_id = :expedition_id
            ");
            $stmt2->execute(['status' => $colisStatus, 'expedition_id' => $expeditionId]);
        }
    }

    public function getColisReadyForExpedition(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.tracking_number, c.total_weight, c.status,
                   s.name AS sender_name, r.name AS receiver_name,
                   da.name AS departure_agency, aa.name AS arrival_agency
            FROM lbp_colis c
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            LEFT JOIN company_sites da ON da.id = c.departure_agency_id
            LEFT JOIN company_sites aa ON aa.id = c.arrival_agency_id
            WHERE c.status = 'RECEPTIONNE'
              AND c.id NOT IN (SELECT colis_id FROM lbp_colis_expeditions)
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // ─────────────────────────────────────────────
    //  TRACKING GPS
    // ─────────────────────────────────────────────

    public function getTrackingHistory(int $colisId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.full_name AS recorded_by_name
            FROM lbp_tracking_gps t
            LEFT JOIN users u ON u.id = t.recorded_by
            WHERE t.colis_id = :colis_id
            ORDER BY t.recorded_at ASC
        ");
        $stmt->execute(['colis_id' => $colisId]);
        return $stmt->fetchAll() ?: [];
    }

    public function addTrackingEvent(int $colisId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_tracking_gps (colis_id, step_name, status, latitude, longitude, recorded_by)
            VALUES (:colis_id, :step_name, :status, :latitude, :longitude, :recorded_by)
        ");
        $stmt->execute([
            'colis_id' => $colisId,
            'step_name' => $data['step_name'],
            'status' => $data['status'] ?? 'INFO',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'recorded_by' => $data['recorded_by'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────
    //  INVENTAIRES
    // ─────────────────────────────────────────────

    public function getAllInventaires(): array
    {
        $stmt = $this->pdo->query("
            SELECT i.*, s.name AS agency_name,
                   COUNT(l.id) AS nb_scanned,
                   SUM(CASE WHEN l.status = 'MANQUANT' THEN 1 ELSE 0 END) AS nb_missing
            FROM lbp_inventaires i
            LEFT JOIN company_sites s ON s.id = i.agency_id
            LEFT JOIN lbp_inventaire_lignes l ON l.inventaire_id = i.id
            GROUP BY i.id
            ORDER BY i.started_at DESC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public function findInventaireById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.name AS agency_name
            FROM lbp_inventaires i
            LEFT JOIN company_sites s ON s.id = i.agency_id
            WHERE i.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getLignesInventaire(int $inventaireId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, c.tracking_number, c.description,
                   s.name AS sender_name, r.name AS receiver_name
            FROM lbp_inventaire_lignes l
            INNER JOIN lbp_colis c ON c.id = l.colis_id
            LEFT JOIN crm_clients s ON s.id = c.sender_id
            LEFT JOIN crm_clients r ON r.id = c.receiver_id
            WHERE l.inventaire_id = :inventaire_id
            ORDER BY l.scanned_at DESC
        ");
        $stmt->execute(['inventaire_id' => $inventaireId]);
        return $stmt->fetchAll() ?: [];
    }

    public function createInventaire(int $agencyId, int $createdBy): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_inventaires (agency_id, created_by, status) VALUES (:agency_id, :created_by, 'EN_COURS')
        ");
        $stmt->execute(['agency_id' => $agencyId, 'created_by' => $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function scanColisInventaire(int $inventaireId, int $colisId, string $status, ?string $comments = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_inventaire_lignes (inventaire_id, colis_id, status, comments)
            VALUES (:inventaire_id, :colis_id, :status, :comments)
            ON DUPLICATE KEY UPDATE status = VALUES(status), comments = VALUES(comments)
        ");
        $stmt->execute([
            'inventaire_id' => $inventaireId,
            'colis_id' => $colisId,
            'status' => $status,
            'comments' => $comments,
        ]);
    }

    public function cloturerInventaire(int $inventaireId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_inventaires SET status = 'CLOTURE', closed_at = NOW() WHERE id = :id
        ");
        $stmt->execute(['id' => $inventaireId]);
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    public function getAgencies(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, code, country, city FROM company_sites WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll() ?: [];
    }

    public function getClients(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, phone, email FROM crm_clients ORDER BY name");
        return $stmt->fetchAll() ?: [];
    }

    public function getLivreurs(): array
    {
        $stmt = $this->pdo->query("
            SELECT l.id, u.full_name, l.vehicle_model, l.license_plate, l.status
            FROM lbp_livreurs l
            INNER JOIN users u ON u.id = l.user_id
            WHERE l.status = 'DISPONIBLE'
            ORDER BY u.full_name
        ");
        return $stmt->fetchAll() ?: [];
    }

    private function generateTrackingNumber(): string
    {
        return 'LBP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
    }
}
