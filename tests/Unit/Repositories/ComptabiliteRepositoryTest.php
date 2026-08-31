<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Finance\EcritureComptable;
use App\Repositories\Finance\ComptabiliteRepository;
use Tests\Support\DatabaseTestCase;

final class ComptabiliteRepositoryTest extends DatabaseTestCase
{
    public function test_create_and_get_ecritures(): void
    {
        $pdo = $this->setupDatabase();
        $repo = new ComptabiliteRepository($pdo);

        $ecriture = new EcritureComptable(
            id: null,
            dateEcriture: '2026-08-31',
            journal: 'ventes',
            compteDebit: '411100',
            compteCredit: '701100',
            montant: 150000.0,
            devise: 'XOF',
            tauxChange: null,
            pieceJustificativeId: 'FAC-2026-001',
            libelle: 'Facture Fret Aérien #101'
        );

        $id = $repo->createEcriture($ecriture);
        self::assertGreaterThan(0, $id);

        $found = $repo->findById($id);
        self::assertNotNull($found);
        self::assertSame('411100', $found->compteDebit);
        self::assertSame('701100', $found->compteCredit);
        self::assertSame(150000.0, $found->montant);
    }

    public function test_get_balance_des_comptes_calculates_debit_credit_and_net_balances(): void
    {
        $pdo = $this->setupDatabase();
        $repo = new ComptabiliteRepository($pdo);

        $repo->seedDefaultPlanComptable();

        // 1. Vente 200 000 XOF
        $repo->createEcriture(new EcritureComptable(
            id: null,
            dateEcriture: '2026-08-01',
            journal: 'ventes',
            compteDebit: '411100',
            compteCredit: '701100',
            montant: 200000.0,
            devise: 'XOF',
            tauxChange: null,
            pieceJustificativeId: 'FAC-001',
            libelle: 'Vente Fret'
        ));

        // 2. Encaissement Client 150 000 XOF
        $repo->createEcriture(new EcritureComptable(
            id: null,
            dateEcriture: '2026-08-02',
            journal: 'caisses',
            compteDebit: '571100',
            compteCredit: '411100',
            montant: 150000.0,
            devise: 'XOF',
            tauxChange: null,
            pieceJustificativeId: 'ENC-001',
            libelle: 'Règlement Caisse Client'
        ));

        $balance = $repo->getBalanceDesComptes();
        self::assertNotEmpty($balance);

        $account411 = current(array_filter($balance, fn($b) => $b['code'] === '411100'));
        self::assertNotFalse($account411);
        self::assertSame(200000.0, $account411['total_debit']);
        self::assertSame(150000.0, $account411['total_credit']);
        self::assertSame(50000.0, $account411['solde_debiteur']);
        self::assertSame(0.0, $account411['solde_crediteur']);
    }

    public function test_contre_passer_ecriture_creates_reversal_entry_and_letters_original(): void
    {
        $pdo = $this->setupDatabase();
        $repo = new ComptabiliteRepository($pdo);

        $id = $repo->createEcriture(new EcritureComptable(
            id: null,
            dateEcriture: '2026-08-10',
            journal: 'OD',
            compteDebit: '601100',
            compteCredit: '401100',
            montant: 75000.0,
            devise: 'XOF',
            tauxChange: null,
            pieceJustificativeId: 'OD-ERR',
            libelle: 'Dépense erronée'
        ));

        $newId = $repo->contrePasserEcriture($id, 'Erreur de saisie compte');
        self::assertGreaterThan(0, $newId);

        $originale = $repo->findById($id);
        $contre = $repo->findById($newId);

        self::assertSame('EXT', $originale->lettrage);
        self::assertSame('401100', $contre->compteDebit);
        self::assertSame('601100', $contre->compteCredit);
        self::assertSame(75000.0, $contre->montant);
        self::assertStringContainsString('Contre-passation', $contre->libelle);
    }

    public function test_add_compte_comptable_persists_new_account(): void
    {
        $pdo = $this->setupDatabase();
        $repo = new ComptabiliteRepository($pdo);

        $repo->addCompteComptable('601300', 'Achats de carburant flotte', 6);

        $plan = $repo->getPlanComptable();
        $found = array_filter($plan, fn($p) => $p['code'] === '601300');

        self::assertCount(1, $found);
    }

    private function setupDatabase(): \PDO
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE lbp_ecritures_comptables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_ecriture TEXT NOT NULL,
            journal TEXT NOT NULL,
            compte_debit TEXT NOT NULL,
            compte_credit TEXT NOT NULL,
            montant REAL NOT NULL,
            devise TEXT NOT NULL DEFAULT "XOF",
            taux_change REAL NULL,
            piece_justificative_id TEXT NULL,
            libelle TEXT NOT NULL,
            lettrage TEXT NULL,
            created_at TEXT NULL
        )');

        $pdo->exec('CREATE TABLE lbp_plan_comptable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            libelle TEXT NOT NULL,
            classe INTEGER NOT NULL
        )');

        return $pdo;
    }
}
