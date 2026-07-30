<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Finance\FinanceController;
use App\Models\Database;
use Tests\Support\DatabaseTestCase;

final class FinanceControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inject in-memory SQLite connection into Database::$connection
        $sqlitePdo = $this->sqlite();
        
        // Create necessary tables for repository constructors
        $sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_prestataires (id INTEGER PRIMARY KEY, name TEXT, zone_regionale_id INTEGER)");
        $sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_plan_comptable (compte TEXT PRIMARY KEY, libelle TEXT, type TEXT)");
        
        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue(null, $sqlitePdo);
    }

    protected function tearDown(): void
    {
        // Reset Database::$connection
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
}
