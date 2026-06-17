<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Database;
use PDO;

/**
 * Repository pour le module Logistique Interne & Exploitation.
 * Gère : Prestataires, Factures, Retraits Hub, Fournitures, Crédits inter-agences.
 */
final class LogistiqueRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────
    //  PRESTATAIRES
    // ─────────────────────────────────────────────

    public function getAllPrestataires(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['type']) && $filters['type'] !== '') {
            $where[] = 'p.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'p.name LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   COUNT(f.id) AS nb_factures,
                   SUM(CASE WHEN f.status = 'EN_ATTENTE' THEN f.amount - COALESCE(f.amount_paid, 0) ELSE 0 END) AS encours
            FROM lbp_prestataires p
            LEFT JOIN lbp_factures_prestataires f ON f.prestataire_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            ORDER BY p.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findPrestataire(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_prestataires WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createPrestataire(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_prestataires (type, name, contact_info, country, contact_name, phone, email, is_active)
            VALUES (:type, :name, :contact_info, :country, :contact_name, :phone, :email, 1)
        ");
        $stmt->execute([
            'type' => $data['type'],
            'name' => $data['name'],
            'contact_info' => $data['contact_info'] ?? null,
            'country' => $data['country'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ─────────────────────────────────────────────
    //  FACTURES PRESTATAIRES
    // ─────────────────────────────────────────────

    public function getAllFactures(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'f.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['prestataire_id'])) {
            $where[] = 'f.prestataire_id = :prestataire_id';
            $params['prestataire_id'] = $filters['prestataire_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT f.*, p.name AS prestataire_name, p.type AS prestataire_type,
                   (f.amount - COALESCE(f.amount_paid, 0)) AS reliquat
            FROM lbp_factures_prestataires f
            INNER JOIN lbp_prestataires p ON p.id = f.prestataire_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY f.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findFacture(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.name AS prestataire_name,
                   (f.amount - COALESCE(f.amount_paid, 0)) AS reliquat
            FROM lbp_factures_prestataires f
            INNER JOIN lbp_prestataires p ON p.id = f.prestataire_id
            WHERE f.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createFacture(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_factures_prestataires (
                prestataire_id, invoice_number, amount, currency,
                status, due_date, lta_number, issue_date, amount_paid, notes
            ) VALUES (
                :prestataire_id, :invoice_number, :amount, :currency,
                'EN_ATTENTE', :due_date, :lta_number, :issue_date, 0, :notes
            )
        ");
        $stmt->execute([
            'prestataire_id' => $data['prestataire_id'],
            'invoice_number' => $data['invoice_number'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'XOF',
            'due_date' => $data['due_date'] ?: null,
            'lta_number' => $data['lta_number'] ?? null,
            'issue_date' => $data['issue_date'] ?: null,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getRetraitsOfFacture(int $factureId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.full_name AS recorded_by_name
            FROM lbp_retraits_prestataires r
            LEFT JOIN users u ON u.id = r.recorded_by
            WHERE r.facture_id = :facture_id
            ORDER BY r.payment_date DESC
        ");
        $stmt->execute(['facture_id' => $factureId]);
        return $stmt->fetchAll() ?: [];
    }

    // ─────────────────────────────────────────────
    //  RETRAITS HUB
    // ─────────────────────────────────────────────

    public function getAllRetraits(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare("
            SELECT r.*, f.invoice_number, f.currency,
                   p.name AS prestataire_name,
                   u.full_name AS recorded_by_name,
                   ua.full_name AS approved_by_name
            FROM lbp_retraits_prestataires r
            INNER JOIN lbp_factures_prestataires f ON f.id = r.facture_id
            INNER JOIN lbp_prestataires p ON p.id = f.prestataire_id
            LEFT JOIN users u ON u.id = r.recorded_by
            LEFT JOIN users ua ON ua.id = r.approved_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.payment_date DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function createRetrait(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_retraits_prestataires (facture_id, amount_paid, currency, recorded_by, reference_transaction, notes, status)
            VALUES (:facture_id, :amount_paid, :currency, :recorded_by, :reference_transaction, :notes, 'EN_ATTENTE')
        ");
        $stmt->execute([
            'facture_id' => $data['facture_id'],
            'amount_paid' => $data['amount_paid'],
            'currency' => $data['currency'] ?? 'XOF',
            'recorded_by' => $data['recorded_by'],
            'reference_transaction' => $data['reference_transaction'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function approuverRetrait(int $retraitId, int $approvedBy): void
    {
        $retrait = $this->pdo->prepare("SELECT * FROM lbp_retraits_prestataires WHERE id = :id");
        $retrait->execute(['id' => $retraitId]);
        $row = $retrait->fetch();
        if (!$row) return;

        $this->pdo->prepare("
            UPDATE lbp_retraits_prestataires SET status = 'APPROUVE', approved_by = :by, approved_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ")->execute(['by' => $approvedBy, 'id' => $retraitId]);

        // Mettre à jour le montant payé sur la facture
        $this->pdo->prepare("
            UPDATE lbp_factures_prestataires SET
                amount_paid = LEAST(amount, COALESCE(amount_paid, 0) + :amount),
                status = CASE WHEN (COALESCE(amount_paid, 0) + :amount2) >= amount THEN 'PAYEE' ELSE 'EN_ATTENTE' END,
                updated_at = NOW()
            WHERE id = :facture_id
        ")->execute([
            'amount' => $row['amount_paid'],
            'amount2' => $row['amount_paid'],
            'facture_id' => $row['facture_id'],
        ]);
    }

    public function refuserRetrait(int $retraitId, int $refusedBy, string $reason): void
    {
        $this->pdo->prepare("
            UPDATE lbp_retraits_prestataires SET
                status = 'REFUSE', approved_by = :by, approved_at = NOW(),
                rejection_reason = :reason, updated_at = NOW()
            WHERE id = :id
        ")->execute(['by' => $refusedBy, 'reason' => $reason, 'id' => $retraitId]);
    }

    // ─────────────────────────────────────────────
    //  FOURNITURES
    // ─────────────────────────────────────────────

    public function getAllFournitures(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['agency_id'])) {
            $where[] = 'd.agency_id = :agency_id';
            $params['agency_id'] = $filters['agency_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT d.*, s.name AS agency_name, u.full_name AS requested_by_name
            FROM lbp_demandes_fournitures d
            LEFT JOIN company_sites s ON s.id = d.agency_id
            LEFT JOIN users u ON u.id = d.requested_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function createFourniture(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_demandes_fournitures (agency_id, requested_by, items_requested, status)
            VALUES (:agency_id, :requested_by, :items_requested, 'EN_ATTENTE')
        ");
        $stmt->execute([
            'agency_id' => $data['agency_id'],
            'requested_by' => $data['requested_by'],
            'items_requested' => $data['items_requested'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function validerFourniture(int $id, int $validatedBy): void
    {
        $this->pdo->prepare("
            UPDATE lbp_demandes_fournitures SET
                status = 'APPROUVEE', validated_by = :by, validated_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ")->execute(['by' => $validatedBy, 'id' => $id]);
    }

    public function livrerFourniture(int $id): void
    {
        $this->pdo->prepare("
            UPDATE lbp_demandes_fournitures SET
                status = 'LIVREE', delivered_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $id]);
    }

    public function rejeterFourniture(int $id, int $rejectedBy, string $reason): void
    {
        $this->pdo->prepare("
            UPDATE lbp_demandes_fournitures SET
                status = 'REJETEE', validated_by = :by, validated_at = NOW(),
                rejection_reason = :reason, updated_at = NOW()
            WHERE id = :id
        ")->execute(['by' => $rejectedBy, 'reason' => $reason, 'id' => $id]);
    }

    // ─────────────────────────────────────────────
    //  KPIs Dashboard
    // ─────────────────────────────────────────────

    public function getDashboardKpis(): array
    {
        $encours = $this->pdo->query("
            SELECT COALESCE(SUM(f.amount - COALESCE(f.amount_paid, 0)), 0) AS total
            FROM lbp_factures_prestataires f WHERE f.status = 'EN_ATTENTE'
        ")->fetchColumn();

        $retraitsEnAttente = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_retraits_prestataires WHERE status = 'EN_ATTENTE'
        ")->fetchColumn();

        $fournituresEnAttente = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_demandes_fournitures WHERE status = 'EN_ATTENTE'
        ")->fetchColumn();

        return [
            'encours_fournisseurs' => (float) $encours,
            'retraits_en_attente' => (int) $retraitsEnAttente,
            'fournitures_en_attente' => (int) $fournituresEnAttente,
        ];
    }
    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    public function getAgencies(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, code, country, city FROM company_sites WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll() ?: [];
    }
}
