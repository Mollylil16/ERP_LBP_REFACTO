<?php
/**
 * Script de correction des permissions pour Claude Yedess
 */

define('BASE_PATH', __DIR__);
$config = require BASE_PATH . '/config/database.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("[-] Erreur de connexion à la base de données : " . $e->getMessage() . "\n");
}

$email = 'claude.yedess@labelleporte.ci';

// 1. Trouver l'utilisateur
$stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    die("[-] Utilisateur avec l'email '{$email}' introuvable.\n");
}

echo "Correction des permissions pour : " . $user['full_name'] . " (ID: " . $user['id'] . ")\n";

// 2. Mettre à jour les permissions pour retirer tous les droits de modification et de suppression
$stmt = $pdo->prepare('
    UPDATE user_permissions 
    SET can_update = 0, can_delete = 0 
    WHERE user_id = ?
');
$stmt->execute([$user['id']]);
$rowsAffected = $stmt->rowCount();

echo "[+] Succès : tous les droits de modification (UPDATE) et suppression (DELETE) ont été révoqués.\n";
echo "[+] Nombre de lignes modifiées en base : " . $rowsAffected . "\n\n";
