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
        foreach ([['Siege Abidjan','ABJ-HQ','Cote d Ivoire','Abidjan'], ['Agence San Pedro','SPY','Cote d Ivoire','San Pedro'], ['Bureau international','INTL','International', null]] as [$name,$code,$country,$city]) {
            $stmt->execute(['name'=>$name,'code'=>$code,'country'=>$country,'city'=>$city]);
        }
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
