<?php

namespace App\Modules\Colissage\Repositories;

use App\Models\Database;
use PDO;

class ClientRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchClients(array $filters = []): array
    {
        $sql = "SELECT id, nom, prenom, telephone, email, type_client FROM lbp_clients";
        $stmt = $this->pdo->query($sql . " ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchClientById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_clients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client === false ? null : $client;
    }

    public function createClient(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_clients (nom, prenom, telephone, email, adresse, type_client) 
             VALUES (:nom, :prenom, :telephone, :email, :adresse, :type_client) 
             RETURNING *'
        );

        $stmt->execute([
            'nom' => $data['nom'] ?? '',
            'prenom' => $data['prenom'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'type_client' => $data['type_client'] ?? 'STANDARD',
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
