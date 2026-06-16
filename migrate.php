<?php
require_once __DIR__ . '/bootstrap/app.php';
$pdo = \App\Models\Database::getConnection();
(new \App\Database\MigrationRunner($pdo))->run();
echo "Migration finished.\n";
