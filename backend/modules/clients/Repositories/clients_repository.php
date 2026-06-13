<?php

namespace App\Modules\Clients\Repositories;

use App\Models\Database;
use PDO;

class ClientsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM lbp_clients ORDER BY nom ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_clients WHERE nom ILIKE :q OR prenom ILIKE :q OR telephone ILIKE :q");
        $stmt->execute(['q' => "%$query%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_clients (nom, prenom, telephone, email, adresse, type_piece_identite, numero_piece_identite) 
            VALUES (:nom, :prenom, :telephone, :email, :adresse, :type_piece, :numero_piece)
            RETURNING *
        ");
        $stmt->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'type_piece' => $data['type_piece_identite'] ?? null,
            'numero_piece' => $data['numero_piece_identite'] ?? null
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
