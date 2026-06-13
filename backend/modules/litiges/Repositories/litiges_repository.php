<?php

namespace App\Modules\Litiges\Repositories;

use App\Models\Database;
use PDO;

class LitigesRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT l.*, c.nom as client_nom, c.prenom as client_prenom, co.numero_tracking 
            FROM lbp_litiges l
            LEFT JOIN lbp_clients c ON c.id = l.id_client
            LEFT JOIN lbp_colis co ON co.id = l.id_colis
            ORDER BY l.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): array
    {
        $numeroLitige = 'LIT-' . date('y') . '-' . rand(1000, 9999); // Simple generator

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_litiges (numero_litige, id_client, id_colis, type_litige, description, priorite) 
            VALUES (:numero, :id_client, :id_colis, :type_litige, :description, :priorite)
            RETURNING *
        ");
        $stmt->execute([
            'numero' => $numeroLitige,
            'id_client' => $data['id_client'],
            'id_colis' => $data['id_colis'] ?? null,
            'type_litige' => $data['type_litige'],
            'description' => $data['description'],
            'priorite' => $data['priorite'] ?? 'MOYENNE'
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
