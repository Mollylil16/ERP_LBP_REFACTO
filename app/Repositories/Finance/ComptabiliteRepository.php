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
                :piece_justificative_id, :libelle, :lettrage, :created_at
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
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?EcritureComptable
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_ecritures_comptables WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapToEcriture($row) : null;
    }

    public function lettrerEcritures(array $ids, string $lettrageCode): void
    {
        if ($ids === []) return;
        $in = implode(',', array_map('intval', $ids));
        $stmt = $this->pdo->prepare("UPDATE lbp_ecritures_comptables SET lettrage = :lettrage WHERE id IN ({$in})");
        $stmt->execute(['lettrage' => strtoupper(trim($lettrageCode))]);
    }

    public function contrePasserEcriture(int $id, string $motif = ''): int
    {
        $originale = $this->findById($id);
        if (!$originale) {
            throw new \InvalidArgumentException("Écriture introuvable pour contre-passation.");
        }

        $libelleContre = "Contre-passation de l'écriture #" . $originale->id . " (" . $originale->libelle . ")";
        if ($motif !== '') {
            $libelleContre .= " — Motif: " . $motif;
        }

        // L'écriture inverse permute compte_debit et compte_credit
        $inversée = new EcritureComptable(
            id: null,
            dateEcriture: date('Y-m-d'),
            journal: $originale->journal,
            compteDebit: $originale->compteCredit,
            compteCredit: $originale->compteDebit,
            montant: $originale->montant,
            devise: $originale->devise,
            tauxChange: $originale->tauxChange,
            pieceJustificativeId: 'EXT-' . ($originale->pieceJustificativeId ?? $originale->id),
            libelle: $libelleContre,
            lettrage: 'EXT'
        );

        $newId = $this->createEcriture($inversée);

        // Marquer l'écriture originale avec le lettrage 'EXT'
        $this->lettrerEcritures([$originale->id], 'EXT');

        return $newId;
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

    public function getBalanceDesComptes(array $filters = []): array
    {
        $plan = $this->getPlanComptable();
        $ecritures = $this->getEcritures($filters);

        $balance = [];
        foreach ($plan as $p) {
            $code = (string) $p['code'];
            $balance[$code] = [
                'code' => $code,
                'libelle' => (string) $p['libelle'],
                'classe' => (int) $p['classe'],
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'solde_debiteur' => 0.0,
                'solde_crediteur' => 0.0,
            ];
        }

        foreach ($ecritures as $e) {
            $deb = $e->compteDebit;
            $cred = $e->compteCredit;
            $m = (float) $e->montant;

            if (!isset($balance[$deb])) {
                $balance[$deb] = [
                    'code' => $deb,
                    'libelle' => 'Compte ' . $deb,
                    'classe' => (int) substr($deb, 0, 1),
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                    'solde_debiteur' => 0.0,
                    'solde_crediteur' => 0.0,
                ];
            }
            if (!isset($balance[$cred])) {
                $balance[$cred] = [
                    'code' => $cred,
                    'libelle' => 'Compte ' . $cred,
                    'classe' => (int) substr($cred, 0, 1),
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                    'solde_debiteur' => 0.0,
                    'solde_crediteur' => 0.0,
                ];
            }

            $balance[$deb]['total_debit'] += $m;
            $balance[$cred]['total_credit'] += $m;
        }

        foreach ($balance as $code => &$row) {
            $diff = $row['total_debit'] - $row['total_credit'];
            if ($diff > 0) {
                $row['solde_debiteur'] = $diff;
            } elseif ($diff < 0) {
                $row['solde_crediteur'] = abs($diff);
            }
        }

        // Filtrer les comptes inactifs si souhaité, trier par code
        ksort($balance);
        return array_values($balance);
    }

    public function addCompteComptable(string $code, string $libelle, int $classe): void
    {
        $code = trim($code);
        $stmtCheck = $this->pdo->prepare("SELECT id FROM lbp_plan_comptable WHERE code = :code LIMIT 1");
        $stmtCheck->execute(['code' => $code]);
        if ($stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("UPDATE lbp_plan_comptable SET libelle = :libelle, classe = :classe WHERE code = :code");
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO lbp_plan_comptable (code, libelle, classe) VALUES (:code, :libelle, :classe)");
        }
        $stmt->execute([
            'code' => $code,
            'libelle' => trim($libelle),
            'classe' => $classe,
        ]);
    }

    public function getPlanComptable(): array
    {
        return $this->pdo->query("SELECT * FROM lbp_plan_comptable ORDER BY code ASC")->fetchAll() ?: [];
    }

    public function seedDefaultPlanComptable(): void
    {
        $accounts = [
            ['101000', 'Capital social', 1],
            ['131000', 'Subventions d\'équipement', 1],
            ['162000', 'Emprunts et dettes auprès des établissements de crédit', 1],
            ['211000', 'Terrains & Aménagements', 2],
            ['241000', 'Matériel de transport & Flotte', 2],
            ['244000', 'Matériel informatique et mobilier', 2],
            ['401100', 'Fournisseurs - Dettes locales', 4],
            ['401200', 'Fournisseurs - Transporteurs & Fret', 4],
            ['411100', 'Clients - Ventes nationales (LB-CI)', 4],
            ['411200', 'Clients - Ventes internationales (LB-FR / CA-CI)', 4],
            ['443100', 'TVA facturée sur ventes', 4],
            ['445100', 'TVA récupérable sur achats', 4],
            ['421100', 'Personnel - Rémunérations dues', 4],
            ['431100', 'Sécurité Sociale (CNPS / IPRES)', 4],
            ['521100', 'Banque locale principaux comptes', 5],
            ['521200', 'Banque internationale (Transferts EUR)', 5],
            ['571100', 'Caisse Agence - Espèces tiroir', 5],
            ['571200', 'Caisse Principale - Centralisé', 5],
            ['572100', 'Comptes Mobile Money (Wave / Orange Money)', 5],
            ['585000', 'Virement interne de fonds', 5],
            ['601100', 'Achats de prestations transporteurs & Douane', 6],
            ['601200', 'Achats de fournitures, carton & emballages LBP', 6],
            ['612000', 'Transports de plis et carburant flotte', 6],
            ['622000', 'Frais de télécoms & internet', 6],
            ['625000', 'Prime d\'assurance transport & fret', 6],
            ['631000', 'Impôts, taxes et droits d\'enregistrement', 6],
            ['641100', 'Salaires et traitements du personnel', 6],
            ['701100', 'Ventes de prestations Fret Aérien', 7],
            ['701200', 'Ventes de prestations Fret Maritime', 7],
            ['706100', 'Produits annexes - Emballages & Stockage', 7],
            ['771000', 'Escomptes obtenus & Produits financiers', 7],
        ];

        foreach ($accounts as $a) {
            $this->addCompteComptable($a[0], $a[1], (int)$a[2]);
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
