<?php
/**
 * Script de mise à jour des rôles pour Siaka Diarra (siaka.diarra@labelleporte.ci)
 * Rôle attribué : caissiere_principale
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

$email = 'siaka.diarra@labelleporte.ci';

// 1. Trouver l'utilisateur
$stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    die("[-] Utilisateur avec l'email '{$email}' introuvable.\n");
}

echo "[*] Utilisateur trouve : " . $user['full_name'] . " (ID: " . $user['id'] . ")\n";

// 2. Vérifier s'il a déjà le rôle
$stmt = $pdo->prepare('SELECT COUNT(*) FROM lbp_user_roles WHERE user_id = ? AND role = ?');
$stmt->execute([$user['id'], 'caissiere_principale']);
$hasRole = (int) $stmt->fetchColumn() > 0;

if ($hasRole) {
    echo "[+] L'utilisateur a deja le role 'caissiere_principale'.\n";
} else {
    // Insérer le rôle
    $stmt = $pdo->prepare('INSERT INTO lbp_user_roles (user_id, role) VALUES (?, ?)');
    $stmt->execute([$user['id'], 'caissiere_principale']);
    echo "[+] Succes : Role 'caissiere_principale' attribue avec succes.\n";
}

// 3. Afficher tous les rôles de l'utilisateur pour validation
$stmt = $pdo->prepare('SELECT role FROM lbp_user_roles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "[*] Liste des roles actuels de l'utilisateur : " . implode(', ', $roles) . "\n";
