<?php

namespace App\Repositories\Finance;

use App\Models\Finance\EcritureComptable;
use PDO;

class ComptabiliteRepository
{
    public function __construct(private PDO $pdo) {}

    public function createEcriture(EcritureComptable $ecriture): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_ecritures_comptables (
                date_ecriture, journal, compte_debit, compte_credit, montant, devise, taux_change,
                piece_justificative_id, libelle, lettrage, created_at
            ) VALUES (
                :date_ecriture, :journal, :compte_debit, :compte_credit, :montant, :devise, :taux_change,
                :piece_justificative_id, :libelle, :lettrage, NOW()
            )
        ");
        $stmt->execute([
            'date_ecriture' => $ecriture->dateEcriture,
            'journal' => $ecriture->journal,
            'compte_debit' => $ecriture->compteDebit,
            'compte_credit' => $ecriture->compteCredit,
            'montant' => $ecriture->montant,
            'devise' => $ecriture->devise,
            'taux_change' => $ecriture->tauxChange,
            'piece_justificative_id' => $ecriture->pieceJustificativeId,
            'libelle' => $ecriture->libelle,
            'lettrage' => $ecriture->lettrage,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getEcritures(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (($filters['journal'] ?? '') !== '') {
            $conditions[] = 'journal = :journal';
            $params['journal'] = $filters['journal'];
        }
        if (($filters['compte'] ?? '') !== '') {
            $conditions[] = '(compte_debit = :compte OR compte_credit = :compte)';
            $params['compte'] = $filters['compte'];
        }
        if (($filters['date_debut'] ?? '') !== '') {
            $conditions[] = 'date_ecriture >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }
        if (($filters['date_fin'] ?? '') !== '') {
            $conditions[] = 'date_ecriture <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_ecritures_comptables 
            {$where} 
            ORDER BY date_ecriture DESC, id DESC
        ");
        $stmt->execute($params);
        return array_map(fn($row) => $this->mapToEcriture($row), $stmt->fetchAll() ?: []);
    }

    public function getPlanComptable(): array
    {
        return $this->pdo->query("SELECT * FROM lbp_plan_comptable ORDER BY code ASC")->fetchAll() ?: [];
    }

    public function seedDefaultPlanComptable(): void
    {
        $accounts = [
            ['101000', 'Capital social', 1],
            ['411100', 'Clients - Ventes nationales', 4],
            ['411200', 'Clients - Ventes internationales', 4],
            ['401100', 'Fournisseurs - Dettes locales', 4],
            ['571100', 'Caisse Agence - Espèces', 5],
            ['571200', 'Caisse Principale - Centralisé', 5],
            ['521100', 'Banque locale', 5],
            ['701100', 'Ventes de fret aérien', 7],
            ['701200', 'Ventes de fret maritime', 7],
            ['601100', 'Achats de prestations transporteurs', 6],
            ['601200', 'Achats de fournitures et emballages', 6],
            ['585000', 'Virement interne de fonds', 5],
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_plan_comptable (code, libelle, classe)
            VALUES (:code, :libelle, :classe)
            ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), classe = VALUES(classe)
        ");

        foreach ($accounts as $a) {
            $stmt->execute([
                'code' => $a[0],
                'libelle' => $a[1],
                'classe' => $a[2],
            ]);
        }
    }

    private function mapToEcriture(array $row): EcritureComptable
    {
        return new EcritureComptable(
            id: (int) $row['id'],
            dateEcriture: $row['date_ecriture'],
            journal: $row['journal'],
            compteDebit: $row['compte_debit'],
            compteCredit: $row['compte_credit'],
            montant: (float) $row['montant'],
            devise: $row['devise'],
            tauxChange: isset($row['taux_change']) ? (float) $row['taux_change'] : null,
            pieceJustificativeId: $row['piece_justificative_id'],
            libelle: $row['libelle'],
            lettrage: $row['lettrage'] ?? null,
            createdAt: $row['created_at']
        );
    }
}
