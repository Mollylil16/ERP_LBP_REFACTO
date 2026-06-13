<?php

namespace App\Modules\Tarifs\Repositories;

use App\Models\Database;
use PDO;

class TarifsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM lbp_tarifs WHERE is_active = TRUE ORDER BY nom ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifTrajet(string $paysDepart, string $paysArrivee, string $typeTarif): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_tarifs 
            WHERE pays_depart = :depart AND pays_arrivee = :arrivee AND type_tarif = :type AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([
            'depart' => $paysDepart,
            'arrivee' => $paysArrivee,
            'type' => $typeTarif
        ]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_tarifs (type_tarif, nom, description, pays_depart, pays_arrivee, montant, devise) 
            VALUES (:type_tarif, :nom, :description, :pays_depart, :pays_arrivee, :montant, :devise)
            RETURNING *
        ");
        $stmt->execute([
            'type_tarif' => $data['type_tarif'],
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'pays_depart' => $data['pays_depart'],
            'pays_arrivee' => $data['pays_arrivee'],
            'montant' => $data['montant'],
            'devise' => $data['devise'] ?? 'XOF'
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
