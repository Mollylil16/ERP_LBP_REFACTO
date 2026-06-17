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
        $this->createBusinessTables();
        $this->addColisageExtensions();
        $this->linkUsersToRhEmployees();
        $this->createFinanceTables();
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
            'site_id' => 'INT UNSIGNED NULL',
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
            'photo_path' => 'VARCHAR(255) NULL',
            'identity_document_path' => 'VARCHAR(255) NULL',
            'diploma_path' => 'VARCHAR(255) NULL',
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
            CREATE TABLE IF NOT EXISTS rh_document_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(60) NULL,
                is_required_onboarding TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_document_types_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " );

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_employee_documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                document_type VARCHAR(80) NOT NULL,
                child_index INT UNSIGNED NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NULL,
                size_bytes INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rh_employee_documents_employee (employee_id),
                CONSTRAINT fk_rh_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " );

        $this->addColumnIfMissing('rh_employee_documents', 'expiration_date', 'DATE NULL');

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contracts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                contract_type ENUM('CDI', 'CDD', 'Stage', 'Interim') NOT NULL DEFAULT 'CDI',
                start_date DATE NOT NULL,
                end_date DATE NULL,
                trial_end_date DATE NULL,
                base_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                status ENUM('active', 'terminated', 'renewed') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_contracts_employee (employee_id),
                KEY idx_rh_contracts_status (status),
                CONSTRAINT fk_rh_contracts_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contract_allowances (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                is_taxable TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_rh_contract_allowances_contract (contract_id),
                CONSTRAINT fk_rh_contract_allowances_contract FOREIGN KEY (contract_id) REFERENCES rh_contracts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_parameters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                year INT UNSIGNED NOT NULL,
                smig DECIMAL(10, 2) NOT NULL DEFAULT 75000.00,
                cnps_ceiling DECIMAL(12, 2) NOT NULL DEFAULT 1647315.00,
                cnps_employee_rate DECIMAL(5, 2) NOT NULL DEFAULT 3.20,
                cnps_employer_rate DECIMAL(5, 2) NOT NULL DEFAULT 7.70,
                cmu_employee_rate DECIMAL(5, 2) NOT NULL DEFAULT 2.00,
                cmu_employer_rate DECIMAL(5, 2) NOT NULL DEFAULT 2.00,
                cn_rate DECIMAL(5, 2) NOT NULL DEFAULT 1.50,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_payroll_params_year (year)
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

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_attendances (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                date DATE NOT NULL,
                check_in TIME NULL,
                check_out TIME NULL,
                total_hours DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                overtime_hours DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                status ENUM('present', 'absent', 'leave', 'holiday') NOT NULL DEFAULT 'present',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_attendances_emp_date (employee_id, date),
                CONSTRAINT fk_rh_attendances_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                month TINYINT UNSIGNED NOT NULL,
                year INT UNSIGNED NOT NULL,
                status ENUM('draft', 'validated', 'paid') NOT NULL DEFAULT 'draft',
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_payroll_campaigns_my (month, year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payslips (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                campaign_id INT UNSIGNED NOT NULL,
                base_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                overtime_pay DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                total_allowances DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                gross_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                cnps_deduction DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                cmu_deduction DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                its_deduction DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                net_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                payment_method VARCHAR(50) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_rh_payslips_emp_camp (employee_id, campaign_id),
                CONSTRAINT fk_rh_payslips_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE,
                CONSTRAINT fk_rh_payslips_campaign FOREIGN KEY (campaign_id) REFERENCES rh_payroll_campaigns(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payslip_lines (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payslip_id INT UNSIGNED NOT NULL,
                type ENUM('gain', 'deduction') NOT NULL,
                label VARCHAR(150) NOT NULL,
                base DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                rate DECIMAL(5, 2) NULL,
                amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                is_taxable TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                CONSTRAINT fk_rh_payslip_lines_payslip FOREIGN KEY (payslip_id) REFERENCES rh_payslips(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_leave_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                is_paid TINYINT(1) NOT NULL DEFAULT 1,
                deduct_from_balance TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_leave_opening_balance (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                year INT UNSIGNED NOT NULL,
                days_acquired DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_rh_leave_opening_emp_yr (employee_id, year),
                CONSTRAINT fk_rh_leave_opening_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_leave_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                leave_type_id INT UNSIGNED NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                duration_days DECIMAL(5, 2) NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                reason TEXT NULL,
                approved_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_rh_leave_requests_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE,
                CONSTRAINT fk_rh_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES rh_leave_types(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedRhStatuses();
        $this->seedRhExitReasons();
        $this->seedRhDocumentTypes();
        $this->seedRhLeaveTypes();
    }

    private function seedRhLeaveTypes(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO rh_leave_types (name, is_paid, deduct_from_balance)
            VALUES (:name, :is_paid, :deduct)
        ");

        $types = [
            ['Congé Annuel', 1, 1],
            ['Congé Maladie', 1, 0],
            ['Congé Maternité', 1, 0],
            ['Absence Non Rémunérée', 0, 0],
            ['Permission Exceptionnelle', 1, 0],
        ];

        foreach ($types as [$name, $isPaid, $deduct]) {
            $stmt->execute([
                'name' => $name,
                'is_paid' => $isPaid,
                'deduct' => $deduct,
            ]);
        }
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

    private function seedRhDocumentTypes(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO rh_document_types (name, code, is_required_onboarding, sort_order)
            VALUES (:name, :code, :required, :sort_order)
        ");

        $types = [
            ['Photo d\'identite', 'photo', 1, 10],
            ['Piece d\'identite', 'identity', 1, 20],
            ['Diplome / attestation', 'diploma', 0, 30],
            ['Extrait de naissance enfant', 'child_birth_certificate', 0, 40],
            ['Contrat signe', 'contract', 0, 50],
            ['Autre document RH', 'other', 0, 90],
        ];

        foreach ($types as [$name, $code, $required, $sortOrder]) {
            $stmt->execute([
                'name' => $name,
                'code' => $code,
                'required' => $required,
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



    private function createBusinessTables(): void
    {
        // ... (Agences / Sites)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS company_sites (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                code VARCHAR(50) NULL,
                country VARCHAR(100) NOT NULL DEFAULT 'Cote d Ivoire',
                city VARCHAR(120) NULL,
                address VARCHAR(255) NULL,
                phone VARCHAR(60) NULL,
                email VARCHAR(150) NULL,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                manager_employee_id INT UNSIGNED NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_company_sites_code (code),
                KEY idx_company_sites_country_city (country, city),
                KEY idx_company_sites_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('company_sites', 'latitude', 'DECIMAL(10,7) NULL');
        $this->addColumnIfMissing('company_sites', 'longitude', 'DECIMAL(10,7) NULL');


        // Colisage: Colis
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_colis (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tracking_number VARCHAR(50) NOT NULL,
                sender_id INT UNSIGNED NOT NULL,
                receiver_id INT UNSIGNED NOT NULL,
                departure_agency_id INT UNSIGNED NOT NULL,
                arrival_agency_id INT UNSIGNED NOT NULL,
                status ENUM('RECEPTIONNE', 'EN_PREPARATION', 'EN_TRANSIT', 'ARRIVE', 'RETIRE') NOT NULL DEFAULT 'RECEPTIONNE',
                total_weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                declared_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                total_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                retrieval_cni VARCHAR(100) NULL,
                retrieval_name VARCHAR(150) NULL,
                retrieved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_colis_tracking (tracking_number),
                CONSTRAINT fk_colis_sender FOREIGN KEY (sender_id) REFERENCES crm_clients(id) ON DELETE RESTRICT,
                CONSTRAINT fk_colis_receiver FOREIGN KEY (receiver_id) REFERENCES crm_clients(id) ON DELETE RESTRICT,
                CONSTRAINT fk_colis_dep_agency FOREIGN KEY (departure_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_colis_arr_agency FOREIGN KEY (arrival_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Colisage: Marchandises
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_marchandises (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                unit_weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_marchandises_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Colisage: Expéditions (Manifestes)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_expeditions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(100) NOT NULL,
                transport_type ENUM('AERIEN', 'MARITIME', 'TERRESTRE') NOT NULL,
                departure_agency_id INT UNSIGNED NOT NULL,
                arrival_agency_id INT UNSIGNED NOT NULL,
                departure_date DATETIME NULL,
                estimated_arrival_date DATETIME NULL,
                status ENUM('PLANIFIE', 'EN_COURS', 'ARRIVE', 'CLOTURE') NOT NULL DEFAULT 'PLANIFIE',
                driver_user_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_expedition_ref (reference),
                CONSTRAINT fk_exp_dep_agency FOREIGN KEY (departure_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_exp_arr_agency FOREIGN KEY (arrival_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Pivot: Colis -> Expéditions
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_colis_expeditions (
                colis_id INT UNSIGNED NOT NULL,
                expedition_id INT UNSIGNED NOT NULL,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (colis_id, expedition_id),
                CONSTRAINT fk_pivot_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE,
                CONSTRAINT fk_pivot_expedition FOREIGN KEY (expedition_id) REFERENCES lbp_expeditions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Flotte / Transport: Livreurs
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_livreurs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                vehicle_model VARCHAR(150) NULL,
                license_plate VARCHAR(50) NULL,
                status ENUM('DISPONIBLE', 'EN_COURSE', 'INACTIF') NOT NULL DEFAULT 'DISPONIBLE',
                last_latitude DECIMAL(10,7) NULL,
                last_longitude DECIMAL(10,7) NULL,
                last_location_updated_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_livreur_user (user_id),
                CONSTRAINT fk_livreur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tracking Colis: Suivi GPS
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_tracking_gps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NOT NULL,
                step_name VARCHAR(150) NOT NULL,
                status VARCHAR(50) NOT NULL,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                recorded_by INT NULL,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_tracking_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE,
                CONSTRAINT fk_tracking_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Entrepôts: Inventaires
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_inventaires (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agency_id INT UNSIGNED NOT NULL,
                created_by INT NOT NULL,
                status ENUM('EN_COURS', 'CLOTURE') NOT NULL DEFAULT 'EN_COURS',
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME NULL,
                notes TEXT NULL,
                CONSTRAINT fk_inventaire_agency FOREIGN KEY (agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_inventaire_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Entrepôts: Lignes d'inventaire
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_inventaire_lignes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventaire_id INT UNSIGNED NOT NULL,
                colis_id INT UNSIGNED NOT NULL,
                status ENUM('PRESENT', 'MANQUANT', 'ENDOMMAGE') NOT NULL DEFAULT 'PRESENT',
                scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                comments VARCHAR(255) NULL,
                UNIQUE KEY uniq_inv_colis (inventaire_id, colis_id),
                CONSTRAINT fk_inv_ligne_inv FOREIGN KEY (inventaire_id) REFERENCES lbp_inventaires(id) ON DELETE CASCADE,
                CONSTRAINT fk_inv_ligne_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Phase 3: Exploitation
        // Transit Douane & Achats: Prestataires
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_prestataires (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type ENUM('DOUANE', 'COMPAGNIE_AERIENNE', 'FOURNISSEUR_MATERIEL', 'AUTRE') NOT NULL,
                name VARCHAR(150) NOT NULL,
                contact_info TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Facturation & Finance: Factures prestataires
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_factures_prestataires (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                prestataire_id INT UNSIGNED NOT NULL,
                invoice_number VARCHAR(100) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                status ENUM('EN_ATTENTE', 'PAYEE', 'ANNULEE') NOT NULL DEFAULT 'EN_ATTENTE',
                due_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_facture_prestataire FOREIGN KEY (prestataire_id) REFERENCES lbp_prestataires(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Finance: Retraits Hub
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_retraits_prestataires (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                facture_id INT UNSIGNED NOT NULL,
                amount_paid DECIMAL(15,2) NOT NULL,
                currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                recorded_by INT NOT NULL,
                reference_transaction VARCHAR(100) NULL,
                CONSTRAINT fk_retrait_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures_prestataires(id) ON DELETE CASCADE,
                CONSTRAINT fk_retrait_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Logistique: Demandes de fournitures
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_demandes_fournitures (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agency_id INT UNSIGNED NOT NULL,
                requested_by INT NOT NULL,
                items_requested TEXT NOT NULL,
                status ENUM('EN_ATTENTE', 'APPROUVEE', 'LIVREE', 'REJETEE') NOT NULL DEFAULT 'EN_ATTENTE',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_fourniture_agency FOREIGN KEY (agency_id) REFERENCES company_sites(id) ON DELETE CASCADE,
                CONSTRAINT fk_fourniture_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Finance: Crédits et Compensations Inter-agences
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_credits_inter_agences (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                from_agency_id INT UNSIGNED NOT NULL,
                to_agency_id INT UNSIGNED NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                reason TEXT NULL,
                status ENUM('EN_ATTENTE', 'VALIDE') NOT NULL DEFAULT 'EN_ATTENTE',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_credit_from FOREIGN KEY (from_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_credit_to FOREIGN KEY (to_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('company_sites', 'latitude', 'DECIMAL(10,7) NULL');
        $this->addColumnIfMissing('company_sites', 'longitude', 'DECIMAL(10,7) NULL');
        $this->addColumnIfMissing('rh_employees', 'site_id', 'INT UNSIGNED NULL');
        $this->addIndexIfMissing('rh_employees', 'idx_rh_employees_site_id', 'site_id');
        $this->addForeignKeyIfMissing('rh_employees', 'fk_rh_employees_site', 'site_id', 'company_sites', 'id', 'SET NULL');

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_clients (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id INT UNSIGNED NULL,
                type ENUM('prospect','client','partner') NOT NULL DEFAULT 'prospect',
                name VARCHAR(180) NOT NULL,
                contact_name VARCHAR(160) NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(60) NULL,
                country VARCHAR(100) NULL,
                city VARCHAR(120) NULL,
                sector VARCHAR(120) NULL,
                status ENUM('new','active','dormant','lost') NOT NULL DEFAULT 'new',
                notes TEXT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_crm_clients_site (site_id),
                KEY idx_crm_clients_status (status),
                CONSTRAINT fk_crm_clients_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_opportunities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                stage VARCHAR(80) NOT NULL DEFAULT 'qualification',
                estimated_amount DECIMAL(15,2) NULL,
                currency VARCHAR(10) NOT NULL DEFAULT 'XOF',
                expected_close_date DATE NULL,
                probability TINYINT UNSIGNED NOT NULL DEFAULT 10,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_crm_opportunities_client (client_id),
                CONSTRAINT fk_crm_opportunities_client FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_interactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT UNSIGNED NOT NULL,
                user_id INT NULL,
                channel VARCHAR(60) NOT NULL DEFAULT 'appel',
                subject VARCHAR(180) NOT NULL,
                notes TEXT NULL,
                interaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                next_action_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_crm_interactions_client (client_id),
                KEY idx_crm_interactions_next_action (next_action_date),
                CONSTRAINT fk_crm_interactions_client FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(40) NULL,
                site_id INT UNSIGNED NULL,
                title VARCHAR(180) NOT NULL,
                description TEXT NULL,
                category VARCHAR(80) NULL,
                priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
                status ENUM('open','assigned','in_progress','waiting','closed','cancelled') NOT NULL DEFAULT 'open',
                requester_user_id INT NULL,
                requester_employee_id INT UNSIGNED NULL,
                assigned_service_id INT UNSIGNED NULL,
                assigned_user_id INT NULL,
                due_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_tickets_status_priority (status, priority),
                KEY idx_tickets_site (site_id),
                CONSTRAINT fk_tickets_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ticket_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT UNSIGNED NOT NULL,
                user_id INT NULL,
                message TEXT NOT NULL,
                visibility ENUM('internal','public') NOT NULL DEFAULT 'internal',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ticket_messages_ticket (ticket_id),
                CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_pages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL,
                title VARCHAR(180) NOT NULL,
                content LONGTEXT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_website_pages_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_services (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                summary VARCHAR(255) NULL,
                icon VARCHAR(80) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_leads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(80) NOT NULL DEFAULT 'site',
                name VARCHAR(180) NOT NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(60) NULL,
                subject VARCHAR(180) NULL,
                message TEXT NULL,
                status ENUM('new','processing','converted','closed') NOT NULL DEFAULT 'new',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS shipment_tracking_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(120) NOT NULL,
                requester_ip VARCHAR(80) NULL,
                result_status VARCHAR(80) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_tracking_reference (reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->createTransitExtensionTables();

        $this->seedCompanySites();
        $this->seedWebsiteContent();
    }


    private function createTransitExtensionTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS customs_files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(80) NULL,
                client_id INT UNSIGNED NULL,
                site_id INT UNSIGNED NULL,
                declaration_number VARCHAR(120) NULL,
                status VARCHAR(80) NOT NULL DEFAULT 'draft',
                eta DATE NULL,
                release_date DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_customs_files_status (status),
                KEY idx_customs_files_client (client_id),
                KEY idx_customs_files_site (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS shipments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(120) NOT NULL,
                client_id INT UNSIGNED NULL,
                site_id INT UNSIGNED NULL,
                origin_country VARCHAR(120) NULL,
                destination_country VARCHAR(120) NULL,
                current_status VARCHAR(80) NOT NULL DEFAULT 'created',
                expected_delivery_at DATETIME NULL,
                delivered_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_shipments_reference (reference),
                KEY idx_shipments_status (current_status),
                KEY idx_shipments_site (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS shipment_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                shipment_id INT UNSIGNED NOT NULL,
                status VARCHAR(80) NOT NULL,
                location_label VARCHAR(180) NULL,
                notes TEXT NULL,
                event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT NULL,
                KEY idx_shipment_events_shipment (shipment_id),
                CONSTRAINT fk_shipment_events_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(80) NOT NULL,
                client_id INT UNSIGNED NULL,
                site_id INT UNSIGNED NULL,
                type ENUM('proforma','invoice','credit_note') NOT NULL DEFAULT 'invoice',
                status VARCHAR(80) NOT NULL DEFAULT 'draft',
                amount_ht DECIMAL(15,2) NOT NULL DEFAULT 0,
                amount_ttc DECIMAL(15,2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'XOF',
                due_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_invoices_reference (reference),
                KEY idx_invoices_status (status),
                KEY idx_invoices_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS warehouses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id INT UNSIGNED NULL,
                name VARCHAR(160) NOT NULL,
                code VARCHAR(50) NULL,
                address VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_warehouses_code (code),
                KEY idx_warehouses_site (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS fleet_vehicles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id INT UNSIGNED NULL,
                plate_number VARCHAR(60) NOT NULL,
                type VARCHAR(80) NULL,
                brand VARCHAR(100) NULL,
                status VARCHAR(80) NOT NULL DEFAULT 'available',
                next_maintenance_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_fleet_vehicles_plate (plate_number),
                KEY idx_fleet_vehicles_site (site_id),
                KEY idx_fleet_vehicles_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS international_agents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(180) NOT NULL,
                country VARCHAR(120) NOT NULL,
                city VARCHAR(120) NULL,
                contact_name VARCHAR(160) NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(60) NULL,
                coverage TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_international_agents_country (country, city)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS client_portfolio_segments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(50) NULL,
                color VARCHAR(20) NULL,
                min_revenue DECIMAL(15,2) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_client_portfolio_segments_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Ajoute les colonnes manquantes pour le module Colisage (retrait, tracking, marchandises).
     */
    private function addColisageExtensions(): void
    {
        // Colonnes de retrait sur lbp_colis
        $this->addColumnIfMissing('lbp_colis', 'retrieval_name', 'VARCHAR(180) NULL');
        $this->addColumnIfMissing('lbp_colis', 'retrieval_cni', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('lbp_colis', 'retrieval_phone', 'VARCHAR(60) NULL');
        $this->addColumnIfMissing('lbp_colis', 'retrieved_at', 'DATETIME NULL');
        $this->addColumnIfMissing('lbp_colis', 'notes', 'TEXT NULL');

        // Colonne description courte du colis
        $this->addColumnIfMissing('lbp_colis', 'description', 'VARCHAR(255) NULL');

        // Livreur sur expéditions
        $this->addColumnIfMissing('lbp_expeditions', 'livreur_id', 'INT UNSIGNED NULL');
        $this->addColumnIfMissing('lbp_expeditions', 'notes', 'TEXT NULL');

        // Lien retrait → user qui a effectué le retrait
        $this->addColumnIfMissing('lbp_colis', 'retrieved_by', 'INT NULL');

        // S'assurer que lbp_marchandises existe avec toutes colonnes
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_marchandises (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                unit_weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_lbp_marchandises_colis (colis_id),
                CONSTRAINT fk_marchandises_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tracking GPS — s'assurer que les colonnes sont correctes
        $this->addColumnIfMissing('lbp_tracking_gps', 'step_name', 'VARCHAR(180) NOT NULL DEFAULT \'Etape\'');
        $this->addColumnIfMissing('lbp_tracking_gps', 'status', 'VARCHAR(50) NOT NULL DEFAULT \'INFO\'');
        $this->addColumnIfMissing('lbp_tracking_gps', 'latitude', 'DECIMAL(10,7) NULL');
        $this->addColumnIfMissing('lbp_tracking_gps', 'longitude', 'DECIMAL(10,7) NULL');

        // Logistique: vérifications colonnes lbp_prestataires
        $this->addColumnIfMissing('lbp_prestataires', 'country', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('lbp_prestataires', 'contact_name', 'VARCHAR(180) NULL');
        $this->addColumnIfMissing('lbp_prestataires', 'phone', 'VARCHAR(60) NULL');
        $this->addColumnIfMissing('lbp_prestataires', 'email', 'VARCHAR(150) NULL');
        $this->addColumnIfMissing('lbp_prestataires', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->addColumnIfMissing('lbp_prestataires', 'updated_at', 'DATETIME NULL');

        // Logistique: factures prestataires colonnes supplémentaires
        $this->addColumnIfMissing('lbp_factures_prestataires', 'lta_number', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('lbp_factures_prestataires', 'issue_date', 'DATE NULL');
        $this->addColumnIfMissing('lbp_factures_prestataires', 'amount_paid', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        $this->addColumnIfMissing('lbp_factures_prestataires', 'notes', 'TEXT NULL');
        $this->addColumnIfMissing('lbp_factures_prestataires', 'updated_at', 'DATETIME NULL');

        // Logistique: retraits hub colonnes
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'status', "ENUM('EN_ATTENTE','APPROUVE','REFUSE') NOT NULL DEFAULT 'EN_ATTENTE'");
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'approved_by', 'INT NULL');
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'approved_at', 'DATETIME NULL');
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'rejection_reason', 'TEXT NULL');
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'notes', 'TEXT NULL');
        $this->addColumnIfMissing('lbp_retraits_prestataires', 'updated_at', 'DATETIME NULL');

        // Logistique: fournitures colonnes
        $this->addColumnIfMissing('lbp_demandes_fournitures', 'validated_by', 'INT NULL');
        $this->addColumnIfMissing('lbp_demandes_fournitures', 'validated_at', 'DATETIME NULL');
        $this->addColumnIfMissing('lbp_demandes_fournitures', 'rejection_reason', 'TEXT NULL');
        $this->addColumnIfMissing('lbp_demandes_fournitures', 'delivered_at', 'DATETIME NULL');

        // Crédits inter-agences : colonnes supplémentaires
        $this->addColumnIfMissing('lbp_credits_inter_agences', 'reference_colis', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('lbp_credits_inter_agences', 'settled_at', 'DATETIME NULL');
        $this->addColumnIfMissing('lbp_credits_inter_agences', 'updated_at', 'DATETIME NULL');
    }

    private function seedCompanySites(): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO company_sites (name, code, country, city, is_active) VALUES (:name, :code, :country, :city, 1)");
        foreach ([['Siege Abidjan','ABJ-HQ','Cote d Ivoire','Abidjan'], ['Agence San Pedro','SPY','Cote d Ivoire','San Pedro'], ['Bureau international','INTL','International', null]] as [$name,$code,$country,$city]) {
            $stmt->execute(['name'=>$name,'code'=>$code,'country'=>$country,'city'=>$city]);
        }
    }

    private function seedWebsiteContent(): void
    {
        $this->pdo->exec("INSERT IGNORE INTO website_pages (slug,title,content,is_published) VALUES ('accueil','Accueil','Site vitrine transit pilote depuis ERP.',1)");
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO website_services (title, summary, sort_order) VALUES (:title,:summary,:sort_order)");
        foreach ([['Dédouanement','Formalités douanières import-export',10],['Fret & transport','Organisation des enlèvements et livraisons',20],['Suivi colis','Tracking digital des expéditions',30]] as [$title,$summary,$order]) {
            $stmt->execute(['title'=>$title,'summary'=>$summary,'sort_order'=>$order]);
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

    private function createFinanceTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_paiements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                payment_method ENUM('ESPECES', 'CHEQUE', 'VIREMENT', 'MOBILE_MONEY') NOT NULL,
                reference VARCHAR(100) NULL,
                recorded_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_paiements_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_caisses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agency_id INT UNSIGNED UNIQUE NOT NULL,
                balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                status ENUM('OUVERTE', 'FERMEE') NOT NULL DEFAULT 'FERMEE',
                updated_at DATETIME NULL,
                CONSTRAINT fk_caisses_agency FOREIGN KEY (agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_mouvements_caisse (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                caisse_id INT UNSIGNED NOT NULL,
                type ENUM('ENTREE', 'DECAISSEMENT', 'APPRO') NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                justification VARCHAR(255) NULL,
                recorded_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_mouvements_caisse FOREIGN KEY (caisse_id) REFERENCES lbp_caisses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_points_caisse (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                caisse_id INT UNSIGNED NOT NULL,
                declared_balance DECIMAL(15,2) NOT NULL,
                theoretical_balance DECIMAL(15,2) NOT NULL,
                status ENUM('EN_ATTENTE', 'VALIDE', 'REJETE') NOT NULL DEFAULT 'EN_ATTENTE',
                rejection_reason TEXT NULL,
                created_by INT NULL,
                validated_by INT NULL,
                validated_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_points_caisse FOREIGN KEY (caisse_id) REFERENCES lbp_caisses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
