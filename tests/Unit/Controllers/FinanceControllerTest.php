<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Finance\FinanceController;
use App\Models\Database;
use Tests\Support\DatabaseTestCase;

final class FinanceControllerTest extends DatabaseTestCase
{
    private \PDO $sqlitePdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePdo = $this->sqlite();
        
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_prestataires (id INTEGER PRIMARY KEY, name TEXT, zone_regionale_id INTEGER)");
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_plan_comptable (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, libelle TEXT, classe INTEGER)");
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_ecritures_comptables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_ecriture TEXT NOT NULL,
            journal TEXT NOT NULL,
            compte_debit TEXT NOT NULL,
            compte_credit TEXT NOT NULL,
            montant REAL NOT NULL,
            devise TEXT NOT NULL DEFAULT 'XOF',
            taux_change REAL NULL,
            piece_justificative_id TEXT NULL,
            libelle TEXT NOT NULL,
            lettrage TEXT NULL,
            created_at TEXT NULL
        )");
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS company_sites (id INTEGER PRIMARY KEY, name TEXT)");
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, password_hash TEXT, status TEXT, is_admin INTEGER)");
        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_etats_journaliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agence_id INTEGER,
            chef_agence_id INTEGER,
            date_jour TEXT,
            nb_colis_enregistres INTEGER,
            nb_factures_emises INTEGER,
            total_facture_xof REAL,
            total_facture_eur REAL,
            total_encaisse_xof REAL,
            total_encaisse_eur REAL,
            total_restant_du_xof REAL,
            total_restant_du_eur REAL,
            solde_caisse_agence_xof REAL,
            solde_caisse_agence_eur REAL,
            statut TEXT,
            date_soumission TEXT,
            solde_physique_declare REAL,
            ecart_caisse REAL,
            explication_ecart TEXT,
            consolide_par_id INTEGER,
            date_consolidation TEXT
        )");

        $this->sqlitePdo->exec("INSERT INTO users (id, full_name, email, password_hash, status, is_admin) VALUES (1, 'Comptable ERP', 'comptable@erp.local', 'hash', 'active', 1)");

        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->sqlitePdo);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    public function test_finance_controller_can_be_instantiated(): void
    {
        $controller = new FinanceController();
        self::assertInstanceOf(FinanceController::class, $controller);
    }

    public function test_export_syscohada_generates_valid_csv(): void
    {
        $_SESSION['auth_user_id'] = 1;

        $controller = new FinanceController();

        ob_start();
        $controller->exportSyscohada();
        $output = ob_get_clean();

        self::assertStringContainsString('Date;Journal;', (string)$output);
        self::assertStringContainsString('Compte Débit', (string)$output);
    }
}
