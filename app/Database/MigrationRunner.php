<?php

namespace App\Database;

use App\Security\PermissionEntityRegistry;
use PDO;

class MigrationRunner
{
    private Schema $schema;


    /**
     * MigrationRunner constructor.
     *
     * @param PDO $pdo La connexion PDO à la base de données.
     */
    public function __construct(private PDO $pdo)
    {
        $this->schema = new Schema($pdo);
    }


    /**
     * Exécuter les migrations.
     */
    public function run(): void
    {
        $this->createUsersTable();
        $this->createAdminTables();
        $this->createRhTables();
        $this->linkUsersToRhEmployees();
    }


    /**
     * Crée la table "users" si elle n'existe pas, et ajoute les colonnes nécessaires.
     */
    private function createUsersTable(): void
    {
        if (!$this->schema->tableExists('users')) {
            $this->pdo->exec("
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $this->addColumnIfMissing('users', 'full_name', "VARCHAR(150) NOT NULL");
        $this->addColumnIfMissing('users', 'email', "VARCHAR(150) NOT NULL");
        $this->addColumnIfMissing('users', 'phone', "VARCHAR(30) NULL");
        $this->addColumnIfMissing('users', 'password_hash', "VARCHAR(255) NOT NULL");
        $this->addColumnIfMissing('users', 'status', "ENUM('active', 'inactive', 'blocked') DEFAULT 'active'");
        $this->addColumnIfMissing('users', 'is_admin', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('users', 'rh_employee_id', "INT UNSIGNED NULL");
        $this->addColumnIfMissing('users', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
        $this->addColumnIfMissing('users', 'updated_at', "DATETIME NULL");

        $this->addUniqueIndexIfMissing('users', 'uniq_users_email', 'email');
    }

    private function linkUsersToRhEmployees(): void
    {
        $this->addUniqueIndexIfMissing('users', 'uniq_users_rh_employee', 'rh_employee_id');
        $this->addForeignKeyIfMissing(
            'users',
            'fk_users_rh_employee',
            'rh_employee_id',
            'rh_employees',
            'id',
            'RESTRICT'
        );
    }

    /**
     * Crée le catalogue des entités protégées et les droits CRUD individuels.
     */
    private function createAdminTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS permission_entities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                module VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(255) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_permission_entities_code (code),
                KEY idx_permission_entities_module (module, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INT NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                can_view TINYINT(1) NOT NULL DEFAULT 0,
                can_create TINYINT(1) NOT NULL DEFAULT 0,
                can_update TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                PRIMARY KEY (user_id, entity_id),
                KEY idx_user_permissions_entity (entity_id),
                CONSTRAINT fk_user_permissions_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_permissions_entity
                    FOREIGN KEY (entity_id) REFERENCES permission_entities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedPermissionEntities();
    }

    private function seedPermissionEntities(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO permission_entities (code, module, name, description, sort_order)
            VALUES (:code, :module, :name, :description, :sort_order)
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                name = VALUES(name),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                is_active = 1
        ");

        foreach (PermissionEntityRegistry::all() as $code => $entity) {
            $stmt->execute([
                'code' => $code,
                'module' => $entity['module'],
                'name' => $entity['name'],
                'description' => $entity['description'],
                'sort_order' => $entity['sort_order'],
            ]);
        }

        $this->pdo->exec("
            UPDATE permission_entities
            SET is_active = 0
            WHERE code IN (
                'admin.dashboard', 'admin.users', 'admin.permissions',
                'rh.dashboard', 'rh.personnel', 'rh.mutations', 'rh.movements'
            )
        ");
    }

    /**
     * Cree le socle de donnees partage par les ecrans RH.
     */
    private function createRhTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_services (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(30) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_services_name (name),
                UNIQUE KEY uniq_rh_services_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_functions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(30) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_functions_name (name),
                UNIQUE KEY uniq_rh_functions_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_statuses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(30) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_statuses_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_employees (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_number VARCHAR(50) NULL,
                full_name VARCHAR(180) NOT NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(40) NULL,
                service_id INT UNSIGNED NULL,
                function_id INT UNSIGNED NULL,
                status_id INT UNSIGNED NULL,
                hire_date DATE NULL,
                start_date DATE NULL,
                exit_date DATE NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_employees_number (employee_number),
                KEY idx_rh_employees_service (service_id),
                KEY idx_rh_employees_function (function_id),
                KEY idx_rh_employees_status (status_id),
                KEY idx_rh_employees_active (is_active),
                CONSTRAINT fk_rh_employees_service FOREIGN KEY (service_id) REFERENCES rh_services(id) ON DELETE SET NULL,
                CONSTRAINT fk_rh_employees_function FOREIGN KEY (function_id) REFERENCES rh_functions(id) ON DELETE SET NULL,
                CONSTRAINT fk_rh_employees_status FOREIGN KEY (status_id) REFERENCES rh_statuses(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $employeeColumns = [
            'gender' => "ENUM('male', 'female', 'other') NULL",
            'birth_date' => 'DATE NULL',
            'birth_place' => 'VARCHAR(150) NULL',
            'marital_status' => 'VARCHAR(80) NULL',
            'address' => 'VARCHAR(255) NULL',
            'site' => 'VARCHAR(150) NULL',
            'cni_number' => 'VARCHAR(100) NULL',
            'cnps_number' => 'VARCHAR(100) NULL',
            'contract_duration_months' => 'INT UNSIGNED NULL',
            'father_name' => 'VARCHAR(180) NULL',
            'father_phone' => 'VARCHAR(40) NULL',
            'mother_name' => 'VARCHAR(180) NULL',
            'mother_phone' => 'VARCHAR(40) NULL',
            'emergency_contact_name' => 'VARCHAR(180) NULL',
            'emergency_contact_phone' => 'VARCHAR(40) NULL',
            'children_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'exit_reason_id' => 'INT UNSIGNED NULL',
            'exit_notes' => 'TEXT NULL',
        ];
        foreach ($employeeColumns as $column => $definition) {
            $this->addColumnIfMissing('rh_employees', $column, $definition);
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_exit_reasons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(180) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_exit_reasons_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_employee_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                event_date DATE NOT NULL,
                title VARCHAR(180) NOT NULL,
                description TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rh_employee_history_employee (employee_id),
                KEY idx_rh_employee_history_date (event_date),
                CONSTRAINT fk_rh_employee_history_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_employee_mutations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                effective_date DATE NOT NULL,
                previous_service_id INT UNSIGNED NULL,
                new_service_id INT UNSIGNED NULL,
                previous_function_id INT UNSIGNED NULL,
                new_function_id INT UNSIGNED NULL,
                previous_status_id INT UNSIGNED NULL,
                new_status_id INT UNSIGNED NULL,
                previous_site VARCHAR(150) NULL,
                new_site VARCHAR(150) NULL,
                reason TEXT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rh_employee_mutations_employee (employee_id),
                KEY idx_rh_employee_mutations_date (effective_date),
                CONSTRAINT fk_rh_employee_mutations_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedRhStatuses();
        $this->seedRhExitReasons();
    }

    private function seedRhStatuses(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO rh_statuses (name, code, sort_order)
            VALUES (:name, :code, :sort_order)
        ");

        $statuses = [
            ['CDI', 'cdi', 10],
            ['CDD', 'cdd', 20],
            ['Stage', 'stage', 30],
            ['Consultant', 'consultant', 40],
            ['Prestataire', 'prestataire', 50],
        ];

        foreach ($statuses as [$name, $code, $sortOrder]) {
            $stmt->execute([
                'name' => $name,
                'code' => $code,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function seedRhExitReasons(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO rh_exit_reasons (name, sort_order)
            VALUES (:name, :sort_order)
        ");

        foreach (['Fin de contrat', 'Demission', 'Licenciement', 'Retraite', 'Mutation externe', 'Autre'] as $index => $name) {
            $stmt->execute([
                'name' => $name,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }


    /**
     * Méthodes utilitaires pour ajouter des colonnes, index et clés étrangères si elles n'existent pas déjà.
     */
    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->schema->columnExists($table, $column)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!$this->schema->indexExists($table, $index)) {
            $this->pdo->exec("CREATE INDEX {$index} ON {$table} ({$columns})");
        }
    }

    private function addUniqueIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!$this->schema->indexExists($table, $index)) {
            $this->pdo->exec("CREATE UNIQUE INDEX {$index} ON {$table} ({$columns})");
        }
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete = 'CASCADE'
    ): void {
        if (!$this->schema->foreignKeyExists($table, $constraint)) {
            $this->pdo->exec("
                ALTER TABLE {$table}
                ADD CONSTRAINT {$constraint}
                FOREIGN KEY ({$column})
                REFERENCES {$referenceTable}({$referenceColumn})
                ON DELETE {$onDelete}
            ");
        }
    }
}
