<?php
/**
 * Script de vérification des permissions pour Claude Yedess
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
$stmt = $pdo->prepare('SELECT id, full_name, is_admin, status FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    die("[-] Utilisateur avec l'email '{$email}' introuvable.\n");
}

echo "=== UTILISATEUR ===\n";
echo "ID: " . $user['id'] . "\n";
echo "Nom: " . $user['full_name'] . "\n";
echo "Statut: " . $user['status'] . "\n";
echo "Administrateur: " . ($user['is_admin'] ? "OUI (⚠️ Attention, bypass toutes les restrictions)" : "NON (✅ Correct)") . "\n\n";

// 2. Rôles
$stmt = $pdo->prepare('SELECT role FROM lbp_user_roles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== ROLES ===\n";
echo "Rôles affectés: " . implode(', ', $roles) . "\n";
echo "Rôle assistant_dg : " . (in_array('assistant_dg', $roles) ? "✅ OK" : "❌ MANQUANT") . "\n\n";

// 3. Permissions de modification/suppression
$stmt = $pdo->prepare('
    SELECT e.code, p.can_update, p.can_delete
    FROM user_permissions p
    INNER JOIN permission_entities e ON e.id = p.entity_id
    WHERE p.user_id = ? AND (p.can_update = 1 OR p.can_delete = 1)
');
$stmt->execute([$user['id']]);
$forbidden = $stmt->fetchAll();

echo "=== PERMISSIONS INTERDITES (UPDATE/DELETE) ===\n";
if (empty($forbidden)) {
    echo "✅ OK: Aucune permission de modification (UPDATE) ou suppression (DELETE) active en BDD.\n";
} else {
    echo "⚠️ ATTENTION : Des permissions de modification/suppression sont actives en BDD :\n";
    foreach ($forbidden as $f) {
        echo "  - " . $f['code'] . " : update=" . $f['can_update'] . ", delete=" . $f['can_delete'] . "\n";
    }
}
echo "\n";
