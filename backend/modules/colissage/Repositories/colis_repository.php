<?php

namespace App\Modules\Colissage\Repositories;

use App\Models\Database;
use PDO;

class ColisRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchColis(array $filters = []): array
    {
        $sql = "
            SELECT
                c.id,
                c.numero_tracking,
                c.statut,
                c.poids,
                c.valeur_declaree,
                exp.nom AS expediteur_nom,
                exp.prenom AS expediteur_prenom,
                dest.nom AS destinataire_nom,
                dest.prenom AS destinataire_prenom
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.id_expediteur = exp.id
            LEFT JOIN lbp_clients dest ON c.id_destinataire = dest.id
        ";

        $stmt = $this->pdo->query($sql . " ORDER BY c.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchColisById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_colis WHERE id = :id LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $colis = $stmt->fetch(PDO::FETCH_ASSOC);

        return $colis === false ? null : $colis;
    }

    public function createColis(array $data, string $numero_tracking): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_colis (numero_tracking, statut, poids, id_expediteur, id_destinataire, id_createur, id_operateur) 
             VALUES (:numero_tracking, :statut, :poids, :id_expediteur, :id_destinataire, :id_createur, :id_operateur) 
             RETURNING *'
        );

        $stmt->execute([
            'numero_tracking' => $numero_tracking,
            'statut' => $data['statut'] ?? 'RECEPTIONNE',
            'poids' => $data['poids'] ?? null,
            'id_expediteur' => $data['id_expediteur'] ?? null,
            'id_destinataire' => $data['id_destinataire'] ?? null,
            'id_createur' => $data['id_createur'] ?? null,
            'id_operateur' => $data['id_operateur'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatut(int $id, string $statut): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lbp_colis SET statut = :statut, updated_at = NOW() WHERE id = :id RETURNING *'
        );
        $stmt->execute(['statut' => $statut, 'id' => $id]);
        $colis = $stmt->fetch(PDO::FETCH_ASSOC);

        return $colis === false ? null : $colis;
    }

    public function retraitColis(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lbp_colis 
             SET statut = \'LIVRE\', 
                 nom_recuperateur = :nom, 
                 cni_recuperateur = :cni, 
                 telephone_recuperateur = :telephone, 
                 date_retrait = NOW(),
                 updated_at = NOW() 
             WHERE id = :id 
             RETURNING *'
        );

        $stmt->execute([
            'id' => $id,
            'nom' => $data['nom_recuperateur'] ?? null,
            'cni' => $data['cni_recuperateur'] ?? null,
            'telephone' => $data['telephone_recuperateur'] ?? null,
        ]);

        $colis = $stmt->fetch(PDO::FETCH_ASSOC);
        return $colis === false ? null : $colis;
    }
}
