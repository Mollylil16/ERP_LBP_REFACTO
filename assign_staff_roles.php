<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Script d'attribution automatique des rôles, agences et permissions
 * pour la liste des utilisateurs du personnel (Liste PDF).
 * Compatible PHP 7.4+ et PHP 8.x
 */

$basePath = __DIR__;
if (!file_exists($basePath . '/config/database.php') && file_exists(dirname($basePath) . '/config/database.php')) {
    $basePath = dirname($basePath);
}

if (file_exists($basePath . '/vendor/autoload.php')) {
    require_once $basePath . '/vendor/autoload.php';
}

$isCli = (php_sapi_name() === 'cli');

function out($msg, $type = 'info') {
    global $isCli;
    $prefix = '[*] ';
    if ($type === 'success') {
        $prefix = '[✔] ';
    } elseif ($type === 'error') {
        $prefix = '[-] ';
    } elseif ($type === 'warn') {
        $prefix = '[!] ';
    }

    if ($isCli) {
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = '#0c5460; background-color: #d1ecf1; border-color: #bee5eb;';
        if ($type === 'success') {
            $color = '#155724; background-color: #d4edda; border-color: #c3e6cb;';
        } elseif ($type === 'error') {
            $color = '#721c24; background-color: #f8d7da; border-color: #f5c6cb;';
        } elseif ($type === 'warn') {
            $color = '#856404; background-color: #fff3cd; border-color: #ffeeba;';
        }
        echo "<div style=\"padding:6px 12px; margin:4px 0; border:1px solid; border-radius:4px; color:{$color}; font-family:monospace;\">"
             . htmlspecialchars($prefix . $msg) . "</div>\n";
    }

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Attribution Rôles & Agences</title></head><body style='font-family:sans-serif; padding:20px; background:#f8f9fa;'>";
    echo "<h2>Attribution des Rôles, Agences & Permissions (Liste Personnel)</h2>";
}

out("Version PHP : " . PHP_VERSION, "info");
out("Dossier racine : " . $basePath, "info");

// Connexion BDD
$configFile = $basePath . '/config/database.php';
if (!file_exists($configFile)) {
    out("Fichier config/database.php introuvable : {$configFile}", "error");
    if (!$isCli) echo "</body></html>";
    exit(1);
}

$config = require $configFile;
$pdo = null;

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    out("Connexion à la base {$config['dbname']} réussie.", "success");
} catch (Exception $e) {
    out("Erreur MySQL : " . $e->getMessage(), "error");
    if (!$isCli) echo "</body></html>";
    exit(1);
}

// 1. Structure des tables requises
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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        entity_id INT NULL,
        can_view TINYINT(1) NOT NULL DEFAULT 0,
        can_create TINYINT(1) NOT NULL DEFAULT 0,
        can_update TINYINT(1) NOT NULL DEFAULT 0,
        can_delete TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_entity (user_id, entity_id),
        KEY idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Fonction de validation du site ID par rapport à company_sites
function resolveValidSiteId($pdo, $targetSiteId) {
    if ($targetSiteId === null || $targetSiteId === 1) {
        // Vérifier si le site 1 existe
        $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE id = ?");
        $stmt->execute([$targetSiteId]);
        if ($stmt->fetchColumn()) {
            return (int)$targetSiteId;
        }
        return null; // Siège / Multi-agence
    }
    $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE id = ?");
    $stmt->execute([$targetSiteId]);
    if ($stmt->fetchColumn()) {
        return (int)$targetSiteId;
    }
    return null;
}

