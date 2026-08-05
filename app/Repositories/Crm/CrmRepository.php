<?php

declare(strict_types=1);

namespace App\Repositories\Crm;

use PDO;
use Throwable;

final class CrmRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string, mixed> */
    public function dashboardStats(): array
    {
        $clientsCount = 0;
        $prospectsCount = 0;
        $relancesCount = 0;
        $opportunitesCount = 0;
        $interactionsCount = 0;

        try {
            $clientsCount = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_clients WHERE crm_status = 'actif'")->fetchColumn();
            $prospectsCount = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_clients WHERE crm_status = 'prospect'")->fetchColumn();
            $relancesCount = (int) $this->pdo->query("SELECT COUNT(*) FROM crm_interactions WHERE next_action_date IS NOT NULL AND next_action_date >= CURDATE()")->fetchColumn();
            $opportunitesCount = (int) $this->pdo->query("SELECT COUNT(*) FROM crm_opportunities WHERE stage NOT IN ('gagnee', 'perdue')")->fetchColumn();
            $interactionsCount = (int) $this->pdo->query("SELECT COUNT(*) FROM crm_interactions")->fetchColumn();
        } catch (Throwable $e) {
        }

        return [
            'clientsCount' => $clientsCount,
            'prospectsCount' => $prospectsCount,
            'relancesCount' => $relancesCount,
            'opportunitesCount' => $opportunitesCount,
            'interactionsCount' => $interactionsCount,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array{clients: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listClients(array $filters, int $page = 1, int $perPage = 20): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = '(c.name LIKE :q1 OR c.phone LIKE :q2 OR c.email LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        if (!empty($filters['crm_status'])) {
            $conditions[] = 'c.crm_status = :crm_status';
            $params['crm_status'] = $filters['crm_status'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM lbp_clients c {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name AS commercial_owner_name
            FROM lbp_clients c
            LEFT JOIN users u ON c.commercial_owner_id = u.id
            {$where}
            ORDER BY c.name ASC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'clients' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'pagination' => ['currentPage' => $page, 'totalPages' => $totalPages, 'totalItems' => $total, 'itemsPerPage' => $perPage],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findClient(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name AS commercial_owner_name, s.name AS agence_creation_name
            FROM lbp_clients c
            LEFT JOIN users u ON c.commercial_owner_id = u.id
            LEFT JOIN company_sites s ON c.agence_creation_id = s.id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function clientColis(int $id, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, numero_tracking, statut, nombre_colis, montant_total, devise, created_at
            FROM lbp_colis
            WHERE expediteur_id = :id OR destinataire_id = :id2
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':id2', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function clientFactures(int $id, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, numero_facture, statut, montant_total, montant_restant, devise, date_emission
            FROM lbp_factures
            WHERE client_id = :id
            ORDER BY date_emission DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function clientInteractions(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, u.full_name AS user_name
            FROM crm_interactions ci
            LEFT JOIN users u ON ci.user_id = u.id
            WHERE ci.client_id = :id
            ORDER BY ci.interaction_at DESC
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function clientOpportunities(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM crm_opportunities
            WHERE client_id = :id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function commercialOwners(): array
    {
        $stmt = $this->pdo->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name ASC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createClient(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_clients (name, phone, email, address, type, crm_status, secteur_activite, notes_commerciales, commercial_owner_id)
            VALUES (:name, :phone, :email, :address, :type, :crm_status, :secteur_activite, :notes_commerciales, :commercial_owner_id)
        ");
        $stmt->execute([
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
            'type' => $data['type'] ?: 'standard',
            'crm_status' => $data['crm_status'] ?: 'prospect',
            'secteur_activite' => $data['secteur_activite'] ?: null,
            'notes_commerciales' => $data['notes_commerciales'] ?: null,
            'commercial_owner_id' => $data['commercial_owner_id'] ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateClientCrm(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_clients SET
                crm_status = :crm_status,
                secteur_activite = :secteur_activite,
                notes_commerciales = :notes_commerciales,
                commercial_owner_id = :commercial_owner_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'crm_status' => $data['crm_status'] ?: 'prospect',
            'secteur_activite' => $data['secteur_activite'] ?: null,
            'notes_commerciales' => $data['notes_commerciales'] ?: null,
            'commercial_owner_id' => $data['commercial_owner_id'] ?: null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function createInteraction(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_interactions (client_id, user_id, channel, subject, notes, interaction_at, next_action_date)
            VALUES (:client_id, :user_id, :channel, :subject, :notes, NOW(), :next_action_date)
        ");
        $stmt->execute([
            'client_id' => $data['client_id'],
            'user_id' => $data['user_id'] ?: null,
            'channel' => $data['channel'] ?: 'appel',
            'subject' => $data['subject'],
            'notes' => $data['notes'] ?: null,
            'next_action_date' => $data['next_action_date'] ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createOpportunity(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_opportunities (client_id, title, stage, estimated_amount, currency, expected_close_date, probability)
            VALUES (:client_id, :title, :stage, :estimated_amount, :currency, :expected_close_date, :probability)
        ");
        $stmt->execute([
            'client_id' => $data['client_id'],
            'title' => $data['title'],
            'stage' => $data['stage'] ?: 'qualification',
            'estimated_amount' => $data['estimated_amount'] !== '' ? (float) $data['estimated_amount'] : null,
            'currency' => $data['currency'] ?: 'XOF',
            'expected_close_date' => $data['expected_close_date'] ?: null,
            'probability' => (int) ($data['probability'] ?: 10),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateOpportunityStage(int $id, string $stage): void
    {
        $probability = match ($stage) {
            'gagnee' => 100,
            'perdue' => 0,
            'negociation' => 75,
            'proposition' => 50,
            default => 25,
        };

        $stmt = $this->pdo->prepare("UPDATE crm_opportunities SET stage = :stage, probability = :probability, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id, 'stage' => $stage, 'probability' => $probability]);
    }
}
