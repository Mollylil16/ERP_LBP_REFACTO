<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\CallCenter\CallCenterController;
use App\Models\Database;
use Tests\Support\DatabaseTestCase;

final class CallCenterControllerTest extends DatabaseTestCase
{
    private \PDO $sqlitePdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePdo = $this->sqlite();
        
        // Create necessary tables for CallCenterController
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_colis (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                numero_tracking TEXT NOT NULL,
                expediteur_id INTEGER NOT NULL,
                destinataire_id INTEGER NOT NULL,
                statut TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NULL
            )
        ");
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_call_center_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                colis_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                type_notification TEXT NOT NULL,
                duree_appel INTEGER NULL,
                description TEXT NULL,
                satisfaction_score INTEGER NULL,
                agent_id INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_call_center_appels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                agent_id INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_call_center_litiges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                agent_id INTEGER NOT NULL,
                statut TEXT NOT NULL
            )
        ");
        $this->sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0
            )
        ");

        // Inject connection
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

    public function test_controller_can_be_instantiated(): void
    {
        $controller = new CallCenterController();
        self::assertInstanceOf(CallCenterController::class, $controller);
    }
}