// 2. Définition stricte des 15 utilisateurs de la liste PDF
$staffList = [
    [
        'name'        => 'AKOIBLIN ROXANE',
        'poste'       => "CHEF D'AGENCE ADJAME",
        'exact_emails'=> ['roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci'],
        'name_pattern'=> '%AKOIBLIN%',
        'default_mail'=> 'roxane.akoiblin@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 1, 0],
            'modifier_facture_apres_creation' => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'KOUAKOU SALES',
        'poste'       => 'RESPONSABLE GROUPAGE',
        'exact_emails'=> ['sales.kouakou@labelleporte.ci'],
        'name_pattern'=> '%SALES%',
        'default_mail'=> 'sales.kouakou@labelleporte.ci',
        'role'        => 'agent_groupage',
        'agence_id'   => 1, // Siège
        'agence_name' => 'Siège Abidjan',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'DIARRA SIAKA',
        'poste'       => "CHEF D'AGENCE DOKUI",
        'exact_emails'=> ['siaka.diarra@labelleporte.ci'],
        'name_pattern'=> '%SIAKA%DIARRA%',
        'default_mail'=> 'siaka.diarra@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3403, // Abobo Dokui
        'agence_name' => 'Agence Abobo Dokui',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 1, 0],
            'modifier_facture_apres_creation' => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'KOUAME YVETTE',
        'poste'       => 'AGENT DE SAISIE DOKUI',
        'exact_emails'=> ['yvette.kouame@labelleporte.ci', 'grace.kouame@labelleporte.ci'],
        'name_pattern'=> '%KOUAME%',
        'default_mail'=> 'yvette.kouame@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3403, // Abobo Dokui
        'agence_name' => 'Agence Abobo Dokui',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'KOLI KONAN ANICET',
        'poste'       => 'AGENT DE SAISIE DOKUI',
        'exact_emails'=> ['anicet.koli@labelleporte.ci'],
        'name_pattern'=> '%KOLI%ANICET%',
        'default_mail'=> 'anicet.koli@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3403, // Abobo Dokui
        'agence_name' => 'Agence Abobo Dokui',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'ABOU CARINE (Mme AGBADAN)',
        'poste'       => 'CAISSIERE DOKUI',
        'exact_emails'=> ['carine.abou@labelleporte.ci'],
        'name_pattern'=> '%AGBADAN%',
        'default_mail'=> 'carine.abou@labelleporte.ci',
        'role'        => 'caissiere',
        'agence_id'   => 3403, // Abobo Dokui
        'agence_name' => 'Agence Abobo Dokui',
        'permissions' => [
            'saisir_facture'        => [1, 1, 0, 0],
            'finance_retraits'      => [1, 1, 0, 0],
            'colisage_colis'        => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'rapports_agence'       => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'ABASSI WILFRIED',
        'poste'       => 'RESPONSABLE MARKETING ET COMMUNICATION',
        'exact_emails'=> ['wilfried.abassi@labelleporte.ci'],
        'name_pattern'=> '%ABASSI%',
        'default_mail'=> 'wilfried.abassi@labelleporte.ci',
        'role'        => 'responsable_marketing',
        'agence_id'   => 1, // Siège
        'agence_name' => 'Siège Abidjan',
        'permissions' => [
            'crm_clients'           => [1, 1, 1, 0],
            'crm_opportunities'     => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'LASSICI MARIAM',
        'poste'       => 'AGENT CALL CENTER',
        'exact_emails'=> ['mariam.lassici@labelleporte.ci'],
        'name_pattern'=> '%LASSICI%',
        'default_mail'=> 'mariam.lassici@labelleporte.ci',
        'role'        => 'agent_call_center',
        'agence_id'   => 1, // Siège
        'agence_name' => 'Siège Abidjan',
        'permissions' => [
            'call_center_view'      => [1, 1, 0, 0],
            'call_center_manage'    => [1, 1, 1, 0],
            'colisage_colis'        => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'KOFFI MARQUEZ',
        'poste'       => "CHEF D'AGENCE AEROPORT",
        'exact_emails'=> ['marquez.koffi@labelleporte.ci'],
        'name_pattern'=> '%MARQUEZ%',
        'default_mail'=> 'marquez.koffi@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3402, // Aéroport Port-Bouët Fret
        'agence_name' => 'Aéroport Port Bouët Fret',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 1, 0],
            'modifier_facture_apres_creation' => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'ASSOMA ASSI JEAN EUDES',
        'poste'       => 'AGENT DE SAISIE AEROPORT',
        'exact_emails'=> ['jeaneudes.assoma@labelleporte.ci'],
        'name_pattern'=> '%ASSOMA%',
        'default_mail'=> 'jeaneudes.assoma@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3402, // Aéroport Port-Bouët Fret
        'agence_name' => 'Aéroport Port Bouët Fret',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'ADEPO MARIE ESTHER',
        'poste'       => "CHEF D'AGENCE SENEGAL",
        'exact_emails'=> ['estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci'],
        'name_pattern'=> '%ADEPO%ESTHER%',
        'default_mail'=> 'estelle.adepo@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3401, // Agence Sénégal
        'agence_name' => 'Agence Sénégal - Dakar',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 1, 0],
            'modifier_facture_apres_creation' => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
        ]
    ],
    [
        'name'        => 'DJAMBITCHE SARAH STEPHANIE',
        'poste'       => 'AGENT DE SAISIE ADJAME',
        'exact_emails'=> ['sarah.djambitche@labelleporte.ci'],
        'name_pattern'=> '%SARAH%DJAMBITCHE%',
        'default_mail'=> 'sarah.djambitche@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'KARABBOUE AMY',
        'poste'       => 'AGENT DE SAISIE ADJAME',
        'exact_emails'=> ['amy.dieng@labelleporte.ci', 'amy.karabboue@labelleporte.ci'],
        'name_pattern'=> '%KARABBOUE%',
        'default_mail'=> 'amy.dieng@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'SERY GRACE',
        'poste'       => 'AGENT DE SAISIE ADJAME',
        'exact_emails'=> ['grace.sery@labelleporte.ci'],
        'name_pattern'=> '%SERY%GRACE%',
        'default_mail'=> 'grace.sery@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé',
        'permissions' => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    [
        'name'        => 'KADJO PRINCE',
        'poste'       => 'RESPONSABLE PARIS',
        'exact_emails'=> ['prince.kadjo@labelleporte.ci'],
        'name_pattern'=> '%PRINCE%KADJO%',
        'default_mail'=> 'prince.kadjo@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3400, // France Paris
        'agence_name' => 'Agence France - Paris',
        'permissions' => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 1, 0],
            'modifier_facture_apres_creation' => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
        ]
    ],
];

