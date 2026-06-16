<?php

namespace App\Repositories;

use PDO;
use App\Models\Database;

class CrmRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAllClients(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM crm_clients ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createClient(array $data, ?int $createdBy = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_clients (
                type, name, contact_name, email, phone, country, city, sector, status, notes, created_by, created_at
            ) VALUES (
                :type, :name, :contact_name, :email, :phone, :country, :city, :sector, :status, :notes, :created_by, NOW()
            )
        ");
        
        $stmt->execute([
            'type' => $data['type'] ?? 'client',
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'sector' => $data['sector'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
