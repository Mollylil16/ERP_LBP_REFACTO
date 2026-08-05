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
        $this->createSystemTestTables();
        $this->createModuleMaintenanceTable();
        $this->linkUsersToRhEmployees();
        $this->createColisageTables();
        $this->createLbpUnifiedFlowTables();
        $this->createLogistiqueRayonsAndSettingsTables();
        $this->createCallCenterTables();
        $this->createColisageOperationRefactoTables();
        $this->createMissingProductionTables();
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



    /**
     * Crée la table d'historique du module Santé & Tests.
     */
    private function createSystemTestTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_test_runs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scope VARCHAR(30) NOT NULL,
                module VARCHAR(80) NOT NULL DEFAULT 'application',
                status ENUM('passed','warning','failed') NOT NULL DEFAULT 'warning',
                score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                payload JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_system_test_runs_scope_created (scope, created_at),
                KEY idx_system_test_runs_module_created (module, created_at),
                KEY idx_system_test_runs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createModuleMaintenanceTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS module_maintenance (
                module_slug VARCHAR(80) PRIMARY KEY,
                is_maintenance TINYINT(1) NOT NULL DEFAULT 0,
                reason VARCHAR(500) NULL,
                updated_by INT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_module_maintenance_status (is_maintenance),
                CONSTRAINT fk_module_maintenance_user
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
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

        $this->createRhLifecycleTables();
        $this->seedRhStatuses();
        $this->seedRhExitReasons();
        $this->seedRhDocumentTypes();
    }

    private function createRhLifecycleTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contracts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                contract_type VARCHAR(50) NOT NULL,
                reference VARCHAR(80) NULL,
                start_date DATE NOT NULL,
                end_date DATE NULL,
                trial_start_date DATE NULL,
                trial_end_date DATE NULL,
                trial_status ENUM('not_applicable','pending','confirmed','renewed','terminated') NOT NULL DEFAULT 'pending',
                status ENUM('draft','approval','active','expired','terminated') NOT NULL DEFAULT 'draft',
                alert_days VARCHAR(30) NOT NULL DEFAULT '30,15,7',
                signed_document_path VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_contracts_employee (employee_id),
                KEY idx_rh_contracts_dates (end_date, trial_end_date),
                CONSTRAINT fk_rh_contracts_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contract_renewals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id INT UNSIGNED NOT NULL,
                previous_end_date DATE NULL,
                new_end_date DATE NOT NULL,
                reason TEXT NULL,
                amendment_reference VARCHAR(80) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rh_contract_renewals_contract (contract_id),
                CONSTRAINT fk_rh_contract_renewals_contract FOREIGN KEY (contract_id) REFERENCES rh_contracts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_assignments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                project_code VARCHAR(80) NULL,
                manager_employee_id INT UNSIGNED NULL,
                site_id INT UNSIGNED NULL,
                start_date DATE NOT NULL,
                end_date DATE NULL,
                status ENUM('draft','approval','active','completed','cancelled') NOT NULL DEFAULT 'draft',
                notes TEXT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_assignments_employee (employee_id),
                KEY idx_rh_assignments_status_dates (status, start_date, end_date),
                CONSTRAINT fk_rh_assignments_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE,
                CONSTRAINT fk_rh_assignments_manager FOREIGN KEY (manager_employee_id) REFERENCES rh_employees(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_evaluations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                evaluator_employee_id INT UNSIGNED NULL,
                evaluation_type ENUM('annual','semiannual','trial_end','assignment_end','professional') NOT NULL,
                period_label VARCHAR(100) NOT NULL,
                due_date DATE NULL,
                technical_score DECIMAL(5,2) NULL,
                behavioral_score DECIMAL(5,2) NULL,
                objectives_score DECIMAL(5,2) NULL,
                attendance_score DECIMAL(5,2) NULL,
                overall_score DECIMAL(5,2) NULL,
                employee_comments TEXT NULL,
                manager_comments TEXT NULL,
                improvement_plan TEXT NULL,
                recommendation TEXT NULL,
                status ENUM('draft','self_review','manager_review','hr_review','completed') NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_evaluations_employee (employee_id),
                KEY idx_rh_evaluations_status_due (status, due_date),
                CONSTRAINT fk_rh_evaluations_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_training_sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                training_type ENUM('internal','external','mandatory','job') NOT NULL DEFAULT 'internal',
                provider VARCHAR(180) NULL,
                start_date DATE NOT NULL,
                end_date DATE NULL,
                budget DECIMAL(15,2) NOT NULL DEFAULT 0,
                capacity INT UNSIGNED NULL,
                status ENUM('planned','approval','open','completed','cancelled') NOT NULL DEFAULT 'planned',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_training_sessions_status_date (status, start_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_training_enrollments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id INT UNSIGNED NOT NULL,
                employee_id INT UNSIGNED NOT NULL,
                status ENUM('requested','manager_approved','hr_approved','direction_approved','rejected','attended','absent') NOT NULL DEFAULT 'requested',
                attendance_rate DECIMAL(5,2) NULL,
                post_score DECIMAL(5,2) NULL,
                certificate_path VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_training_enrollment (session_id, employee_id),
                CONSTRAINT fk_rh_training_enrollment_session FOREIGN KEY (session_id) REFERENCES rh_training_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_rh_training_enrollment_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_workflow_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                process_type VARCHAR(60) NOT NULL,
                subject_type VARCHAR(60) NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                employee_id INT UNSIGNED NULL,
                current_step VARCHAR(60) NOT NULL DEFAULT 'manager',
                status ENUM('draft','pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
                payload_json LONGTEXT NULL,
                requested_by INT NULL,
                decided_by INT NULL,
                decided_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_workflow_process_status (process_type, status),
                KEY idx_rh_workflow_employee (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_objectives (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                description TEXT NULL,
                target_value VARCHAR(120) NULL,
                due_date DATE NULL,
                progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM('draft','active','completed','cancelled') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_rh_objectives_employee_status (employee_id, status),
                CONSTRAINT fk_rh_objectives_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_disciplinary_actions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                action_type ENUM('warning','reprimand','suspension','other') NOT NULL,
                action_date DATE NOT NULL,
                reason TEXT NOT NULL,
                decision TEXT NULL,
                status ENUM('draft','notified','closed','cancelled') NOT NULL DEFAULT 'draft',
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rh_disciplinary_employee_date (employee_id, action_date),
                CONSTRAINT fk_rh_disciplinary_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_legal_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                request_type ENUM('leave','absence','salary_advance','attendance_correction','document','other') NOT NULL,
                reference VARCHAR(50) NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                amount DECIMAL(15,2) NULL,
                reason TEXT NOT NULL,
                attachment_path VARCHAR(255) NULL,
                assigned_team VARCHAR(30) NOT NULL DEFAULT 'rh',
                current_step VARCHAR(40) NOT NULL DEFAULT 'manager',
                status ENUM('draft','submitted','manager_approved','hr_approved','direction_approved','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
                decision_comment TEXT NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                decided_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_employee_request_reference (reference),
                KEY idx_employee_requests_employee (employee_id, submitted_at),
                KEY idx_employee_requests_status (status, current_step),
                CONSTRAINT fk_employee_requests_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            ALTER TABLE employee_legal_requests
            MODIFY request_type ENUM('leave','absence','lateness','salary_advance','attendance_correction','document','other') NOT NULL
        ");
        $this->addColumnIfMissing('employee_legal_requests', 'metadata_json', 'LONGTEXT NULL');
        $this->addColumnIfMissing('employee_legal_requests', 'attachment_original_name', 'VARCHAR(255) NULL');
        $this->addColumnIfMissing('employee_legal_requests', 'attachment_mime_type', 'VARCHAR(120) NULL');
        $this->addColumnIfMissing('employee_legal_requests', 'attachment_size_bytes', 'INT UNSIGNED NULL');

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_request_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                step VARCHAR(40) NULL,
                status VARCHAR(40) NOT NULL,
                comment TEXT NULL,
                actor_user_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_employee_request_events_request (request_id, created_at),
                CONSTRAINT fk_employee_request_events_request FOREIGN KEY (request_id) REFERENCES employee_legal_requests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_explanation_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                subject VARCHAR(180) NOT NULL,
                facts TEXT NOT NULL,
                incident_date DATE NULL,
                response_due_date DATE NULL,
                response_due_days INT NULL,
                incident_period VARCHAR(180) NULL,
                incident_location VARCHAR(180) NULL,
                is_dg_copy TINYINT(1) NOT NULL DEFAULT 0,
                general_context TEXT NULL,
                expected_explanations TEXT NULL,
                additional_elements TEXT NULL,
                employee_response TEXT NULL,
                response_attachment_path VARCHAR(255) NULL,
                status ENUM('pending_response','responded','complement_requested','closed','cancelled') NOT NULL DEFAULT 'pending_response',
                requested_by INT NULL,
                responded_at DATETIME NULL,
                closed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_explanation_employee_status (employee_id, status),
                CONSTRAINT fk_explanation_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_attendance_daily (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                attendance_date DATE NOT NULL,
                check_in_time TIME NULL,
                check_out_time TIME NULL,
                attendance_status ENUM('present','absent','half_day','mission','conge','rest') NOT NULL DEFAULT 'present',
                worked_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
                overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
                source VARCHAR(50) NOT NULL DEFAULT 'manual',
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_attendance_employee_date (employee_id, attendance_date),
                KEY idx_attendance_date_status (attendance_date, attendance_status),
                CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_leave_opening_balance (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                leave_year SMALLINT UNSIGNED NOT NULL,
                opening_days DECIMAL(6,2) NOT NULL DEFAULT 0,
                acquired_days DECIMAL(6,2) NOT NULL DEFAULT 0,
                taken_days DECIMAL(6,2) NOT NULL DEFAULT 0,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_leave_balance_employee_year (employee_id, leave_year),
                CONSTRAINT fk_leave_balance_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('rh_leave_opening_balance', 'leave_year', 'SMALLINT UNSIGNED NULL');
        $this->addColumnIfMissing('rh_leave_opening_balance', 'opening_days', 'DECIMAL(6,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('rh_leave_opening_balance', 'acquired_days', 'DECIMAL(6,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('rh_leave_opening_balance', 'taken_days', 'DECIMAL(6,2) NOT NULL DEFAULT 0');
        if ($this->schema->columnExists('rh_leave_opening_balance', 'year')) {
            $this->pdo->exec("UPDATE rh_leave_opening_balance SET leave_year = COALESCE(leave_year, `year`)");
        }
        if ($this->schema->columnExists('rh_leave_opening_balance', 'days_acquired')) {
            $this->pdo->exec("UPDATE rh_leave_opening_balance SET acquired_days = days_acquired WHERE acquired_days = 0");
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_holidays (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                holiday_date DATE NOT NULL,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                year INT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_holiday_date (holiday_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contract_rules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_type VARCHAR(50) NOT NULL,
                trial_duration_days INT UNSIGNED NOT NULL DEFAULT 0,
                max_renewals INT UNSIGNED NOT NULL DEFAULT 0,
                alert_days_before_end INT UNSIGNED NOT NULL DEFAULT 30,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_contract_rules_type (contract_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_signatories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                role VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                document_types VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_rh_signatories_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_missions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                destination VARCHAR(180) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                purpose TEXT NOT NULL,
                but_contexte TEXT NULL,
                liaison_type VARCHAR(80) NULL,
                expenses_json LONGTEXT NULL,
                notes TEXT NULL,
                transport_mode VARCHAR(80) NULL,
                budget DECIMAL(15,2) NOT NULL DEFAULT 0,
                status ENUM('draft','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_rh_missions_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE,
                CONSTRAINT fk_rh_missions_approved_by FOREIGN KEY (approved_by) REFERENCES rh_employees(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_periods (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(30) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status ENUM('open', 'calculating', 'closed') NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_payroll_period_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_variables (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                period_id INT UNSIGNED NOT NULL,
                employee_id INT UNSIGNED NOT NULL,
                worked_days DECIMAL(5,2) NOT NULL DEFAULT 30,
                absences_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
                bonus DECIMAL(15,2) NOT NULL DEFAULT 0,
                deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_payroll_var_emp_period (period_id, employee_id),
                CONSTRAINT fk_payroll_var_period FOREIGN KEY (period_id) REFERENCES rh_payroll_periods(id) ON DELETE CASCADE,
                CONSTRAINT fk_payroll_var_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_slips (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                period_id INT UNSIGNED NOT NULL,
                employee_id INT UNSIGNED NOT NULL,
                base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                bonuses_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                deductions_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                net_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                status ENUM('draft', 'validated', 'paid') NOT NULL DEFAULT 'draft',
                pdf_path VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_payroll_slip_emp_period (period_id, employee_id),
                CONSTRAINT fk_payroll_slip_period FOREIGN KEY (period_id) REFERENCES rh_payroll_periods(id) ON DELETE CASCADE,
                CONSTRAINT fk_payroll_slip_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contract_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employer_name VARCHAR(255) NULL,
                legal_form VARCHAR(255) NULL,
                capital_mention VARCHAR(255) NULL,
                address VARCHAR(255) NULL,
                rccm VARCHAR(255) NULL,
                representation_text TEXT NULL,
                signature_city VARCHAR(255) NULL,
                dg_signatory_name VARCHAR(255) NULL,
                dg_title VARCHAR(255) NULL,
                rh_signatory_name VARCHAR(255) NULL,
                rh_title VARCHAR(255) NULL,
                footer_line1 TEXT NULL,
                footer_line2 TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM rh_contract_settings");
        if ((int)$stmt->fetchColumn() === 0) {
            $this->pdo->exec("
                INSERT INTO rh_contract_settings (
                    id, employer_name, legal_form, capital_mention, address, rccm, representation_text,
                    signature_city, dg_signatory_name, dg_title, rh_signatory_name, rh_title, footer_line1, footer_line2
                ) VALUES (
                    1,
                    'BANAMUR INDUSTRIES ET TECHNOLOGIES',
                    'SARL au capital de 100 000 000 FCFA',
                    'Capital social : 100 000 000 FCFA',
                    'Abidjan, Koumassi Bd. du Gabon prolonge',
                    'CI-ABJ-03-2022-B13-02828',
                    'Representee pour les besoins du present contrat par la Direction Generale ou tout mandataire habilite.',
                    'Abidjan',
                    'Paul-Alex BRAUD',
                    'Directeur General',
                    'Constant Michel YAO',
                    'Responsable RH',
                    'Abidjan, Koumassi Bd. du Gabon prolonge - RCCM CI-ABJ-03-2022-B13-02828 - Tel. +225 27 21 36 27 27',
                    'Document RH genere depuis le module interne BANAMUR. Signature DG, RH et salarie requise pour prise d effet.'
                )
            ");
        }

        $this->createRhPayrollWizardTables();
    }

    /**
     * Crée les tables nécessaires au wizard de paie avancé :
     * coefficients contrat, rubriques, paramètres fiscaux, colonnes détaillées.
     */
    private function createRhPayrollWizardTables(): void
    {
        // --- Colonnes supplémentaires sur rh_contracts ---
        $contractCols = [
            'base_salary'        => 'DECIMAL(15,2) NULL',
            'sursalaire'         => 'DECIMAL(15,2) NULL',
            'category'           => 'VARCHAR(30) NULL',
            'transport_locality' => 'VARCHAR(150) NULL',
            'seniority_premium'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'other_premiums'     => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'gratification'      => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'paid_leave_premium' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'precarity_premium'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        ];
        foreach ($contractCols as $col => $def) {
            $this->addColumnIfMissing('rh_contracts', $col, $def);
        }

        // --- Colonnes supplémentaires sur rh_payroll_slips ---
        $slipCols = [
            'transport_premium'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'health_insurance'   => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'advance_deduction'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'other_deductions'   => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'rounding'           => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'observations'       => 'TEXT NULL',
            'fiscal_parts'       => 'INT NOT NULL DEFAULT 1',
            'igr_manual'         => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        ];
        foreach ($slipCols as $col => $def) {
            $this->addColumnIfMissing('rh_payroll_slips', $col, $def);
        }

        // --- Paramètres sociaux et fiscaux globaux (1 seule ligne) ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                is_salarial_rate DECIMAL(5,2) NOT NULL DEFAULT 1.20,
                cnps_salarial_rate DECIMAL(5,2) NOT NULL DEFAULT 6.30,
                cnps_patronal_rate DECIMAL(5,2) NOT NULL DEFAULT 7.70,
                family_benefits_rate DECIMAL(5,2) NOT NULL DEFAULT 5.75,
                work_accident_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                apprentice_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.40,
                professional_training_rate DECIMAL(5,2) NOT NULL DEFAULT 0.60,
                fdfp_rate DECIMAL(5,2) NOT NULL DEFAULT 0.60,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $cnt = (int) $this->pdo->query("SELECT COUNT(*) FROM rh_payroll_settings")->fetchColumn();
        if ($cnt === 0) {
            $this->pdo->exec("INSERT INTO rh_payroll_settings (id) VALUES (1)");
        }

        // --- Coefficients par type de contrat ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_contract_rules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_type VARCHAR(50) NOT NULL,
                label VARCHAR(150) NOT NULL,
                working_days INT UNSIGNED NOT NULL DEFAULT 30,
                hours_per_day DECIMAL(4,2) NOT NULL DEFAULT 8.00,
                overtime_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                precarity_auto_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                mission_rate DECIMAL(5,2) NOT NULL DEFAULT 100,
                leave_rate DECIMAL(5,2) NOT NULL DEFAULT 100,
                half_day_rate DECIMAL(5,2) NOT NULL DEFAULT 50,
                absence_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                sickness_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                rest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_payroll_contract_rules_type (contract_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $cntRules = (int) $this->pdo->query("SELECT COUNT(*) FROM rh_payroll_contract_rules")->fetchColumn();
        if ($cntRules === 0) {
            $this->pdo->exec("
                INSERT INTO rh_payroll_contract_rules
                    (contract_type, label, working_days, hours_per_day, overtime_multiplier, precarity_auto_rate)
                VALUES
                    ('cdd',             'CDD',                          30, 8.00, 1.15, 3),
                    ('cdi_permanent',   'CDI permanent',                26, 8.00, 1.25, 0),
                    ('stage',           'Stage de perfectionnement',    22, 8.00, 0.00, 0),
                    ('vacataire',       'Vacataire',                    26, 8.00, 1.00, 0),
                    ('libre',           'Parametrage libre',            30, 8.00, 1.00, 0)
            ");
        }

        // --- Catalogue des rubriques de paie ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_line_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                name VARCHAR(180) NOT NULL,
                nature ENUM('allocation_prime','avantage_nature','gain') NOT NULL DEFAULT 'allocation_prime',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_payroll_line_items_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $cntItems = (int) $this->pdo->query("SELECT COUNT(*) FROM rh_payroll_line_items")->fetchColumn();
        if ($cntItems === 0) {
            $this->pdo->exec("
                INSERT INTO rh_payroll_line_items (code, name, nature, sort_order) VALUES
                    ('alloc_assist_famille',  'Allocations assistance famille',           'allocation_prime',  10),
                    ('alloc_familiales_cps',  'Allocations familiales / CPS',             'allocation_prime',  20),
                    ('alloc_speciales',       'Allocations speciales non remboursees',     'allocation_prime',  30),
                    ('indem_apprentissage',   'Indemnite apprentissage',                  'allocation_prime',  40),
                    ('indem_stage',           'Indemnite de stage',                       'allocation_prime',  50),
                    ('prime_outillage',       'Prime outillage',                          'allocation_prime',  60),
                    ('prime_panier',          'Prime panier',                             'allocation_prime',  70),
                    ('prime_salissure',       'Prime salissure',                          'allocation_prime',  80),
                    ('prime_tenue',           'Prime tenue',                              'allocation_prime',  90),
                    ('avantage_logement',     'Avantage logement',                        'avantage_nature',  100),
                    ('avantage_vehicule',     'Avantage vehicule',                        'avantage_nature',  110),
                    ('prime_assiduite',       'Prime d''assiduite',                       'gain',             120),
                    ('prime_bilan',           'Prime de bilan',                           'gain',             130)
            ");
        }

        // --- Montants contractuels par contrat/rubrique ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_contract_line_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id INT UNSIGNED NOT NULL,
                line_item_id INT UNSIGNED NOT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_contract_line_item (contract_id, line_item_id),
                CONSTRAINT fk_contract_line_items_contract FOREIGN KEY (contract_id) REFERENCES rh_contracts(id) ON DELETE CASCADE,
                CONSTRAINT fk_contract_line_items_item FOREIGN KEY (line_item_id) REFERENCES rh_payroll_line_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
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
        if (!$this->schema->indexExists('website_services', 'uniq_website_services_title')) {
            // Les anciennes données de démonstration étaient réinsérées à chaque
            // requête car la table ne possédait aucune contrainte unique.
            $this->pdo->exec("
                DELETE duplicate_service
                FROM website_services duplicate_service
                INNER JOIN website_services original_service
                    ON original_service.title = duplicate_service.title
                   AND original_service.id < duplicate_service.id
            ");
            try {
                $this->pdo->exec(
                    'CREATE UNIQUE INDEX uniq_website_services_title ON website_services (title)'
                );
            } catch (\PDOException $exception) {
                // Une requête concurrente peut avoir créé l’index entre le
                // contrôle et l’ajout. Toute autre erreur doit rester visible.
                if ((string) $exception->getCode() !== '42000'
                    || !str_contains($exception->getMessage(), "uniq_website_services_title")) {
                    throw $exception;
                }
            }
        }
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_branding (
                id TINYINT UNSIGNED PRIMARY KEY,
                company_name VARCHAR(160) NOT NULL,
                tagline VARCHAR(255) NULL,
                logo_text VARCHAR(30) NULL,
                logo_url VARCHAR(255) NULL,
                primary_color VARCHAR(20) NOT NULL DEFAULT '#111c44',
                secondary_color VARCHAR(20) NOT NULL DEFAULT '#ffcc00',
                accent_color VARCHAR(20) NOT NULL DEFAULT '#d40511',
                surface_color VARCHAR(20) NOT NULL DEFAULT '#f5f7fb',
                font_family VARCHAR(120) NOT NULL DEFAULT 'Inter',
                announcement VARCHAR(255) NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_slides (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                eyebrow VARCHAR(120) NULL,
                title VARCHAR(220) NOT NULL,
                description TEXT NULL,
                image_url VARCHAR(255) NULL,
                primary_label VARCHAR(100) NULL,
                primary_url VARCHAR(180) NULL,
                secondary_label VARCHAR(100) NULL,
                secondary_url VARCHAR(180) NULL,
                overlay_color VARCHAR(20) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_products (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(80) NOT NULL,
                name VARCHAR(180) NOT NULL,
                category VARCHAR(100) NULL,
                summary VARCHAR(255) NULL,
                price DECIMAL(12,2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'XOF',
                image_url VARCHAR(255) NULL,
                badge VARCHAR(60) NULL,
                stock_status VARCHAR(40) NOT NULL DEFAULT 'available',
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_website_products_sku (sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_forum_topics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(100) NOT NULL,
                title VARCHAR(220) NOT NULL,
                excerpt VARCHAR(500) NULL,
                author_name VARCHAR(140) NOT NULL DEFAULT 'Équipe LBP',
                replies_count INT UNSIGNED NOT NULL DEFAULT 0,
                views_count INT UNSIGNED NOT NULL DEFAULT 0,
                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                is_published TINYINT(1) NOT NULL DEFAULT 1,
                last_activity_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                badge VARCHAR(50) NULL,
                title VARCHAR(255) NOT NULL,
                link_label VARCHAR(100) NULL,
                link_url VARCHAR(180) NULL,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_articles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(160) NOT NULL,
                title VARCHAR(220) NOT NULL,
                excerpt VARCHAR(500) NULL,
                content LONGTEXT NULL,
                image_url VARCHAR(255) NULL,
                author_name VARCHAR(140) NOT NULL DEFAULT 'Équipe LBP',
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_website_articles_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_analytics_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                visitor_id VARCHAR(80) NOT NULL,
                customer_id INT UNSIGNED NULL,
                event_type ENUM('page_view','click') NOT NULL,
                page_path VARCHAR(255) NOT NULL,
                target_key VARCHAR(180) NULL,
                target_label VARCHAR(255) NULL,
                referrer VARCHAR(500) NULL,
                ip_address VARCHAR(80) NULL,
                user_agent VARCHAR(500) NULL,
                language VARCHAR(50) NULL,
                timezone VARCHAR(100) NULL,
                screen_size VARCHAR(40) NULL,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_website_analytics_date (created_at),
                KEY idx_website_analytics_page (page_path, event_type),
                KEY idx_website_analytics_visitor (visitor_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_customer_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(180) NOT NULL,
                email VARCHAR(160) NOT NULL,
                phone VARCHAR(60) NULL,
                password_hash VARCHAR(255) NOT NULL,
                status ENUM('active','suspended') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_website_customer_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_conversations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                subject VARCHAR(180) NOT NULL DEFAULT 'Assistance client',
                status ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
                assigned_user_id INT NULL,
                last_message_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_website_conversations_customer (customer_id, status),
                KEY idx_website_conversations_activity (last_message_at),
                CONSTRAINT fk_website_conversations_customer FOREIGN KEY (customer_id) REFERENCES website_customer_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS website_conversation_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT UNSIGNED NOT NULL,
                sender_type ENUM('customer','manager') NOT NULL,
                sender_id INT UNSIGNED NOT NULL,
                message TEXT NULL,
                attachment_path VARCHAR(255) NULL,
                attachment_name VARCHAR(255) NULL,
                attachment_mime VARCHAR(120) NULL,
                attachment_size INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_website_messages_conversation (conversation_id, id),
                CONSTRAINT fk_website_messages_conversation FOREIGN KEY (conversation_id) REFERENCES website_conversations(id) ON DELETE CASCADE
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

    private function seedCompanySites(): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO company_sites (name, code, country, city, is_active) VALUES (:name, :code, :country, :city, 1)");
        foreach ([
            ['Siege Abidjan','ABJ-HQ','Cote d Ivoire','Abidjan'],
            ['Agence San Pedro','SPY','Cote d Ivoire','San Pedro'],
            ['Bureau international','INTL','International', null],
            ['Paris 17 chemin des Vignes 93000 Bobigny','FRA','France','Bobigny'],
            ['Agence Sénégal','SEN','Sénégal','Dakar'],
            ['Aéroport Port Bouët Fret','ABJ-FRET','Cote d Ivoire','Abidjan'],
            ['Agence Abobo Dokui','ABO-DOK','Cote d Ivoire','Abidjan'],
            ['Agence Adjamé Pharmacie Latin','ADJ-LAT','Cote d Ivoire','Abidjan']
        ] as [$name,$code,$country,$city]) {
            $stmt->execute(['name'=>$name,'code'=>$code,'country'=>$country,'city'=>$city]);
        }

        $this->pdo->exec("UPDATE company_sites SET name = 'Paris 17 chemin des Vignes 93000 Bobigny', city = 'Bobigny' WHERE code = 'FRA' OR name = 'Agence France' OR name LIKE '%Agence France%'");
    }

    private function seedWebsiteContent(): void
    {
        $this->pdo->exec("INSERT IGNORE INTO website_pages (slug,title,content,is_published) VALUES ('accueil','Accueil','Site vitrine transit pilote depuis ERP.',1)");
        $stmt = $this->pdo->prepare("
            INSERT INTO website_services (title, summary, icon, sort_order)
            VALUES (:title, :summary, :icon, :sort_order)
            ON DUPLICATE KEY UPDATE summary = VALUES(summary), icon = VALUES(icon), sort_order = VALUES(sort_order)
        ");
        foreach ([
            ['Dédouanement','Formalités douanières import-export','customs',10],
            ['Fret & transport','Organisation des enlèvements et livraisons','freight',20],
            ['Suivi colis','Tracking digital des expéditions','tracking',30],
            ['Livraison locale','Distribution, preuve de livraison et dernier kilomètre','delivery',40],
        ] as [$title,$summary,$icon,$order]) {
            $stmt->execute(['title'=>$title,'summary'=>$summary,'icon'=>$icon,'sort_order'=>$order]);
        }
        $this->pdo->exec("
            INSERT IGNORE INTO website_branding
                (id, company_name, tagline, logo_text, primary_color, secondary_color, accent_color, surface_color, font_family, announcement)
            VALUES
                (1, 'LBP Transit', 'Le monde avance. Vos marchandises aussi.', 'LBP', '#111c44', '#ffcc00', '#d40511', '#f5f7fb', 'Inter', 'Expéditions Chine → Afrique : départs groupés chaque semaine')
        ");
        $this->pdo->exec("
            INSERT IGNORE INTO website_slides
                (id, eyebrow, title, description, image_url, primary_label, primary_url, secondary_label, secondary_url, overlay_color, sort_order)
            VALUES
                (1, 'Transit international', 'Votre commerce n’a plus de frontières.', 'Fret, dédouanement, sourcing et livraison finale réunis dans une expérience digitale claire.', 'images/site/hero-logistics.svg', 'Demander un devis', 'site/devis', 'Suivre un colis', 'site/tracking', '#111c44', 10),
                (2, 'Marketplace logistique', 'Achetez les services et fournitures utiles à vos expéditions.', 'Emballages, assurance, groupage et prestations transit accessibles depuis notre nouvelle boutique.', 'images/site/warehouse.svg', 'Explorer la boutique', 'site/shop', 'Nos services', 'site#services', '#063f4f', 20),
                (3, 'Communauté import-export', 'Les bonnes réponses circulent aussi vite que vos colis.', 'Échangez avec des professionnels sur les formalités, fournisseurs, corridors et bonnes pratiques.', 'images/site/hero-logistics.svg', 'Découvrir le forum', 'site/forum', 'Créer un compte bientôt', 'site/forum', '#4c1d95', 30)
        ");
        $this->pdo->exec("
            INSERT IGNORE INTO website_products
                (id, sku, name, category, summary, price, currency, badge, stock_status, is_featured, sort_order)
            VALUES
                (1, 'PACK-EXPORT-M', 'Kit emballage export renforcé', 'Emballage', 'Carton double cannelure, film, adhésif et protections pour expédition internationale.', 35000, 'XOF', 'Best-seller', 'available', 1, 10),
                (2, 'GROUPAGE-CN-CI', 'Réservation groupage Chine → Abidjan', 'Transport', 'Acompte de réservation pour un départ maritime consolidé.', 150000, 'XOF', 'Départ hebdomadaire', 'available', 1, 20),
                (3, 'ASSUR-CARGO', 'Assurance cargo essentielle', 'Assurance', 'Protection simplifiée de votre marchandise pendant le transport.', 45000, 'XOF', 'Recommandé', 'available', 1, 30),
                (4, 'DOC-IMPORT', 'Pack documents import', 'Formalités', 'Contrôle documentaire et préparation du dossier avant embarquement.', 75000, 'XOF', 'Gain de temps', 'available', 1, 40)
        ");
        $this->pdo->exec("
            INSERT IGNORE INTO website_forum_topics
                (id, category, title, excerpt, author_name, replies_count, views_count, is_pinned, last_activity_at)
            VALUES
                (1, 'Import Chine', 'Quels documents demander à son fournisseur avant le départ ?', 'Checklist facture, packing list, certificat d’origine et contrôle qualité.', 'Awa K.', 18, 426, 1, NOW()),
                (2, 'Douane', 'Comprendre la valeur en douane sans jargon', 'Échange pratique autour du fret, de l’assurance et de la valeur transactionnelle.', 'Conseiller LBP', 12, 318, 1, NOW()),
                (3, 'Transport', 'Maritime ou aérien pour un premier envoi ?', 'Retours d’expérience selon le volume, l’urgence et le budget.', 'Moussa T.', 27, 591, 0, NOW())
        ");
        $this->pdo->exec("
            INSERT IGNORE INTO website_announcements
                (id, badge, title, link_label, link_url, is_active, sort_order)
            VALUES
                (1, 'Nouveau', 'Expéditions Chine → Afrique : départs groupés chaque semaine', 'En savoir plus', 'site/devis', 1, 10)
        ");
        $this->pdo->exec("
            INSERT IGNORE INTO website_articles
                (id, slug, title, excerpt, content, author_name, is_published, published_at)
            VALUES
                (1, 'preparer-import-chine-afrique', 'Préparer son premier import Chine → Afrique',
                 'Les étapes essentielles avant de payer un fournisseur et réserver le transport.',
                 'Vérifiez le fournisseur, définissez clairement les incoterms, contrôlez les documents commerciaux et anticipez les formalités douanières avant l’embarquement.',
                 'Équipe LBP', 1, NOW())
        ");
    }

    private function createColisageTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_clients (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(60) NULL,
                email VARCHAR(150) NULL,
                address VARCHAR(255) NULL,
                type ENUM('standard', 'corporate') NOT NULL DEFAULT 'standard',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_livreurs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                modele_vehicule VARCHAR(120) NULL,
                plaque_immatriculation VARCHAR(60) NULL,
                statut ENUM('Disponible', 'En course') NOT NULL DEFAULT 'Disponible',
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                derniere_localisation DATETIME NULL,
                UNIQUE KEY uniq_lbp_livreurs_user (user_id),
                CONSTRAINT fk_lbp_livreurs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_expeditions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(120) NOT NULL,
                type_transport ENUM('AÉRIEN', 'MARITIME', 'TERRESTRE') NOT NULL,
                agence_depart_id INT UNSIGNED NULL,
                agence_arrivee_id INT UNSIGNED NULL,
                date_depart_prevue DATE NULL,
                date_arrivee_estimee DATE NULL,
                livreur_id INT UNSIGNED NULL,
                statut ENUM('BROUILLON', 'EN_PREPARATION', 'EN_TRANSIT', 'ARRIVE', 'CLOTURE') NOT NULL DEFAULT 'BROUILLON',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_lbp_expeditions_ref (reference),
                CONSTRAINT fk_lbp_expeditions_depart FOREIGN KEY (agence_depart_id) REFERENCES company_sites(id) ON DELETE SET NULL,
                CONSTRAINT fk_lbp_expeditions_arrivee FOREIGN KEY (agence_arrivee_id) REFERENCES company_sites(id) ON DELETE SET NULL,
                CONSTRAINT fk_lbp_expeditions_livreur FOREIGN KEY (livreur_id) REFERENCES lbp_livreurs(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_colis (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                numero_tracking VARCHAR(100) NOT NULL,
                expediteur_id INT UNSIGNED NOT NULL,
                destinataire_id INT UNSIGNED NOT NULL,
                poids_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                nombre_colis INT UNSIGNED NOT NULL DEFAULT 1,
                valeur_declaree DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                montant_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                montant_total_eur DECIMAL(15,2) NULL,
                devise VARCHAR(10) NOT NULL DEFAULT 'XOF',
                agence_depart_id INT UNSIGNED NULL,
                agence_arrivee_id INT UNSIGNED NULL,
                statut ENUM('RÉCEPTIONNÉ', 'EN_PRÉPARATION', 'EN_TRANSIT', 'ARRIVÉ', 'LIVRÉ', 'RETIRÉ') NOT NULL DEFAULT 'RÉCEPTIONNÉ',
                type_expediteur VARCHAR(80) NULL,
                trajet VARCHAR(50) NULL,
                trafic VARCHAR(80) NULL,
                expedition_id INT UNSIGNED NULL,
                recup_nom VARCHAR(180) NULL,
                recup_cni VARCHAR(100) NULL,
                recup_telephone VARCHAR(60) NULL,
                recup_date_heure DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_lbp_colis_tracking (numero_tracking),
                CONSTRAINT fk_lbp_colis_expediteur FOREIGN KEY (expediteur_id) REFERENCES lbp_clients(id) ON DELETE RESTRICT,
                CONSTRAINT fk_lbp_colis_destinataire FOREIGN KEY (destinataire_id) REFERENCES lbp_clients(id) ON DELETE RESTRICT,
                CONSTRAINT fk_lbp_colis_depart FOREIGN KEY (agence_depart_id) REFERENCES company_sites(id) ON DELETE SET NULL,
                CONSTRAINT fk_lbp_colis_arrivee FOREIGN KEY (agence_arrivee_id) REFERENCES company_sites(id) ON DELETE SET NULL,
                CONSTRAINT fk_lbp_colis_exped FOREIGN KEY (expedition_id) REFERENCES lbp_expeditions(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('lbp_colis', 'assurance_souscrite', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('lbp_colis', 'montant_assurance', "DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_marchandises (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                emballage VARCHAR(120) NULL,
                quantite INT UNSIGNED NOT NULL DEFAULT 1,
                nbre_colis INT UNSIGNED NOT NULL DEFAULT 1,
                qte_emballage INT UNSIGNED NOT NULL DEFAULT 1,
                poids_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                prix_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_ligne DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_lbp_marchandises_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_tracking_gps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NULL,
                expedition_id INT UNSIGNED NULL,
                etape VARCHAR(255) NOT NULL,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                date_etape DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_lbp_tracking_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE,
                CONSTRAINT fk_lbp_tracking_exped FOREIGN KEY (expedition_id) REFERENCES lbp_expeditions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_inventaires (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agence_id INT UNSIGNED NOT NULL,
                date_inventaire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                statut ENUM('BROUILLON', 'CLOTURE') NOT NULL DEFAULT 'BROUILLON',
                commentaires TEXT NULL,
                cree_par INT NULL,
                CONSTRAINT fk_lbp_inventaires_agence FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_lbp_inventaires_creator FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_inventaire_lignes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventaire_id INT UNSIGNED NOT NULL,
                colis_id INT UNSIGNED NOT NULL,
                etat ENUM('PRÉSENT', 'MANQUANT', 'ENDOMMAGÉ') NOT NULL DEFAULT 'PRÉSENT',
                commentaires TEXT NULL,
                CONSTRAINT fk_lbp_inv_lines_inv FOREIGN KEY (inventaire_id) REFERENCES lbp_inventaires(id) ON DELETE CASCADE,
                CONSTRAINT fk_lbp_inv_lines_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_produits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(180) NOT NULL,
                categorie VARCHAR(100) NULL,
                nature VARCHAR(50) NULL,
                prix_unitaire DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                prix_forfaitaire DECIMAL(12,2) NULL,
                poids_min DECIMAL(10,2) NULL,
                poids_max DECIMAL(10,2) NULL,
                description VARCHAR(255) NULL,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                unite VARCHAR(20) NOT NULL DEFAULT 'kg',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_lbp_produits_nom (nom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_credits_interagence (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agence_creanciere_id INT UNSIGNED NOT NULL,
                agence_debitrice_id INT UNSIGNED NOT NULL,
                colis_id INT UNSIGNED NULL,
                montant DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                devise VARCHAR(10) NOT NULL DEFAULT 'XOF',
                statut ENUM('NON_REGLE', 'REGLE') NOT NULL DEFAULT 'NON_REGLE',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_credits_creanciere FOREIGN KEY (agence_creanciere_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_credits_debitrice FOREIGN KEY (agence_debitrice_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_credits_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_demandes_fournitures (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agence_id INT UNSIGNED NOT NULL,
                user_id INT NOT NULL,
                description TEXT NOT NULL,
                statut ENUM('SOUMIS', 'VALIDEE', 'REFUSEE', 'LIVREE') NOT NULL DEFAULT 'SOUMIS',
                motif_refus TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT fk_fournitures_agence FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                CONSTRAINT fk_fournitures_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_client_wallets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_nom VARCHAR(255) NOT NULL,
                telephone VARCHAR(50) NULL,
                solde_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                solde_eur DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                solde_cad DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                plafond_credit_xof DECIMAL(15,2) NOT NULL DEFAULT 500000.00,
                statut ENUM('ACTIF', 'SUSPENDU', 'SURVEILLANCE') NOT NULL DEFAULT 'ACTIF',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_lbp_client_wallets_nom (client_nom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_client_wallet_transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wallet_id INT UNSIGNED NOT NULL,
                type ENUM('AVANCE', 'DEBIT_FACTURE', 'REMBOURSEMENT', 'AJUSTEMENT') NOT NULL,
                montant_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                devise VARCHAR(10) NOT NULL DEFAULT 'XOF',
                mode_paiement VARCHAR(50) NOT NULL DEFAULT 'Espèces',
                reference_transac VARCHAR(100) NULL,
                motif TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_wallet_tx_wallet FOREIGN KEY (wallet_id) REFERENCES lbp_client_wallets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_landed_costs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference_lot VARCHAR(100) NOT NULL,
                trajet_code VARCHAR(20) NOT NULL DEFAULT 'LB-FR',
                frais_douane_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                frais_fret_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                frais_manutention_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                poids_total_kg DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                cout_par_kg_xof DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                statut ENUM('SIMULATION', 'VALIDÉ', 'CLÔTURÉ') NOT NULL DEFAULT 'SIMULATION',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_mobile_money_reconciliations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                operateur ENUM('Wave', 'Orange Money', 'MTN Mobile Money', 'Virement Bancaire') NOT NULL,
                reference_transac VARCHAR(100) NOT NULL,
                montant_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                client_nom VARCHAR(255) NULL,
                facture_numero VARCHAR(100) NULL,
                statut ENUM('RAPPROCHÉ', 'EN_ATTENTE', 'ECART_MONTANT', 'REJETÉ') NOT NULL DEFAULT 'EN_ATTENTE',
                date_transaction DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");



        // Table Catalogue d'Emballages LBP (Cartons, Bôrô, Valises, Sacs)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_emballages_catalogue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                type ENUM('Carton', 'Bôrô', 'Valise', 'Sac', 'Consommable') NOT NULL DEFAULT 'Carton',
                dimensions VARCHAR(100) NULL,
                prix_vente_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                prix_achat_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                min_stock_alerte INT NOT NULL DEFAULT 10,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_lbp_emballages_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_emballages_stocks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                emballage_id INT UNSIGNED NOT NULL,
                agence_id INT UNSIGNED NOT NULL,
                quantite_disponible INT NOT NULL DEFAULT 0,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_emballage_agence (emballage_id, agence_id),
                CONSTRAINT fk_emb_stock_item FOREIGN KEY (emballage_id) REFERENCES lbp_emballages_catalogue(id) ON DELETE CASCADE,
                CONSTRAINT fk_emb_stock_site FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_emballages_mouvements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                emballage_id INT UNSIGNED NOT NULL,
                agence_id INT UNSIGNED NOT NULL,
                type_mouvement ENUM('APPROVISIONNEMENT', 'SORTIE_COLISAGE', 'TRANSFERT', 'PERTE') NOT NULL,
                quantite INT NOT NULL,
                motif TEXT NULL,
                user_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_emb_mvt_item FOREIGN KEY (emballage_id) REFERENCES lbp_emballages_catalogue(id) ON DELETE CASCADE,
                CONSTRAINT fk_emb_mvt_site FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedColisageProducts();
    }

    private function seedColisageProducts(): void
    {
        $products = [
            [297,"DENREES ALIMENTAIRES","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [298,"ATTIEKE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [299,"PLACALI","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [300,"GARI","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [301,"CHAT NOIR","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [302,"POUDRE DE CACAO","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [303,"GOMBO","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [304,"GNANGNAN","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [305,"FEUILLE DE PATATE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [306,"SOUMARA","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [307,"PATE D'ARACHIDE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [308,"DORKOUNOU","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [309,"AKASSA","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [310,"CHIPS","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [311,"SHIPS","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [312,"BISSAP","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [313,"TAMARIN","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [314,"PATE DE GINGEMBRE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [315,"POUDRE DE MIL","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [316,"POUDRE DE MAIS","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [317,"POUDRE DE PIMENT","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [318,"POUDRE DE GINGEMBRE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [319,"GINGEMBRE SECHE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [320,"POUDRE DE GOMBO","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [321,"MIL","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [322,"HARICOT","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [323,"TCHONGON","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [324,"RIZ","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [325,"ANANAS SECHE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [326,"MANGUE SECHE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [327,"COUSCOUS","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [328,"AROME","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [329,"GRAINE PILE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [330,"EPICE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [331,"MAIS","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [332,"GNONMI","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [333,"BAOBAB","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [334,"BONBON","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [335,"CACAHOUETTE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [336,"CROQUETTE","DENREE","PRIX_UNITAIRE","900.00","3500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [337,"PETIT COLAS","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [338,"HUILE DE COCO","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [339,"BEURRE DE KARITE","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [340,"KINKELIBA","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [341,"DJEKA","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [342,"INFUSION","HUILE_ET_KARITE","PRIX_UNITAIRE","1100.00","4500.00","0.00","4.00","A PARTIR DE 5 KG",1,"kg"],
            [343,"VETEMENTS","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [344,"CHAUSSURES","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [345,"DRAPS","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [346,"OUVRAGE EN PLASTIQUE","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [347,"USTENSILES DE CUISINE","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [348,"VALISE","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [349,"ENCENS","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [350,"SAVOIR NOIR","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [351,"SAC A MAIN","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [352,"L'EAU BENITE","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [353,"ECORCE","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [354,"4 COTES","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [355,"CAOLIN","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [356,"NEP NEP","DIVERS","PRIX_UNITAIRE","1850.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [357,"ATTOTE","DIVERS","PRIX_UNITAIRE","2100.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [358,"HUILE ROUGE","DIVERS","PRIX_UNITAIRE","1600.00","3500.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [359,"BOUILLONS","DIVERS","PRIX_UNITAIRE","1600.00","3500.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [360,"CUBE MAGGI","DIVERS","PRIX_UNITAIRE","1600.00","3500.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [361,"VETEMENTS DE MARQUE","DIVERS","PRIX_UNITAIRE","3500.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [362,"CHAUSSURES DE MARQUE","DIVERS","PRIX_UNITAIRE","3500.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [363,"SACS DE MARQUE","DIVERS","PRIX_UNITAIRE","3500.00","5000.00","0.00","2.00","A PARTIR DE 2 KG",1,"kg"],
            [364,"POISSON FUME","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [365,"CREVETTE FUMEE","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [366,"ESCARGOT","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [367,"POULET FUME","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [368,"POISSON EN POUDRE","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [369,"CREVETTE EN POUDRE","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [370,"KPLO FUME","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [371,"VIANDE FUME","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5500.00","7500.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [372,"COSMETIQUE","COLIS_RAPIDE_EXPORT","PRIX_UNITAIRE","5850.00","8000.00","0.00","1.00","A PARTIR DE 2 KG",1,"kg"],
            [373,"DENREES ET DIVERS IMPORT","COLIS_RAPIDE_IMPORT","PRIX_UNITAIRE","7216.00",null,null,null,"11 €/kg — Paris → Abidjan",1,"kg"],
            [374,"TELEPHONE ET APPAREIL ELECTRONIQUE","COLIS_RAPIDE_IMPORT","PRIX_UNITAIRE","11807.00",null,null,null,"À partir de 18 €/kg — Paris → Abidjan",1,"kg"],
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_produits (id, nom, categorie, nature, prix_unitaire, prix_forfaitaire, poids_min, poids_max, description, actif, unite)
            VALUES (:id, :nom, :categorie, :nature, :prix_unitaire, :prix_forfaitaire, :poids_min, :poids_max, :description, :actif, :unite)
            ON DUPLICATE KEY UPDATE
                nom = VALUES(nom),
                categorie = VALUES(categorie),
                nature = VALUES(nature),
                prix_unitaire = VALUES(prix_unitaire),
                prix_forfaitaire = VALUES(prix_forfaitaire),
                poids_min = VALUES(poids_min),
                poids_max = VALUES(poids_max),
                description = VALUES(description),
                actif = VALUES(actif),
                unite = VALUES(unite)
        ");

        foreach ($products as $p) {
            $stmt->execute([
                'id' => $p[0],
                'nom' => $p[1],
                'categorie' => $p[2],
                'nature' => $p[3],
                'prix_unitaire' => $p[4],
                'prix_forfaitaire' => $p[5],
                'poids_min' => $p[6],
                'poids_max' => $p[7],
                'description' => $p[8],
                'actif' => $p[9],
                'unite' => $p[10],
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

    /**
     * Crée les tables pour le module Call Center (appels et litiges).
     */
    private function createCallCenterTables(): void
    {
        if (!$this->schema->tableExists('lbp_call_center_appels')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_call_center_appels (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    client_id INT UNSIGNED NOT NULL,
                    agent_id INT UNSIGNED NOT NULL,
                    type_appel ENUM('information', 'reclamation', 'suivi_colis', 'autre') NOT NULL DEFAULT 'information',
                    description TEXT NOT NULL,
                    statut ENUM('en_cours', 'traite', 'a_rappeler') NOT NULL DEFAULT 'traite',
                    satisfaction_score TINYINT UNSIGNED NULL COMMENT '1 a 5',
                    numero_tracking VARCHAR(60) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_cc_appels_client (client_id),
                    KEY idx_cc_appels_agent (agent_id),
                    KEY idx_cc_appels_date (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_call_center_litiges (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    client_id INT UNSIGNED NOT NULL,
                    colis_id INT UNSIGNED NULL,
                    agent_id INT UNSIGNED NOT NULL,
                    type_litige ENUM('colis_perdu', 'colis_endommage', 'retard', 'facturation', 'autre') NOT NULL DEFAULT 'autre',
                    description TEXT NOT NULL,
                    gravite ENUM('faible', 'moyenne', 'elevee', 'critique') NOT NULL DEFAULT 'moyenne',
                    statut ENUM('nouveau', 'en_cours', 'resolu', 'annule') NOT NULL DEFAULT 'nouveau',
                    solution_apportee TEXT NULL,
                    date_ouverture DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    date_resolution DATETIME NULL,
                    KEY idx_cc_litiges_client (client_id),
                    KEY idx_cc_litiges_colis (colis_id),
                    KEY idx_cc_litiges_statut (statut)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $this->fixCallCenterLitigesGraviteEnum();

        // Table des notifications/suivis du call center
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_call_center_notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT UNSIGNED NOT NULL,
                client_id INT UNSIGNED NOT NULL,
                type_notification ENUM('whatsapp', 'sms', 'appel') NOT NULL,
                duree_appel INT UNSIGNED NULL COMMENT 'en secondes',
                description TEXT NULL,
                satisfaction_score TINYINT UNSIGNED NULL,
                agent_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cc_notif_colis (colis_id),
                KEY idx_cc_notif_client (client_id),
                KEY idx_cc_notif_agent (agent_id),
                CONSTRAINT fk_cc_notif_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE,
                CONSTRAINT fk_cc_notif_client FOREIGN KEY (client_id) REFERENCES lbp_clients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed permissions Call Center si non existantes
        $existing = $this->pdo->query("SELECT code FROM permission_entities WHERE code IN ('call_center_view', 'call_center_manage', 'call_center_dg_view', 'rapports_agence')");
        $existingCodes = $existing ? $existing->fetchAll(PDO::FETCH_COLUMN) : [];

        $toInsert = [
            ['call_center_view', 'Call Center', 'Call Center - Consulter', 'Consulter le tableau de bord Call Center, les appels, les litiges et la vue des rayons en temps réel.', 240],
            ['call_center_manage', 'Call Center', 'Call Center - Gérer', 'Enregistrer des appels, ouvrir et résoudre des litiges, envoyer des notifications.', 250],
            ['call_center_dg_view', 'Call Center', 'Call Center - Vue DG / Superviseur', 'Accéder à l\'historique des notifications avec indicateurs rouge/vert pour le DG.', 260],
            ['rapports_agence', 'Facturation', 'Rapports journaliers par agence', 'Accéder aux rapports journaliers et mensuels par agence avec export CSV.', 235],
        ];

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO permission_entities (code, module, name, description, sort_order, is_active)
            VALUES (:code, :module, :name, :description, :sort_order, 1)
        ");
        foreach ($toInsert as [$code, $module, $name, $desc, $sort]) {
            if (!in_array($code, $existingCodes, true)) {
                $stmt->execute(['code' => $code, 'module' => $module, 'name' => $name, 'description' => $desc, 'sort_order' => $sort]);
            }
        }
    }

    /**
     * Corrige une dérive de schéma : certaines bases (copies plus anciennes de la production)
     * ont été créées avec un enum gravite('basse','moyenne','haute','critique') alors que le
     * code applicatif (formulaire, filtres, tri, badges) attend l'enum canonique ci-dessous.
     * La colonne CREATE TABLE ci-dessus est correcte mais ne s'applique jamais telle quelle sur
     * une base où lbp_call_center_appels existe déjà (garde d'existence sur toute la méthode) ;
     * cet ALTER idempotent aligne donc le schéma existant sans toucher aux données historiques
     * (aucune ligne n'utilise 'basse'/'haute' sur les bases où le problème a été constaté).
     */
    private function fixCallCenterLitigesGraviteEnum(): void
    {
        if (!$this->schema->tableExists('lbp_call_center_litiges')) {
            return;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM lbp_call_center_litiges WHERE Field = 'gravite'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($column === null || $column['Type'] === "enum('faible','moyenne','elevee','critique')") {
            return;
        }

        $this->pdo->exec("
            ALTER TABLE lbp_call_center_litiges
            MODIFY COLUMN gravite ENUM('faible', 'moyenne', 'elevee', 'critique') NOT NULL DEFAULT 'moyenne'
        ");
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

    /**
     * Charge et exécute la migration SQL pour le flux unifié LBP.
     */
    private function createLbpUnifiedFlowTables(): void
    {
        if ($this->schema->tableExists('lbp_factures')) {
            return;
        }

        $sqlFile = dirname(__DIR__, 2) . '/doc/backend/migrations/2026_07_05_lbp_unified_flow.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            if ($sql !== false) {
                // Retirer les directives DELIMITER qui ne sont pas supportées par PDO
                $sql = preg_replace('/DELIMITER\s+\S+/i', '', $sql);
                // Remplacer les délimiteurs // par ;
                $sql = str_replace('//', ';', $sql);
                $this->pdo->exec($sql);
            }
        }
    }

    /**
     * Crée les tables pour les rayons de stock et les paramètres de garde/pénalités.
     */
    private function createLogistiqueRayonsAndSettingsTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS logistique_rayons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agence_id INT UNSIGNED NOT NULL,
                code_rayon VARCHAR(50) NOT NULL,
                nom_rayon VARCHAR(120) NOT NULL,
                capacite_max INT UNSIGNED NOT NULL DEFAULT 50,
                statut ENUM('ACTIF', 'PLEIN', 'MAINTENANCE') NOT NULL DEFAULT 'ACTIF',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_logistique_rayons_code (agence_id, code_rayon),
                KEY idx_logistique_rayons_agence (agence_id, statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS logistique_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agence_id INT UNSIGNED NULL,
                delai_gratuit_jours INT UNSIGNED NOT NULL DEFAULT 7,
                frais_gardiennage_par_jour DECIMAL(10, 2) NOT NULL DEFAULT 500.00,
                auto_assign_rayon TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_logistique_settings_agence (agence_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            INSERT IGNORE INTO logistique_settings (agence_id, delai_gratuit_jours, frais_gardiennage_par_jour, auto_assign_rayon)
            VALUES (NULL, 7, 500.00, 1)
        ");

        $this->addColumnIfMissing('logistique_rayons', 'type_rayon', "ENUM('STANDARD', 'EXPRESS', 'CARGO_LOURD', 'FRAGILE', 'SECU_VALEUR') NOT NULL DEFAULT 'STANDARD'");
        $this->addColumnIfMissing('logistique_rayons', 'poids_max_autorise', "DECIMAL(10,2) NULL");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS logistique_mouvements_rayon (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT NOT NULL,
                rayon_id INT UNSIGNED NULL,
                type_mouvement ENUM('ENTREE', 'SORTIE', 'DEPLACEMENT') NOT NULL,
                effectue_par INT UNSIGNED NULL,
                commentaires VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_mouvements_colis (colis_id),
                KEY idx_mouvements_rayon (rayon_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                colis_id INT NOT NULL,
                destinataire_telephone VARCHAR(50) NULL,
                destinataire_email VARCHAR(150) NULL,
                type_notification ENUM('ARRIVEE_AGENCE', 'RAPPEL_GARDIENNAGE', 'RETRAIT_CONFIRME') NOT NULL,
                statut ENUM('ENVOYÉ', 'EN_ATTENTE', 'ÉCHOUÉ') NOT NULL DEFAULT 'ENVOYÉ',
                message TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notifications_colis (colis_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->schema->tableExists('lbp_colis')) {
            $this->addColumnIfMissing('lbp_colis', 'rayon_id', "INT UNSIGNED NULL");
            $this->addColumnIfMissing('lbp_colis', 'date_arrivee_agence', "DATETIME NULL");
            $this->addColumnIfMissing('lbp_colis', 'date_limite_retrait', "DATETIME NULL");
            $this->addColumnIfMissing('lbp_colis', 'frais_gardiennage_appliques', "DECIMAL(10, 2) NOT NULL DEFAULT 0.00");

            $this->addIndexIfMissing('lbp_colis', 'idx_lbp_colis_rayon', 'rayon_id');
        }

        if ($this->schema->tableExists('lbp_etats_journaliers')) {
            $this->addColumnIfMissing('lbp_etats_journaliers', 'solde_physique_declare', "DECIMAL(15,2) NULL");
            $this->addColumnIfMissing('lbp_etats_journaliers', 'ecart_caisse', "DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            $this->addColumnIfMissing('lbp_etats_journaliers', 'explication_ecart', "TEXT NULL");
            $this->addColumnIfMissing('lbp_etats_journaliers', 'decompte_coupures_json', "TEXT NULL");
            $this->addColumnIfMissing('lbp_etats_journaliers', 'blind_count', "TINYINT(1) NOT NULL DEFAULT 1");
            $this->addColumnIfMissing('lbp_etats_journaliers', 'validation_superviseur_id', "INT NULL");
        }

        if ($this->schema->tableExists('lbp_demandes_paiement_prestataires')) {
            $this->addColumnIfMissing('lbp_demandes_paiement_prestataires', 'trajet_id', "INT UNSIGNED NULL");
        }

        if ($this->schema->tableExists('lbp_paiements')) {
            $this->addColumnIfMissing('lbp_paiements', 'mode_paiement', "ENUM('ESPECES', 'WAVE', 'ORANGE_MONEY', 'MTN_MOMO', 'CARTE', 'VIREMENT') NOT NULL DEFAULT 'ESPECES'");
        }
    }

    /**
     * Migration additive pour la refonte du module Colisage & Opération.
     */
    private function createColisageOperationRefactoTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS trajets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                libelle VARCHAR(150) NOT NULL,
                type_transport ENUM('cargo', 'maritime', 'aerien', 'rapide') NOT NULL,
                agence_depart_id INT UNSIGNED NULL,
                destination VARCHAR(150) NULL,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_trajets_agence_depart FOREIGN KEY (agence_depart_id) REFERENCES company_sites(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS factures_audit_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                facture_id INT UNSIGNED NOT NULL,
                modifie_par INT NOT NULL,
                date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                champ_modifie VARCHAR(100) NOT NULL,
                ancienne_valeur TEXT NULL,
                nouvelle_valeur TEXT NULL,
                CONSTRAINT fk_factures_audit_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE CASCADE,
                CONSTRAINT fk_factures_audit_user FOREIGN KEY (modifie_par) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_factures_audit_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                facture_id INT UNSIGNED NOT NULL,
                modifie_par INT NOT NULL,
                date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                champ_modifie VARCHAR(100) NOT NULL,
                ancienne_valeur TEXT NULL,
                nouvelle_valeur TEXT NULL,
                CONSTRAINT fk_lbp_factures_audit_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE CASCADE,
                CONSTRAINT fk_lbp_factures_audit_user FOREIGN KEY (modifie_par) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('lbp_factures', 'trajet_id', 'INT UNSIGNED NULL');
        $this->addColumnIfMissing('lbp_factures', 'agent_id', 'INT NULL');
        $this->addColumnIfMissing('lbp_factures', 'created_by', 'INT NULL');
        $this->addColumnIfMissing('lbp_factures', 'locked', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('lbp_factures', 'locked_at', 'DATETIME NULL');

        $this->addForeignKeyIfMissing('lbp_factures', 'fk_lbp_factures_trajet', 'trajet_id', 'trajets', 'id', 'SET NULL');
        $this->addForeignKeyIfMissing('lbp_factures', 'fk_lbp_factures_agent', 'agent_id', 'users', 'id', 'SET NULL');
        $this->addForeignKeyIfMissing('lbp_factures', 'fk_lbp_factures_created_by', 'created_by', 'users', 'id', 'SET NULL');

        // Index manquants sur les colonnes de filtre du module Recherche & Audit (Facturation)
        $this->addIndexIfMissing('lbp_factures', 'idx_lbp_factures_date_emission', 'date_emission');
        $this->addIndexIfMissing('lbp_clients', 'idx_lbp_clients_name', 'name');
        $this->addIndexIfMissing('factures_audit_log', 'idx_factures_audit_log_facture_id', 'facture_id');
        $this->addIndexIfMissing('factures_audit_log', 'idx_factures_audit_log_date', 'date_modification');
        $this->addIndexIfMissing('lbp_audit_logs', 'idx_lbp_audit_logs_entity', 'entity_type, entity_id');
        $this->addIndexIfMissing('lbp_audit_logs', 'idx_lbp_audit_logs_created_at', 'created_at');

        if ($this->schema->tableExists('lbp_colis')) {
            $this->addColumnIfMissing('lbp_colis', 'trajet_id', 'INT UNSIGNED NULL');
            $this->addForeignKeyIfMissing('lbp_colis', 'fk_lbp_colis_trajet', 'trajet_id', 'trajets', 'id', 'SET NULL');
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO trajets (code, libelle, type_transport, destination, actif) VALUES
            (:code, :libelle, :type_transport, :destination, 1)
        ");

        $trajetsSeed = [
            ['LB-CI', 'Abidjan → France', 'AÉRIEN', 'France'],
            ['LB-FR', 'France → Abidjan', 'AÉRIEN', 'Abidjan'],
            ['S-FR', 'Sénégal → France', 'AÉRIEN', 'France'],
            ['LB-CA', 'Abidjan → Canada', 'AÉRIEN', 'Canada'],
            ['F-SN', 'France → Sénégal', 'AÉRIEN', 'Sénégal'],
            ['CA-CI', 'Abidjan → Paris', 'rapide', 'Paris'],
            ['CA-FR', 'Paris → Abidjan', 'rapide', 'Abidjan'],
            ['CA-SN', 'Sénégal → Côte d\'Ivoire', 'rapide', 'Côte d\'Ivoire'],
            ['CA-IS', 'Côte d\'Ivoire → Sénégal', 'rapide', 'Sénégal'],
            ['CA-IC', 'Côte d\'Ivoire → Canada', 'rapide', 'Canada'],
            ['CA-CC', 'Canada → Abidjan', 'rapide', 'Abidjan'],
            ['DHL', 'Services DHL Express', 'rapide', 'International'],
        ];

        foreach ($trajetsSeed as [$code, $libelle, $type_transport, $destination]) {
            $stmt->execute([
                'code' => $code,
                'libelle' => $libelle,
                'type_transport' => $type_transport,
                'destination' => $destination,
            ]);
        }

        $this->pdo->exec("UPDATE trajets SET type_transport = 'AÉRIEN' WHERE code IN ('LB-CI', 'LB-FR', 'S-FR', 'LB-CA', 'F-SN') OR LOWER(type_transport) = 'maritime'");
    }

    /**
     * Tables présentes en production mais jamais créées par les migrations versionnées
     * (constat d'audit du module Recherche & Audit) : chaîne caisse/prestataires et
     * chaîne congés/paie RH. Schéma repris tel quel depuis la base de production.
     */
    private function createMissingProductionTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS company_settings (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                setting_label VARCHAR(255) NULL,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_company_settings_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_devises_taux (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                devise_source VARCHAR(10) NOT NULL,
                devise_cible VARCHAR(10) NOT NULL,
                taux DECIMAL(12,6) NOT NULL,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS lbp_expedition_status_history (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                expedition_id INT NOT NULL,
                statut_depart VARCHAR(50) NOT NULL,
                statut_arrive VARCHAR(50) NOT NULL,
                changed_by_user_id INT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->schema->tableExists('company_sites')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_caisses (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    agency_id INT UNSIGNED NOT NULL,
                    balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    status ENUM('OUVERTE', 'FERMEE') NOT NULL DEFAULT 'FERMEE',
                    updated_at DATETIME NULL,
                    UNIQUE KEY uniq_lbp_caisses_agency (agency_id),
                    CONSTRAINT fk_caisses_agency FOREIGN KEY (agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_credits_inter_agences (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    from_agency_id INT UNSIGNED NOT NULL,
                    to_agency_id INT UNSIGNED NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                    reason TEXT NULL,
                    status ENUM('EN_ATTENTE', 'VALIDE') NOT NULL DEFAULT 'EN_ATTENTE',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    reference_colis VARCHAR(100) NULL,
                    settled_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    KEY fk_credit_from (from_agency_id),
                    KEY fk_credit_to (to_agency_id),
                    CONSTRAINT fk_credit_from FOREIGN KEY (from_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_credit_to FOREIGN KEY (to_agency_id) REFERENCES company_sites(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('lbp_colis') && $this->schema->tableExists('lbp_expeditions')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_colis_expeditions (
                    colis_id INT UNSIGNED NOT NULL,
                    expedition_id INT UNSIGNED NOT NULL,
                    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (colis_id, expedition_id),
                    KEY fk_pivot_expedition (expedition_id),
                    CONSTRAINT fk_pivot_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE CASCADE,
                    CONSTRAINT fk_pivot_expedition FOREIGN KEY (expedition_id) REFERENCES lbp_expeditions(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('lbp_prestataires')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_factures_prestataires (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    prestataire_id INT UNSIGNED NOT NULL,
                    invoice_number VARCHAR(100) NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                    status ENUM('EN_ATTENTE', 'PAYEE', 'ANNULEE') NOT NULL DEFAULT 'EN_ATTENTE',
                    due_date DATE NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    lta_number VARCHAR(100) NULL,
                    issue_date DATE NULL,
                    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    notes TEXT NULL,
                    updated_at DATETIME NULL,
                    KEY fk_facture_prestataire (prestataire_id),
                    CONSTRAINT fk_facture_prestataire FOREIGN KEY (prestataire_id) REFERENCES lbp_prestataires(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('lbp_caisses')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_mouvements_caisse (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    caisse_id INT UNSIGNED NOT NULL,
                    type ENUM('ENTREE', 'DECAISSEMENT', 'APPRO') NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    justification VARCHAR(255) NULL,
                    recorded_by INT NULL,
                    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY fk_mouvements_caisse (caisse_id),
                    CONSTRAINT fk_mouvements_caisse FOREIGN KEY (caisse_id) REFERENCES lbp_caisses(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_points_caisse (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    caisse_id INT UNSIGNED NOT NULL,
                    declared_balance DECIMAL(15,2) NOT NULL,
                    theoretical_balance DECIMAL(15,2) NOT NULL,
                    status ENUM('EN_ATTENTE', 'VALIDE', 'REJETE') NOT NULL DEFAULT 'EN_ATTENTE',
                    rejection_reason TEXT NULL,
                    created_by INT NULL,
                    validated_by INT NULL,
                    validated_at DATETIME NULL,
                    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY fk_points_caisse (caisse_id),
                    CONSTRAINT fk_points_caisse FOREIGN KEY (caisse_id) REFERENCES lbp_caisses(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('lbp_factures_prestataires') && $this->schema->tableExists('users')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS lbp_retraits_prestataires (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    facture_id INT UNSIGNED NOT NULL,
                    amount_paid DECIMAL(15,2) NOT NULL,
                    currency ENUM('XOF', 'EUR') NOT NULL DEFAULT 'XOF',
                    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    recorded_by INT NOT NULL,
                    reference_transaction VARCHAR(100) NULL,
                    status ENUM('EN_ATTENTE', 'APPROUVE', 'REFUSE') NOT NULL DEFAULT 'EN_ATTENTE',
                    approved_by INT NULL,
                    approved_at DATETIME NULL,
                    rejection_reason TEXT NULL,
                    notes TEXT NULL,
                    updated_at DATETIME NULL,
                    KEY fk_retrait_facture (facture_id),
                    KEY fk_retrait_user (recorded_by),
                    CONSTRAINT fk_retrait_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures_prestataires(id) ON DELETE CASCADE,
                    CONSTRAINT fk_retrait_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('rh_employees')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rh_attendances (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT UNSIGNED NOT NULL,
                    date DATE NOT NULL,
                    check_in TIME NULL,
                    check_out TIME NULL,
                    total_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    status ENUM('present', 'absent', 'leave', 'holiday') NOT NULL DEFAULT 'present',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uniq_rh_attendances_emp_date (employee_id, date),
                    CONSTRAINT fk_rh_attendances_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->schema->tableExists('rh_contracts')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rh_contract_allowances (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    contract_id INT UNSIGNED NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    is_taxable TINYINT(1) NOT NULL DEFAULT 0,
                    KEY idx_rh_contract_allowances_contract (contract_id),
                    CONSTRAINT fk_rh_contract_allowances_contract FOREIGN KEY (contract_id) REFERENCES rh_contracts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Table autonome : clé unique sur name ajoutée pour empêcher la ré-insertion en doublon
        // constatée en production (683 doublons des 5 mêmes libellés, cf. audit du 2026-08-04).
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_leave_types (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                is_paid TINYINT(1) NOT NULL DEFAULT 1,
                deduct_from_balance TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_leave_types_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->schema->tableExists('rh_employees')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rh_leave_requests (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT UNSIGNED NOT NULL,
                    leave_type_id INT UNSIGNED NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    duration_days DECIMAL(5,2) NOT NULL,
                    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                    reason TEXT NULL,
                    approved_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    KEY fk_rh_leave_requests_employee (employee_id),
                    KEY fk_rh_leave_requests_type (leave_type_id),
                    CONSTRAINT fk_rh_leave_requests_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE,
                    CONSTRAINT fk_rh_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES rh_leave_types(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rh_payroll_campaigns (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
            CREATE TABLE IF NOT EXISTS rh_payroll_parameters (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                year INT UNSIGNED NOT NULL,
                smig DECIMAL(10,2) NOT NULL DEFAULT 75000.00,
                cnps_ceiling DECIMAL(12,2) NOT NULL DEFAULT 1647315.00,
                cnps_employee_rate DECIMAL(5,2) NOT NULL DEFAULT 3.20,
                cnps_employer_rate DECIMAL(5,2) NOT NULL DEFAULT 7.70,
                cmu_employee_rate DECIMAL(5,2) NOT NULL DEFAULT 2.00,
                cmu_employer_rate DECIMAL(5,2) NOT NULL DEFAULT 2.00,
                cn_rate DECIMAL(5,2) NOT NULL DEFAULT 1.50,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_rh_payroll_params_year (year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->schema->tableExists('rh_employees')) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rh_payslips (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT UNSIGNED NOT NULL,
                    campaign_id INT UNSIGNED NOT NULL,
                    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    total_allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    cnps_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    cmu_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    its_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    payment_method VARCHAR(50) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_rh_payslips_emp_camp (employee_id, campaign_id),
                    KEY fk_rh_payslips_campaign (campaign_id),
                    CONSTRAINT fk_rh_payslips_campaign FOREIGN KEY (campaign_id) REFERENCES rh_payroll_campaigns(id) ON DELETE CASCADE,
                    CONSTRAINT fk_rh_payslips_employee FOREIGN KEY (employee_id) REFERENCES rh_employees(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rh_payslip_lines (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    payslip_id INT UNSIGNED NOT NULL,
                    type ENUM('gain', 'deduction') NOT NULL,
                    label VARCHAR(150) NOT NULL,
                    base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    rate DECIMAL(5,2) NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    is_taxable TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    KEY fk_rh_payslip_lines_payslip (payslip_id),
                    CONSTRAINT fk_rh_payslip_lines_payslip FOREIGN KEY (payslip_id) REFERENCES rh_payslips(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Purge définitivement toutes les données fictives des emballages, portefeuilles et rapprochements
        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            $this->pdo->exec("TRUNCATE TABLE lbp_emballages_mouvements;");
            $this->pdo->exec("TRUNCATE TABLE lbp_emballages_stocks;");
            $this->pdo->exec("TRUNCATE TABLE lbp_emballages_catalogue;");
            $this->pdo->exec("TRUNCATE TABLE lbp_mobile_money_reconciliations;");
            $this->pdo->exec("TRUNCATE TABLE lbp_landed_costs;");
            $this->pdo->exec("TRUNCATE TABLE lbp_client_wallet_transactions;");
            $this->pdo->exec("TRUNCATE TABLE lbp_client_wallets;");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        } catch (\Throwable $e) {
            // Ignore silence
        }
    }
}
