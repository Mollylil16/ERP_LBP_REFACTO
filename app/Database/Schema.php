<?php

namespace App\Database;

use PDO;

class Schema
{
    public function __construct(private PDO $pdo) {}

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            AND table_name = :table
        ");
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND column_name = :column
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Retourne la définition SQL du type d'une colonne (ex: "enum('a','b')"),
     * ou null si la colonne n'existe pas.
     */
    public function columnType(string $table, string $column): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT column_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND column_name = :column
            LIMIT 1
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column
        ]);

        $type = $stmt->fetchColumn();

        return $type === false ? null : (string) $type;
    }

    public function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND index_name = :index
        ");
        $stmt->execute([
            'table' => $table,
            'index' => $index
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function foreignKeyExists(string $table, string $constraint): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.table_constraints 
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND constraint_name = :constraint
            AND constraint_type = 'FOREIGN KEY'
        ");
        $stmt->execute([
            'table' => $table,
            'constraint' => $constraint
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
