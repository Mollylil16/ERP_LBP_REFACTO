<?php

namespace App\Modules\Colissage\Repositories;

use App\Models\Database;
use PDO;

class InventaireRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchInventaires(array $filters = []): array
    {
        $sql = "
            SELECT
                i.id,
                i.id_agence,
                i.date_inventaire,
                i.statut,
                u.fullname as createur_nom
            FROM lbp_inventaires i
            LEFT JOIN lbp_users u ON i.id_createur = u.id
            WHERE 1=1
        ";
        
        $params = [];
        if (isset($filters['id_agence'])) {
            $sql .= " AND i.id_agence = :id_agence";
            $params['id_agence'] = $filters['id_agence'];
        }

        $sql .= " ORDER BY i.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createInventaire(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_inventaires (id_agence, id_createur, statut) 
             VALUES (:id_agence, :id_createur, :statut) 
             RETURNING *'
        );

        $stmt->execute([
            'id_agence' => $data['id_agence'],
            'id_createur' => $data['id_createur'],
            'statut' => $data['statut'] ?? 'EN_COURS',
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addLigne(int $id_inventaire, array $ligne): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_inventaire_lignes (id_inventaire, id_colis, statut_constate, commentaire) 
             VALUES (:id_inventaire, :id_colis, :statut_constate, :commentaire)'
        );

        $stmt->execute([
            'id_inventaire' => $id_inventaire,
            'id_colis' => $ligne['id_colis'],
            'statut_constate' => $ligne['statut_constate'],
            'commentaire' => $ligne['commentaire'] ?? null,
        ]);
    }
}
