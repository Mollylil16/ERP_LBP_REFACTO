<?php
/**
 * =========================================================================
 *  Script d'initialisation et d'attribution du rôle Suivi & Recouvrement
 *  pour Sylvestre KICHI (sylvestre.kichi@labelleporte.ci)
 *  Agence : Aéroport Port-Bouët Fret (ABJ-FRET)
 * =========================================================================
 * 
 *  Usage CLI :
 *    php setup_suivi_recouvrement.php
 * 
 *  Usage Navigateur :
 *    https://labelleporte.ci/setup_suivi_recouvrement.php (puis supprimer)
 * =========================================================================
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

$isCli = (PHP_SAPI === 'cli');

function out(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[+] ',
            'error'   => '[-] ',
            'warn'    => '[!] ',
            default   => '[*] ',
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#16a34a',
            'error'   => '#dc2626',
            'warn'    => '#d97706',
            default   => '#2563eb',
        };
        echo "<p style='color:{$color}; font-family:monospace; margin:4px 0;'><strong>" . htmlspecialchars($msg) . "</strong></p>";
    }
}

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    if (!$isCli) {
        echo "<h1 style='color:red'>Erreur de connexion à la base de données</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    } else {
        echo "[-] Erreur de connexion : " . $e->getMessage() . PHP_EOL;
    }
    exit(1);
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Setup Suivi & Recouvrement</title></head><body style='font-family:sans-serif; padding:20px; background:#f8fafc;'>";
    echo "<h2>Initialisation du rôle Suivi & Recouvrement — Sylvestre KICHI</h2>";
}

out("Démarrage de la configuration du rôle Suivi & Recouvrement...");

// 1. Création de la table lbp_user_roles si absente
$pdo->exec("
    CREATE TABLE IF NOT EXISTS lbp_user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role VARCHAR(64) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_role (user_id, role),
        KEY idx_user_id (user_id),
        KEY idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
try {
    $pdo->exec("ALTER TABLE lbp_user_roles MODIFY COLUMN role VARCHAR(64) NOT NULL");
} catch (\Throwable $e) {}

// 2. Recherche du site Aéroport Port-Bouët Fret
$stmtSite = $pdo->query("SELECT id, name, code FROM company_sites WHERE code = 'ABJ-FRET' OR name LIKE '%Aéroport%' OR name LIKE '%Fret%' ORDER BY (code = 'ABJ-FRET') DESC LIMIT 1");
$site = $stmtSite ? $stmtSite->fetch() : null;

$fretAgId = $site ? (int) $site['id'] : 3402;
$fretAgName = $site ? $site['name'] : 'Aéroport Port Bouët Fret (ID 3402)';
out("Agence Aéroport Fret cible : {$fretAgName} (ID: {$fretAgId})", "success");

// 3. Recherche de Sylvestre KICHI
$stmtUsr = $pdo->query("
    SELECT id, full_name, email, status, agence_id, is_admin 
    FROM users 
    WHERE email LIKE '%sylvestre.kichi%' 
       OR email LIKE '%sylvestre%' 
       OR full_name LIKE '%SYLVESTRE%' 
       OR full_name LIKE '%Kichi%' 
    ORDER BY (email LIKE '%sylvestre.kichi%') DESC 
    LIMIT 1
");
$user = $stmtUsr ? $stmtUsr->fetch() : null;

if (!$user) {
    out("Utilisateur Sylvestre KICHI introuvable dans la table users.", "error");
    if (!$isCli) echo "</body></html>";
    exit(1);
}

$userId = (int) $user['id'];
out("Utilisateur trouvé : {$user['full_name']} (ID: {$userId}, Email actuel: {$user['email']})", "success");

// 4. Détection dynamique des colonnes de `users` (password vs password_hash)
try {
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $hasPassword = in_array('password', $userCols, true);
    $hasPasswordHash = in_array('password_hash', $userCols, true);
    $hasUpdatedAt = in_array('updated_at', $userCols, true);

    $hash = password_hash('lbp2026', PASSWORD_BCRYPT);
    $targetEmail = 'sylvestre.kichi@labelleporte.ci';

    $updates = [
        'agence_id = :agence_id',
        "status = 'active'",
        'email = :email',
    ];
    $params = [
        'agence_id' => $fretAgId,
        'email'     => $targetEmail,
        'id'        => $userId,
    ];

    if ($hasPassword) {
        $updates[] = 'password = :pwd';
        $params['pwd'] = $hash;
    }
    if ($hasPasswordHash) {
        $updates[] = 'password_hash = :pwd_hash';
        $params['pwd_hash'] = $hash;
    }
    if ($hasUpdatedAt) {
        $updates[] = 'updated_at = NOW()';
    }

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmtUpUser = $pdo->prepare($sql);
    $stmtUpUser->execute($params);

    out("Compte utilisateur mis à jour : Email '{$targetEmail}', Mot de passe 'lbp2026', Agence ID {$fretAgId}, Statut 'active'.", "success");
} catch (\Throwable $e) {
    out("Erreur mise à jour compte users : " . $e->getMessage(), "error");
}

// 5. Mise à jour de l'employé RH
try {
    $stmtEmp = $pdo->prepare("UPDATE rh_employees SET site_id = :site_id, email = :email WHERE email LIKE '%sylvestre%' OR id = 1013");
    $stmtEmp->execute([
        'site_id' => $fretAgId,
        'email'   => $targetEmail,
    ]);
    out("Fiche employé RH mise à jour avec l'affectation au site Aéroport Fret.", "success");
} catch (\Throwable $e) {
    out("Note employé RH : " . $e->getMessage(), "warn");
}

// 6. Attribution des rôles
$rolesToAdd = ['suivi_recouvrement', 'agent_groupage'];
$stmtRole = $pdo->prepare("INSERT IGNORE INTO lbp_user_roles (user_id, role) VALUES (:user_id, :role)");

foreach ($rolesToAdd as $r) {
    try {
        $stmtRole->execute(['user_id' => $userId, 'role' => $r]);
        out("Rôle attribué avec succès : {$r}", "success");
    } catch (\Throwable $e) {
        out("Note attribution rôle {$r} : " . $e->getMessage(), "warn");
    }
}

// 7. Attribution des permissions fonctionnelles
try {
    $permEntities = $pdo->query("SELECT id, code, name FROM permission_entities WHERE is_active = 1")->fetchAll() ?: [];
    if (!empty($permEntities)) {
        $stmtPerm = $pdo->prepare("
            INSERT INTO user_permissions (user_id, entity_id, can_view, can_create, can_update, can_delete)
            VALUES (:user_id, :entity_id, :can_view, :can_create, :can_update, :can_delete)
            ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_update = VALUES(can_update), can_delete = VALUES(can_delete)
        ");

        $targetCodes = [
            'exploitation_synthese' => [1, 1, 1, 0],
            'exploitation_tracking' => [1, 1, 1, 0],
            'exploitation_credits' => [1, 1, 1, 0],
            'exploitation_fournitures' => [1, 1, 1, 0],
            'rapports_agence' => [1, 1, 0, 0],
            'exporter_rapports_excel' => [1, 0, 0, 0],
            'saisir_facture' => [1, 1, 1, 0],
            'call_center_view' => [1, 0, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
            'exporter_facturation_avec_montant' => [1, 0, 0, 0],
        ];

        foreach ($permEntities as $pe) {
            $code = (string) $pe['code'];
            if (isset($targetCodes[$code])) {
                [$cv, $cc, $cu, $cd] = $targetCodes[$code];
                $stmtPerm->execute([
                    'user_id' => $userId,
                    'entity_id' => (int) $pe['id'],
                    'can_view' => $cv,
                    'can_create' => $cc,
                    'can_update' => $cu,
                    'can_delete' => $cd,
                ]);
                out("Permission accordée sur '{$code}' ({$pe['name']})", "info");
            }
        }
    }
} catch (\Throwable $e) {
    out("Note permissions fonctionnelles : " . $e->getMessage(), "warn");
}

// 8. Récapitulatif final
try {
    $stmtFinalRoles = $pdo->prepare("SELECT role FROM lbp_user_roles WHERE user_id = ?");
    $stmtFinalRoles->execute([$userId]);
    $userRoles = $stmtFinalRoles->fetchAll(PDO::FETCH_COLUMN) ?: [];

    out("=========================================================================");
    out("CONFIGURATION VALIDÉE AVEC SUCCÈS !", "success");
    out("Utilisateur : Sylvestre KICHI");
    out("Email : sylvestre.kichi@labelleporte.ci");
    out("Mot de passe : lbp2026");
    out("Agence : {$fretAgName} (ID: {$fretAgId})");
    out("Rôles actifs : " . implode(', ', $userRoles), "success");
    out("=========================================================================");
} catch (\Throwable $e) {}

if (!$isCli) {
    echo "</body></html>";
}
