<?php

namespace App\Modules\Colissage\Repositories;

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
                e.numero_expedition,
                e.type,
                e.date_depart,
                e.date_arrivee_prevue,
                e.statut,
                ad.nom_agence AS agence_depart,
                aa.nom_agence AS agence_arrivee
            FROM lbp_expeditions e
            LEFT JOIN lbp_agences ad ON e.id_agence_depart = ad.id
            LEFT JOIN lbp_agences aa ON e.id_agence_arrivee = aa.id
            ORDER BY e.id DESC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createExpedition(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_expeditions (numero_expedition, type, id_agence_depart, id_agence_arrivee, date_depart, date_arrivee_prevue) 
             VALUES (:numero_expedition, :type, :id_agence_depart, :id_agence_arrivee, :date_depart, :date_arrivee_prevue) 
             RETURNING *'
        );

        $stmt->execute([
            'numero_expedition' => $data['numero_expedition'] ?? uniqid('EXP-'),
            'type' => $data['type'] ?? 'AERIEN',
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
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function addGpsTracking(int $id_expedition, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_tracking_gps (id_expedition, latitude, longitude, etape_description) 
             VALUES (:id_expedition, :latitude, :longitude, :etape_description)'
        );

        $stmt->execute([
            'id_expedition' => $id_expedition,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'etape_description' => $data['etape_description'],
        ]);
    }

    public function fetchGpsTracking(int $id_expedition): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT latitude, longitude, etape_description, date_enregistrement 
             FROM lbp_tracking_gps 
             WHERE id_expedition = :id_expedition 
             ORDER BY date_enregistrement ASC'
        );
        $stmt->execute(['id_expedition' => $id_expedition]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
