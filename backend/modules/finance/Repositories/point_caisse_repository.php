<?php

namespace App\Modules\Finance\Repositories;

use App\Models\Database;
use PDO;

class PointCaisseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchPointsCaisse(array $filters = []): array
    {
        $sql = "
            SELECT
                p.id,
                p.id_agence,
                p.date_point,
                p.total_encaisse_xof,
                p.total_encaisse_eur,
                p.statut,
                a.nom_agence,
                c.fullname AS caissiere_nom
            FROM lbp_points_caisse p
            LEFT JOIN lbp_agences a ON p.id_agence = a.id
            LEFT JOIN lbp_users c ON p.id_caissiere = c.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['id_agence'])) {
            $sql .= " AND p.id_agence = :id_agence";
            $params['id_agence'] = $filters['id_agence'];
        }

        if (!empty($filters['statut'])) {
            $sql .= " AND p.statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        $sql .= " ORDER BY p.date_point DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPointCaisse(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_points_caisse (id_agence, id_caissiere, date_point, total_encaisse_xof, total_encaisse_eur, commentaire) 
             VALUES (:id_agence, :id_caissiere, :date_point, :total_encaisse_xof, :total_encaisse_eur, :commentaire) 
             RETURNING *'
        );

        $stmt->execute([
            'id_agence' => $data['id_agence'],
            'id_caissiere' => $data['id_caissiere'],
            'date_point' => $data['date_point'],
            'total_encaisse_xof' => $data['total_encaisse_xof'] ?? 0,
            'total_encaisse_eur' => $data['total_encaisse_eur'] ?? 0,
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function validerPointCaisse(int $id, int $idValidateur, string $statut): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lbp_points_caisse 
             SET statut = :statut, id_validateur = :id_validateur, date_validation = NOW(), updated_at = NOW() 
             WHERE id = :id 
             RETURNING *'
        );

        $stmt->execute([
            'id' => $id,
            'statut' => $statut,
            'id_validateur' => $idValidateur,
        ]);

        $point = $stmt->fetch(PDO::FETCH_ASSOC);
        return $point === false ? null : $point;
    }
}
