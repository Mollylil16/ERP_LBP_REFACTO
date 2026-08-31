<?php
/**
 * Script CLI pour attribuer l'ensemble des droits et rôles RH à soro.ibrahim@labelleporte.ci
 * Exécution : php grant_soro_rh_access.php
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

$email = 'soro.ibrahim@labelleporte.ci';

echo "===========================================================\n";
echo "    ATTRIBUTION DES DROITS RH — ERP LA BELLE PORTE        \n";
echo "===========================================================\n\n";

// 1. Rechercher l'utilisateur
$stmt = $pdo->prepare('SELECT id, full_name, email, status FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "[-] ERREUR : Aucun utilisateur trouvé avec l'email '{$email}'.\n";
    echo "[*] Recherche partielle sur le nom 'Soro'...\n";
    $stmtLike = $pdo->prepare("SELECT id, full_name, email FROM users WHERE full_name LIKE '%Soro%' OR email LIKE '%soro%'");
    $stmtLike->execute();
    $found = $stmtLike->fetchAll();
    if ($found) {
        echo "[+] Comptes trouvés pouvant correspondre :\n";
        foreach ($found as $u) {
            echo "    - ID: {$u['id']} | Nom: {$u['full_name']} | Email: {$u['email']}\n";
        }
    }
    die("\n[-] Veuillez créer le compte ou vérifier le libellé de l'email.\n");
}

$userId = (int) $user['id'];
echo "[+] Utilisateur identifié : {$user['full_name']} (ID: {$userId}, Email: {$user['email']})\n";

// 2. S'assurer que le compte est actif
if ($user['status'] !== 'active') {
    $stmtAct = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmtAct->execute([$userId]);
    echo "[+] Statut du compte mis à jour vers : ACTIVE\n";
}

// 3. Table des rôles lbp_user_roles
$pdo->exec("
    CREATE TABLE IF NOT EXISTS lbp_user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_role (user_id, role),
        KEY idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$rhRoles = ['rh', 'rh_agent', 'rh_manager', 'rh_responsable', 'rh_employee'];
$stmtRoleCheck = $pdo->prepare('SELECT COUNT(*) FROM lbp_user_roles WHERE user_id = ? AND role = ?');
$stmtRoleInsert = $pdo->prepare('INSERT INTO lbp_user_roles (user_id, role) VALUES (?, ?)');

echo "\n[*] Attribution des rôles RH dans lbp_user_roles...\n";
foreach ($rhRoles as $role) {
    $stmtRoleCheck->execute([$userId, $role]);
    if ((int) $stmtRoleCheck->fetchColumn() === 0) {
        $stmtRoleInsert->execute([$userId, $role]);
        echo "    [+] Rôle ajouté : {$role}\n";
    } else {
        echo "    [*] Rôle déjà présent : {$role}\n";
    }
}

// 4. Attribution des permissions granulaires dans user_permissions
$rhCodes = [
    'rh_employees', 'rh_employee_history', 'rh_employee_mutations',
    'rh_exit_reasons', 'rh_functions', 'rh_services', 'rh_statuses',
    'pointage', 'contrats', 'cycle_vie', 'conges', 'payroll'
];

echo "\n[*] Attribution des privilèges CRUD sur les entités RH dans user_permissions...\n";

// A. Si user_permissions possède la colonne entity_id
try {
    $stmtEntityId = $pdo->prepare("SELECT id, code FROM permission_entities WHERE code = ?");
    $stmtInsertPerm = $pdo->prepare("
        INSERT INTO user_permissions (user_id, entity_id, can_view, can_create, can_update, can_delete)
        VALUES (?, ?, 1, 1, 1, 1)
        ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_update = 1, can_delete = 1
    ");

    foreach ($rhCodes as $code) {
        $stmtEntityId->execute([$code]);
        $entId = $stmtEntityId->fetchColumn();
        if ($entId) {
            $stmtInsertPerm->execute([$userId, (int)$entId]);
            echo "    [+] Privilège par entity_id ({$code} -> ID {$entId}) accordé.\n";
        }
    }
} catch (\Throwable $e) {
    // Ignorer si le schéma est en mode legacy entity varchar
}

// B. Mode secours entity varchar
try {
    $stmtLegacyPerm = $pdo->prepare("
        INSERT INTO user_permissions (user_id, entity, can_read, can_write, can_delete)
        VALUES (?, ?, 1, 1, 1)
        ON DUPLICATE KEY UPDATE can_read = 1, can_write = 1, can_delete = 1
    ");

    foreach ($rhCodes as $code) {
        $stmtLegacyPerm->execute([$userId, $code]);
        echo "    [+] Privilège par entity code ({$code}) accordé.\n";
    }
} catch (\Throwable $e) {
    // Ignorer si la colonne entity varchar n'existe plus
}

// 5. Validation finale
$stmtRoles = $pdo->prepare('SELECT role FROM lbp_user_roles WHERE user_id = ?');
$stmtRoles->execute([$userId]);
$userRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

echo "\n===========================================================\n";
echo "    RÉSULTAT DE L'OPÉRATION                                \n";
echo "===========================================================\n";
echo "[✔] Utilisateur : {$user['full_name']} <{$user['email']}>\n";
echo "[✔] Rôles actuels : " . implode(', ', $userRoles) . "\n";
echo "[✔] Accès complet au Module RH accordé avec succès !\n\n";
