<?php

namespace App\Modules\Rh\Repositories;

use App\Models\Database;
use PDO;

class OperatorRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchOperateursByAgence(int $agenceId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, id_agence, nom_complet, code_secret_hash, "isActive" as is_active 
            FROM lbp_operateurs 
            WHERE id_agence = :id_agence AND "isActive" = true
        ');
        $stmt->execute(['id_agence' => $agenceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchOperateurById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, id_agence, nom_complet, code_secret_hash, "isActive" as is_active 
            FROM lbp_operateurs 
            WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        return $op === false ? null : $op;
    }

    public function createOperateur(array $data): array
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lbp_operateurs (id_agence, nom_complet, code_secret_hash)
            VALUES (:id_agence, :nom_complet, :code_secret_hash)
            RETURNING id, id_agence, nom_complet, "isActive" as is_active
        ');
        $stmt->execute([
            'id_agence' => $data['id_agence'],
            'nom_complet' => $data['nom_complet'],
            'code_secret_hash' => $data['code_secret_hash']
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateOperateur(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];

        if (isset($data['nom_complet'])) {
            $sets[] = 'nom_complet = :nom_complet';
            $params['nom_complet'] = $data['nom_complet'];
        }

        if (isset($data['code_secret_hash'])) {
            $sets[] = 'code_secret_hash = :code_secret_hash';
            $params['code_secret_hash'] = $data['code_secret_hash'];
        }

        if (isset($data['isActive'])) {
            $sets[] = '"isActive" = :isActive';
            $params['isActive'] = $data['isActive'];
        }

        if (empty($sets)) {
            return $this->fetchOperateurById($id);
        }

        $sets[] = 'updated_at = NOW()';
        $sql = sprintf(
            'UPDATE lbp_operateurs SET %s WHERE id = :id RETURNING id, id_agence, nom_complet, "isActive" as is_active',
            implode(', ', $sets)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        return $op === false ? null : $op;
    }
}
