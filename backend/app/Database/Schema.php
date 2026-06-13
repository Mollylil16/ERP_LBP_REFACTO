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
            WHERE table_schema = 'public'
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
            WHERE table_schema = 'public'
            AND table_name = :table
            AND column_name = :column
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM pg_indexes 
            WHERE schemaname = 'public'
            AND tablename = :table
            AND indexname = :index
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
            WHERE table_schema = 'public'
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
