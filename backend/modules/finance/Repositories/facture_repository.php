<?php

namespace App\Modules\Finance\Repositories;

use App\Models\Database;
use PDO;

class FactureRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchFactures(array $filters = []): array
    {
        $sql = "
            SELECT
                f.id,
                f.numero,
                f.montant_total,
                f.statut,
                f.id_client,
                f.date_emission,
                f.date_echeance,
                c.nom AS client_nom,
                c.prenom AS client_prenom
            FROM lbp_factures f
            LEFT JOIN lbp_clients c ON f.id_client = c.id
        ";

        $stmt = $this->pdo->query($sql . " ORDER BY f.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchFactureById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                f.id,
                f.numero,
                f.montant_total,
                f.statut,
                f.id_client,
                f.date_emission,
                f.date_echeance,
                c.nom AS client_nom,
                c.prenom AS client_prenom
            FROM lbp_factures f
            LEFT JOIN lbp_clients c ON f.id_client = c.id
            WHERE f.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);

        return $facture === false ? null : $facture;
    }

    public function createFacture(array $data): array
    {
        $taux = $data['taux_change_eur_xof'] ?? 655.957;
        $montant_xof = $data['montant_xof'] ?? 0;
        $montant_eur = $data['montant_eur'] ?? 0;

        if ($montant_xof > 0 && $montant_eur == 0) {
            $montant_eur = round($montant_xof / $taux, 2);
        } elseif ($montant_eur > 0 && $montant_xof == 0) {
            $montant_xof = round($montant_eur * $taux, 2);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_factures (numero, montant_total, montant_xof, montant_eur, taux_change_eur_xof, code_imputation, id_client, id_createur, id_operateur, date_echeance) 
             VALUES (:numero, :montant_total, :montant_xof, :montant_eur, :taux, :code_imputation, :id_client, :id_createur, :id_operateur, :date_echeance) 
             RETURNING *'
        );

        $stmt->execute([
            'numero' => $data['numero'] ?? uniqid('FACT-'),
            'montant_total' => max($montant_xof, $montant_eur), // historique
            'montant_xof' => $montant_xof,
            'montant_eur' => $montant_eur,
            'taux' => $taux,
            'code_imputation' => $data['code_imputation'] ?? null,
            'id_client' => $data['id_client'] ?? null,
            'id_createur' => $data['id_createur'] ?? null,
            'id_operateur' => $data['id_operateur'] ?? null,
            'date_echeance' => $data['date_echeance'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