out("Attribution en cours pour les 15 utilisateurs du personnel...", "info");

$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (!$userCols) $userCols = [];
$hasPassword = in_array('password', $userCols, true);
$hasPasswordHash = in_array('password_hash', $userCols, true);
$defaultPassword = password_hash('lbp2026', PASSWORD_BCRYPT);

foreach ($staffList as $index => $staff) {
    $num = $index + 1;
    $validSiteId = resolveValidSiteId($pdo, $staff['agence_id']);

    // A. Recherche stricte
    $user = null;
    foreach ($staff['exact_emails'] as $em) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$em]);
        $user = $stmt->fetch();
        if ($user) break;
    }

    if (!$user && !empty($staff['name_pattern'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE full_name LIKE ? LIMIT 1");
        $stmt->execute([$staff['name_pattern']]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        // Création de l'utilisateur
        $insertFields = ['full_name', 'email', 'status', 'is_admin'];
        $insertValues = [':full_name', ':email', "'active'", 0];
        $params = [
            'full_name' => $staff['name'],
            'email'     => $staff['default_mail'],
        ];

        if ($validSiteId !== null) {
            $insertFields[] = 'agence_id';
            $insertValues[] = ':agence_id';
            $params['agence_id'] = $validSiteId;
        }

        if ($hasPassword) {
            $insertFields[] = 'password';
            $insertValues[] = ':pwd';
            $params['pwd']  = $defaultPassword;
        }
        if ($hasPasswordHash) {
            $insertFields[] = 'password_hash';
            $insertValues[] = ':pwd_hash';
            $params['pwd_hash'] = $defaultPassword;
        }

        $sql = "INSERT INTO users (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute($params);
        $userId = (int) $pdo->lastInsertId();
        out("[{$num}/15] [Créé] {$staff['name']} <{$staff['default_mail']}> (ID: {$userId}) -> Agence ID: " . ($validSiteId ?? 'Siège'), "success");
        $userId = (int) $user['id'];
        $stmtUp = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, agence_id = :agence_id, status = 'active' WHERE id = :id");
        $stmtUp->execute([
            'full_name' => $staff['name'],
            'email'     => $staff['default_mail'],
            'agence_id' => $validSiteId,
            'id'        => $userId
        ]);
        out("[{$num}/15] [Mis à jour] {$staff['name']} <{$staff['default_mail']}> (ID: {$userId}) -> Agence ID: " . ($validSiteId ?? 'Siège'), "success");

    // B. Rôle dans lbp_user_roles
    $pdo->prepare("DELETE FROM lbp_user_roles WHERE user_id = ?")->execute([$userId]);
    $stmtRole = $pdo->prepare("INSERT INTO lbp_user_roles (user_id, role) VALUES (?, ?)");
    $stmtRole->execute([$userId, $staff['role']]);
    out("    -> Rôle assigné : {$staff['role']}", "info");

    // C. Permissions
    $stmtEnt = $pdo->prepare("SELECT id FROM permission_entities WHERE code = ? LIMIT 1");
    $stmtPerm = $pdo->prepare("
        INSERT INTO user_permissions (user_id, entity_id, can_view, can_create, can_update, can_delete)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_update = VALUES(can_update), can_delete = VALUES(can_delete)
    ");

    foreach ($staff['permissions'] as $entCode => $rights) {
        $stmtEnt->execute([$entCode]);
        $entId = $stmtEnt->fetchColumn();
        if ($entId) {
            $stmtPerm->execute([$userId, (int)$entId, $rights[0], $rights[1], $rights[2], $rights[3]]);
        }
    }
    out("    -> " . count($staff['permissions']) . " entités de permissions configurées.", "info");

    // D. Liaison rh_employees si présente
    try {
        $stmtEmp = $pdo->prepare("
            UPDATE rh_employees 
            SET user_id = :user_id, site_id = :site_id, agence_id = :site_id, poste = :poste, updated_at = NOW() 
            WHERE (email = :email OR user_id = :user_id)
        ");
        $stmtEmp->execute([
            'user_id'   => $userId,
            'site_id'   => $validSiteId,
            'poste'     => $staff['poste'],
            'email'     => $staff['default_mail'],
        ]);
    } catch (Exception $e) {}
}

out("\n===========================================================", "success");
out("  ATTRIBUTION TERMINÉE AVEC SUCCÈS POUR LES 15 COLLABORATEURS", "success");
out("===========================================================", "success");

if (!$isCli) {
    echo "<p style='margin-top:20px;'><a href='/verify_staff_roles.php' style='display:inline-block; padding:10px 20px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px;'>Lancer la Vérification</a> ";
    echo "<a href='/admin/users' style='display:inline-block; padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:4px;'>Aller aux Utilisateurs</a></p>";
    echo "</body></html>";
}
