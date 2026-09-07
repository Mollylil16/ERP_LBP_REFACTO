<?php
/**
 * Script d'attribution automatique des rôles, agences et permissions
 * pour la liste des utilisateurs du personnel (Liste PDF).
 *
 * Exécution CLI : php assign_staff_roles.php
 * Exécution Web : http://votre-domaine/assign_staff_roles.php
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/vendor/autoload.php';

// Activer l'affichage des erreurs et le vidage de buffer
ini_set('display_errors', '1');
error_reporting(E_ALL);

function out(string $msg, string $type = 'info'): void {
    $prefix = match($type) {
        'success' => '[✔] ',
        'error'   => '[-] ',
        'warn'    => '[!] ',
        default   => '[*] '
    };
    echo $prefix . $msg . PHP_EOL;
    if (php_sapi_name() !== 'cli') {
        echo "<br>";
    }
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

out("Démarrage du script d'attribution des rôles et agences...");

$pdo = null;
try {
    $pdo = \App\Models\Database::getConnection();
    out("Connexion à la base de données réussie via Database::getConnection().", "success");
} catch (\Throwable $e) {
    out("Tentative avec config/database.php direct...", "warn");
    $config = require BASE_PATH . '/config/database.php';
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        out("Connexion directe réussie à {$config['dbname']}.", "success");
    } catch (\Throwable $err) {
        out("Erreur de connexion BDD : " . $err->getMessage(), "error");
        exit(1);
    }
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

// 2. Définition des 15 utilisateurs de la liste PDF
$staffList = [
    [
        'name'        => 'AKOIBLIN ROXANE',
        'poste'       => "CHEF D'AGENCE ADJAME",
        'email_match' => ['roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci', '%akoiblin%'],
        'default_mail'=> 'roxane.akoiblin@labelleporte.ci',
        'role'        => 'chef_agence',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé Pharmacie Latin',
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
        'email_match' => ['sales.kouakou@labelleporte.ci', '%sales%', '%kouakou%'],
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
        'email_match' => ['siaka.diarra@labelleporte.ci', '%siaka%'],
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
        'name'        => 'KOUAME GRACE',
        'poste'       => 'AGENT DE SAISIE DOKUI',
        'email_match' => ['grace.kouame@labelleporte.ci', '%grace.kouame%', '%kouame%grace%'],
        'default_mail'=> 'grace.kouame@labelleporte.ci',
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
        'email_match' => ['anicet.koli@labelleporte.ci', '%koli%', '%anicet%'],
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
        'email_match' => ['carine.abou@labelleporte.ci', '%carine.abou%', '%agbadan%'],
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
        'email_match' => ['wilfried.abassi@labelleporte.ci', '%abassi%'],
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
        'email_match' => ['mariam.lassici@labelleporte.ci', '%lassici%'],
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
        'email_match' => ['marquez.koffi@labelleporte.ci', '%marquez%'],
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
        'email_match' => ['jeaneudes.assoma@labelleporte.ci', '%assoma%', '%jean%eudes%'],
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
        'email_match' => ['estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci', '%adepo%'],
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
        'email_match' => ['sarah.djambitche@labelleporte.ci', '%sarah%djambitche%'],
        'default_mail'=> 'sarah.djambitche@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé Pharmacie Latin',
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
        'email_match' => ['amy.dieng@labelleporte.ci', 'amy.karabboue@labelleporte.ci', '%amy%'],
        'default_mail'=> 'amy.dieng@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé Pharmacie Latin',
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
        'email_match' => ['grace.sery@labelleporte.ci', '%sery%', '%grace%sery%'],
        'default_mail'=> 'grace.sery@labelleporte.ci',
        'role'        => 'agent_saisie',
        'agence_id'   => 3404, // Adjamé
        'agence_name' => 'Agence Adjamé Pharmacie Latin',
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
        'email_match' => ['prince.kadjo@labelleporte.ci', '%prince%kadjo%'],
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

out("Traitement des 15 utilisateurs du personnel...", "info");

$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$hasPassword = in_array('password', $userCols, true);
$hasPasswordHash = in_array('password_hash', $userCols, true);
$defaultPassword = password_hash('lbp2026', PASSWORD_BCRYPT);

foreach ($staffList as $index => $staff) {
    $num = $index + 1;
    // A. Recherche de l'utilisateur existant
    $user = null;
    foreach ($staff['email_match'] as $matcher) {
        if (str_contains($matcher, '%')) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email LIKE ? OR full_name LIKE ? LIMIT 1");
            $stmt->execute([$matcher, $matcher]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$matcher]);
        }
        $user = $stmt->fetch();
        if ($user) break;
    }

    if (!$user) {
        // Création du compte s'il n'existe pas encore
        $insertFields = ['full_name', 'email', 'status', 'agence_id', 'is_admin'];
        $insertValues = [':full_name', ':email', "'active'", ':agence_id', 0];
        $params = [
            'full_name' => $staff['name'],
            'email'     => $staff['default_mail'],
            'agence_id' => $staff['agence_id'],
        ];

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
        out("[{$num}/15] Création du compte : {$staff['name']} <{$staff['default_mail']}> (ID: {$userId})", "success");
    } else {
        $userId = (int) $user['id'];
        // Mise à jour de l'agence et du statut
        $stmtUp = $pdo->prepare("UPDATE users SET agence_id = :agence_id, status = 'active' WHERE id = :id");
        $stmtUp->execute(['agence_id' => $staff['agence_id'], 'id' => $userId]);
        out("[{$num}/15] Utilisateur mis à jour : {$user['full_name']} (ID: {$userId}, Email: {$user['email']}) -> Agence {$staff['agence_name']} (ID {$staff['agence_id']})", "success");
    }

    // B. Attribution du rôle dans lbp_user_roles
    $pdo->prepare("DELETE FROM lbp_user_roles WHERE user_id = ?")->execute([$userId]);
    $stmtRole = $pdo->prepare("INSERT INTO lbp_user_roles (user_id, role) VALUES (?, ?)");
    $stmtRole->execute([$userId, $staff['role']]);
    out("    -> Rôle assigné : {$staff['role']}", "info");

    // C. Attribution des permissions granulaires
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

    // D. Liaison avec rh_employees si la table existe
    try {
        $stmtEmp = $pdo->prepare("
            UPDATE rh_employees 
            SET user_id = :user_id, site_id = :site_id, agence_id = :site_id, poste = :poste, updated_at = NOW() 
            WHERE (email = :email OR nom LIKE :name_like OR user_id = :user_id)
        ");
        $stmtEmp->execute([
            'user_id'   => $userId,
            'site_id'   => $staff['agence_id'],
            'poste'     => $staff['poste'],
            'email'     => $staff['default_mail'],
            'name_like' => '%' . explode(' ', $staff['name'])[0] . '%',
        ]);
    } catch (\Throwable $e) {
        // Optionnel si rh_employees a un schéma différent
    }
}

out("\n===========================================================", "success");
out("  ATTRIBUTION TERMINÉE AVEC SUCCÈS POUR LES 15 UTILISATEURS", "success");
out("===========================================================", "success");
