<?php

namespace App\Modules\Logistique\Repositories;

use App\Models\Database;
use PDO;

class ExpeditionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchExpeditions(array $filters = []): array
    {
        $sql = "
            SELECT
                e.id,
                e.reference,
                e.statut,
                e.id_livreur,
                e.id_agence_depart,
                e.id_agence_arrivee,
                e.date_depart,
                e.date_arrivee_prevue,
                e.date_arrivee_reelle,
                l.vehicule,
                l.immatriculation,
                u.fullname AS livreur_nom
            FROM lbp_expeditions e
            LEFT JOIN lbp_livreurs l ON e.id_livreur = l.id
            LEFT JOIN lbp_users u ON l.id_user = u.id
        ";

        $stmt = $this->pdo->query($sql . " ORDER BY e.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchExpeditionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                e.id,
                e.reference,
                e.statut,
                e.id_livreur,
                e.id_agence_depart,
                e.id_agence_arrivee,
                e.date_depart,
                e.date_arrivee_prevue,
                e.date_arrivee_reelle
            FROM lbp_expeditions e
            WHERE e.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);

        return $exp === false ? null : $exp;
    }

    public function createExpedition(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_expeditions (reference, id_livreur, id_agence_depart, id_agence_arrivee, date_depart, date_arrivee_prevue) 
             VALUES (:reference, :id_livreur, :id_agence_depart, :id_agence_arrivee, :date_depart, :date_arrivee_prevue) 
             RETURNING *'
        );

        $stmt->execute([
            'reference' => $data['reference'] ?? uniqid('EXP-'),
            'id_livreur' => $data['id_livreur'] ?? null,
            'id_agence_depart' => $data['id_agence_depart'] ?? null,
            'id_agence_arrivee' => $data['id_agence_arrivee'] ?? null,
            'date_depart' => $data['date_depart'] ?? null,
            'date_arrivee_prevue' => $data['date_arrivee_prevue'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatut(int $id, string $statut): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lbp_expeditions SET statut = :statut, updated_at = NOW() WHERE id = :id RETURNING *'
        );
        $stmt->execute(['statut' => $statut, 'id' => $id]);
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);

        return $exp === false ? null : $exp;
    }
}
