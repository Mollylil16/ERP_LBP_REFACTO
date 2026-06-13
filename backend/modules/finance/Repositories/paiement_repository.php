<?php

namespace App\Modules\Finance\Repositories;

use App\Models\Database;
use PDO;

class PaiementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchPaiements(array $filters = []): array
    {
        $sql = "
            SELECT
                p.id,
                p.reference,
                p.montant,
                p.mode_paiement,
                p.id_facture,
                p.date_paiement,
                f.numero AS facture_numero,
                u.fullname AS caissier_nom
            FROM lbp_paiements p
            LEFT JOIN lbp_factures f ON p.id_facture = f.id
            LEFT JOIN lbp_users u ON p.id_caissier = u.id
        ";

        $stmt = $this->pdo->query($sql . " ORDER BY p.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPaiement(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO lbp_paiements (reference, montant, mode_paiement, id_facture, id_caissier) 
                 VALUES (:reference, :montant, :mode_paiement, :id_facture, :id_caissier) 
                 RETURNING *'
            );

            $stmt->execute([
                'reference' => $data['reference'] ?? uniqid('PAY-'),
                'montant' => $data['montant'],
                'mode_paiement' => $data['mode_paiement'],
                'id_facture' => $data['id_facture'],
                'id_caissier' => $data['id_caissier'] ?? null,
            ]);

            $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

            // Mettre à jour le statut de la facture si nécessaire (simplifié)
            $stmtFacture = $this->pdo->prepare("UPDATE lbp_factures SET statut = 'PAYEE' WHERE id = :id");
            $stmtFacture->execute(['id' => $data['id_facture']]);

            $this->pdo->commit();
            return $paiement;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException("Erreur lors de l'enregistrement du paiement: " . $e->getMessage());
        }
    }
}
