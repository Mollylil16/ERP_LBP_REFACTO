<?php

namespace App\Database;

use PDO;

class MigrationRunner
{
    public function __construct(private PDO $pdo) {}

    public function run(): void
    {
        // Crée la table de suivi des migrations si nécessaire
        $this->ensureMigrationsTable();

        // Cherche des fichiers .sql dans plusieurs dossiers possibles
        $candidates = [
            __DIR__ . '/../../../database/migrations', // backend/database/migrations
            __DIR__ . '/../../../../database/migrations',
            __DIR__ . '/../../../app/database/migrations',
        ];

        $appliedAny = false;

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.sql');
            sort($files);
            foreach ($files as $file) {
                $name = basename($file);
                if ($this->isApplied($name)) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    continue;
                }

                try {
                    $this->pdo->beginTransaction();
                    $this->pdo->exec($sql);
                    $this->recordMigration($name);
                    $this->pdo->commit();
                    $appliedAny = true;
                } catch (\Throwable $e) {
                    $this->pdo->rollBack();
                    // log minimalement et continuer
                    error_log('Migration failed: ' . $name . ' — ' . $e->getMessage());
                }
            }
        }

        // Si aucune migration SQL trouvée/appliquée, appliquer un jeu de tables minimales
        if (!$appliedAny) {
            $this->applyBuiltinRbac();
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
  id SERIAL PRIMARY KEY,
  migration VARCHAR(255) NOT NULL UNIQUE,
  applied_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
SQL
        );
    }

    private function isApplied(string $migration): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = :m LIMIT 1');
        $stmt->execute([':m' => $migration]);
        return (bool) $stmt->fetchColumn();
    }

    private function recordMigration(string $migration): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:m) ON CONFLICT (migration) DO NOTHING');
        $stmt->execute([':m' => $migration]);
    }

    /**
     * Applique un petit jeu de tables RBAC & agences attendu par le module RH.
     * Idempotent: utilise IF NOT EXISTS.
     */
    private function applyBuiltinRbac(): void
    {
        $sql = <<<'SQL'
BEGIN;

CREATE TABLE IF NOT EXISTS lbp_roles (
  id SERIAL PRIMARY KEY,
  nom_role VARCHAR(150) NOT NULL,
  description TEXT NULL
);

CREATE TABLE IF NOT EXISTS lbp_permissions (
  id SERIAL PRIMARY KEY,
  code VARCHAR(150) NOT NULL UNIQUE,
  description TEXT NULL
);

CREATE TABLE IF NOT EXISTS lbp_role_permissions (
  id SERIAL PRIMARY KEY,
  id_role INT NOT NULL REFERENCES lbp_roles(id) ON DELETE CASCADE,
  id_permission INT NOT NULL REFERENCES lbp_permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lbp_agences (
  id SERIAL PRIMARY KEY,
  nom_agence VARCHAR(200) NOT NULL,
  code_agence VARCHAR(50) NULL,
  adresse TEXT NULL,
  telephone VARCHAR(50) NULL,
  email VARCHAR(150) NULL
);

CREATE TABLE IF NOT EXISTS lbp_users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(150) UNIQUE,
  password VARCHAR(255) NULL,
  fullname VARCHAR(200) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(50) NULL,
  code_acces INT DEFAULT 0,
  "isActive" BOOLEAN DEFAULT TRUE,
  must_change_password BOOLEAN DEFAULT FALSE,
  agence_selected INT NULL REFERENCES lbp_agences(id),
  id_role INT NULL REFERENCES lbp_roles(id),
  id_agence INT NULL REFERENCES lbp_agences(id),
  password_plain TEXT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_lbp_users_role ON lbp_users(id_role);
CREATE INDEX IF NOT EXISTS idx_lbp_users_agence ON lbp_users(id_agence);

COMMIT;
SQL;

        try {
            $this->pdo->beginTransaction();
            $this->pdo->exec($sql);
            $this->recordMigration('builtin_initial_rbac');
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Builtin RBAC migration failed: ' . $e->getMessage());
        }
    }
}
