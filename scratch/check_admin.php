<?php
require __DIR__ . '/../bootstrap/app.php';
$pdo = \App\Models\Database::getConnection();
$stmt = $pdo->query("SELECT * FROM users WHERE is_admin = 1 OR email LIKE '%admin%' LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
