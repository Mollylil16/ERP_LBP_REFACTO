<?php

namespace App\Database;

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
        $this->addColumnIfMissing('users', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
        $this->addColumnIfMissing('users', 'updated_at', "DATETIME NULL");

        $this->addUniqueIndexIfMissing('users', 'uniq_users_email', 'email');
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
