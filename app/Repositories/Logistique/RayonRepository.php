<?php

declare(strict_types=1);

namespace App\Repositories\Logistique;

use App\Models\Logistique\Rayon;
use PDO;

class RayonRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int, Rayon>
     */
    public function getAllRayons(?int $agenceId = null): array
    {
        $sql = "
            SELECT r.*,
                   s.name as agence_nom,
                   (SELECT COUNT(*) FROM lbp_colis c WHERE c.rayon_id = r.id AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')) as capacite_occupee
            FROM logistique_rayons r
            LEFT JOIN company_sites s ON r.agence_id = s.id
        ";

        $params = [];
        if ($agenceId !== null && $agenceId > 0) {
            $sql .= " WHERE r.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        $sql .= " ORDER BY r.agence_id ASC, r.code_rayon ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Rayon::fromArray($row);
        }

        return $results;
    }

    public function findRayonById(int $id): ?Rayon
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   s.name as agence_nom,
                   (SELECT COUNT(*) FROM lbp_colis c WHERE c.rayon_id = r.id AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')) as capacite_occupee
            FROM logistique_rayons r
            LEFT JOIN company_sites s ON r.agence_id = s.id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Rayon::fromArray($row) : null;
    }

    /**
     * Recherche le premier rayon disponible dans l'agence donnée ayant de la capacité libre.
     */
    public function findAvailableRayon(int $agenceId): ?Rayon
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   s.name as agence_nom,
                   (SELECT COUNT(*) FROM lbp_colis c WHERE c.rayon_id = r.id AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')) as capacite_occupee
            FROM logistique_rayons r
            LEFT JOIN company_sites s ON r.agence_id = s.id
            WHERE r.agence_id = :agence_id AND r.statut = 'ACTIF'
            HAVING capacite_occupee < r.capacite_max
            ORDER BY r.code_rayon ASC
            LIMIT 1
        ");
        $stmt->execute(['agence_id' => $agenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Rayon::fromArray($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createRayon(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO logistique_rayons (agence_id, code_rayon, nom_rayon, capacite_max, statut, created_at)
            VALUES (:agence_id, :code_rayon, :nom_rayon, :capacite_max, :statut, NOW())
        ");
        $stmt->execute([
            'agence_id' => (int) ($data['agence_id'] ?? 1),
            'code_rayon' => strtoupper(trim((string) ($data['code_rayon'] ?? ''))),
            'nom_rayon' => trim((string) ($data['nom_rayon'] ?? '')),
            'capacite_max' => max(1, (int) ($data['capacite_max'] ?? 50)),
            'statut' => (string) ($data['statut'] ?? 'ACTIF'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRayon(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE logistique_rayons
            SET code_rayon = :code_rayon,
                nom_rayon = :nom_rayon,
                capacite_max = :capacite_max,
                statut = :statut,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'code_rayon' => strtoupper(trim((string) ($data['code_rayon'] ?? ''))),
            'nom_rayon' => trim((string) ($data['nom_rayon'] ?? '')),
            'capacite_max' => max(1, (int) ($data['capacite_max'] ?? 50)),
            'statut' => (string) ($data['statut'] ?? 'ACTIF'),
        ]);
    }

    public function deleteRayon(int $id): bool
    {
        // Dé-référencer d'abord les colis dans ce rayon
        $stmtColis = $this->pdo->prepare("UPDATE lbp_colis SET rayon_id = NULL WHERE rayon_id = :id");
        $stmtColis->execute(['id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM logistique_rayons WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function assignColisToRayon(int $colisId, ?int $rayonId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET rayon_id = :rayon_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $colisId,
            'rayon_id' => $rayonId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColisByRayon(int $rayonId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   exp.name as expediteur_nom,
                   dest.name as destinataire_nom
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            WHERE c.rayon_id = :rayon_id AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['rayon_id' => $rayonId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enregistre un mouvement d'entrée ou de sortie dans un rayon.
     */
    public function recordMouvement(int $colisId, ?int $rayonId, string $typeMouvement, ?int $userId = null, ?string $commentaires = null): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO logistique_mouvements_rayon (colis_id, rayon_id, type_mouvement, effectue_par, commentaires, created_at)
            VALUES (:colis_id, :rayon_id, :type_mouvement, :effectue_par, :commentaires, NOW())
        ");
        return $stmt->execute([
            'colis_id' => $colisId,
            'rayon_id' => $rayonId,
            'type_mouvement' => $typeMouvement,
            'effectue_par' => $userId,
            'commentaires' => $commentaires,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMouvementsForColis(int $colisId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, r.code_rayon, r.nom_rayon
            FROM logistique_mouvements_rayon m
            LEFT JOIN logistique_rayons r ON m.rayon_id = r.id
            WHERE m.colis_id = :colis_id
            ORDER BY m.created_at DESC
        ");
        $stmt->execute(['colis_id' => $colisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
