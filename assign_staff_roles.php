<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Script officiel d'attribution automatique des rôles, agences et permissions
 * pour l'ensemble des 22 utilisateurs de l'ERP LA BELLE PORTE.
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
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Attribution Rôles & Agences ERP</title></head><body style='font-family:sans-serif; padding:20px; background:#f8f9fa;'>";
    echo "<h2>Attribution des Rôles, Agences & Permissions (22 Utilisateurs)</h2>";
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
    out("Connexion réussie à la base {$config['dbname']}.", "success");
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

// Validation site ID
function resolveValidSiteId($pdo, $targetSiteId) {
    if ($targetSiteId === null || $targetSiteId === 1) {
        return null; // Toutes les agences / Siège
    }
    $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE id = ?");
    $stmt->execute([$targetSiteId]);
    if ($stmt->fetchColumn()) {
        return (int)$targetSiteId;
    }
    return null;
}

// 2. Définition complète des 22 utilisateurs
$usersList = [
    // 1. AKOIBLIN ROXANE
    [
        'name'         => 'AKOIBLIN ROXANE',
        'poste'        => "CHEF D'AGENCE ADJAME",
        'email'        => 'roxane.akoiblin@labelleporte.ci',
        'alias_emails' => ['roxane.akoiblin@labelleporte.ci'],
        'name_pattern' => '%AKOIBLIN%',
        'roles'        => ['chef_agence'],
        'is_admin'     => 0,
        'agence_id'    => 3404, // Adjamé
        'agence_label' => 'Agence Adjamé (ID 3404)',
        'permissions'  => [
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
    // 2. KOUAKOU SALES
    [
        'name'         => 'KOUAKOU SALES',
        'poste'        => 'RESPONSABLE GROUPAGE GENERAL',
        'email'        => 'kouakou.sales@labelleporte.ci',
        'alias_emails' => ['kouakou.sales@labelleporte.ci', 'sales.kouakou@labelleporte.ci'],
        'name_pattern' => '%SALES%',
        'roles'        => ['responsable_groupage', 'agent_groupage'],
        'is_admin'     => 0,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Siège)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 1, 0],
            'colisage_expeditions'  => [1, 1, 1, 0],
            'entrepot_inventaires'  => [1, 1, 1, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
        ]
    ],
    // 3. DIARRA SIAKA
    [
        'name'         => 'DIARRA SIAKA',
        'poste'        => "CHEF D'AGENCE DOKUI",
        'email'        => 'siaka.diarra@labelleporte.ci',
        'alias_emails' => ['siaka.diarra@labelleporte.ci'],
        'name_pattern' => '%SIAKA%DIARRA%',
        'roles'        => ['chef_agence'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
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
    // 4. KOUAME YVETTE
    [
        'name'         => 'KOUAME Yvette',
        'poste'        => 'AGENT DE SAISIE DOKUI',
        'email'        => 'kouame.yvette@labelleporte.ci',
        'alias_emails' => ['kouame.yvette@labelleporte.ci', 'yvette.kouame@labelleporte.ci', 'grace.kouame@labelleporte.ci'],
        'name_pattern' => '%KOUAME%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 5. KOLI KONAN ANICET
    [
        'name'         => 'KOLI KONAN ANICET',
        'poste'        => 'AGENT DE SAISIE DOKUI',
        'email'        => 'anicet.konan@labelleporte.ci',
        'alias_emails' => ['anicet.konan@labelleporte.ci', 'anicet.koli@labelleporte.ci'],
        'name_pattern' => '%KOLI%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 6. Mme AGBADAN (Carine Abou)
    [
        'name'         => 'Mme AGBADAN (Carine Abou)',
        'poste'        => 'CAISSIERE PRINCIPALE & CAISSIERE DOKUI',
        'email'        => 'carine.abou@labelleporte.ci',
        'alias_emails' => ['carine.abou@labelleporte.ci'],
        'name_pattern' => '%AGBADAN%',
        'roles'        => ['caissiere_principale', 'caissiere'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
            'saisir_facture'        => [1, 1, 0, 0],
            'finance_retraits'      => [1, 1, 0, 0],
            'colisage_colis'        => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'rapports_agence'       => [1, 0, 0, 0],
        ]
    ],
    // 7. ABASSI WILFRIED
    [
        'name'         => 'ABASSI WILFRIED',
        'poste'        => 'RESPONSABLE MARKETING & COMMUNICATION ET RESPONSABLE CALL CENTER',
        'email'        => 'wilfried.abassi@labelleporte.ci',
        'alias_emails' => ['wilfried.abassi@labelleporte.ci'],
        'name_pattern' => '%ABASSI%',
        'roles'        => ['responsable_marketing', 'responsable_call_center', 'agent_call_center'],
        'is_admin'     => 0,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Siège)',
        'permissions'  => [
            'crm_clients'           => [1, 1, 1, 0],
            'crm_opportunities'     => [1, 1, 1, 0],
            'call_center_view'      => [1, 1, 0, 0],
            'call_center_manage'    => [1, 1, 1, 0],
            'colisage_colis'        => [1, 0, 0, 0],
        ]
    ],
    // 8. LASSICI MARIAM
    [
        'name'         => 'LASSICI MARIAM',
        'poste'        => 'AGENT CALL CENTER',
        'email'        => 'mariam.lassici@labelleporte.ci',
        'alias_emails' => ['mariam.lassici@labelleporte.ci'],
        'name_pattern' => '%LASSICI%',
        'roles'        => ['agent_call_center'],
        'is_admin'     => 0,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Siège)',
        'permissions'  => [
            'call_center_view'      => [1, 1, 0, 0],
            'call_center_manage'    => [1, 1, 1, 0],
            'colisage_colis'        => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
        ]
    ],
    // 9. KOFFI MARQUEZ
    [
        'name'         => 'KOFFI MARQUEZ',
        'poste'        => "CHEF D'AGENCE AEROPORT",
        'email'        => 'marquez.koffi@labelleporte.ci',
        'alias_emails' => ['marquez.koffi@labelleporte.ci'],
        'name_pattern' => '%MARQUEZ%',
        'roles'        => ['chef_agence'],
        'is_admin'     => 0,
        'agence_id'    => 3402, // Aéroport Port-Bouët Fret
        'agence_label' => 'Aéroport Port-Bouët Fret (ID 3402)',
        'permissions'  => [
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
    // 10. ASSOMA ASSI JEAN EUDES
    [
        'name'         => 'ASSOMA ASSI JEAN EUDES',
        'poste'        => 'AGENT DE SAISIE AEROPORT',
        'email'        => 'jean.eudes@labelleporte.ci',
        'alias_emails' => ['jean.eudes@labelleporte.ci', 'jeaneudes.assoma@labelleporte.ci'],
        'name_pattern' => '%ASSOMA%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3402, // Aéroport Port-Bouët Fret
        'agence_label' => 'Aéroport Port-Bouët Fret (ID 3402)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 11. ADEPO MARIE ESTHER
    [
        'name'         => 'ADEPO MARIE ESTHER',
        'poste'        => "CHEF D'AGENCE SENEGAL",
        'email'        => 'estelle.adepo@labelleporte.ci',
        'alias_emails' => ['estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci'],
        'name_pattern' => '%ADEPO%',
        'roles'        => ['chef_agence'],
        'is_admin'     => 0,
        'agence_id'    => 3401, // Agence Sénégal
        'agence_label' => 'Agence Sénégal (ID 3401)',
        'permissions'  => [
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
    // 12. DJAMBITCHE SARAH STEPHANIE
    [
        'name'         => 'DJAMBITCHE SARAH STEPHANIE',
        'poste'        => 'AGENT DE SAISIE ADJAME',
        'email'        => 'sarah.djambitche@labelleporte.ci',
        'alias_emails' => ['sarah.djambitche@labelleporte.ci'],
        'name_pattern' => '%SARAH%DJAMBITCHE%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3404, // Adjamé
        'agence_label' => 'Agence Adjamé (ID 3404)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 13. KARABBOUE AMY
    [
        'name'         => 'KARABBOUE AMY',
        'poste'        => 'AGENT DE SAISIE ADJAME',
        'email'        => 'karabboue.amy@labelleporte.ci',
        'alias_emails' => ['karabboue.amy@labelleporte.ci'],
        'name_pattern' => '%KARABBOUE%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3404, // Adjamé
        'agence_label' => 'Agence Adjamé (ID 3404)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 14. SERY GRACE
    [
        'name'         => 'SERY GRACE',
        'poste'        => 'AGENT DE SAISIE ADJAME',
        'email'        => 'sery.grace@labelleporte.ci',
        'alias_emails' => ['sery.grace@labelleporte.ci', 'grace.sery@labelleporte.ci'],
        'name_pattern' => '%SERY%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3404, // Adjamé
        'agence_label' => 'Agence Adjamé (ID 3404)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 15. KADJO PRINCE
    [
        'name'         => 'KADJO PRINCE',
        'poste'        => 'CHEF D\'AGENCE FRANCE',
        'email'        => 'prince.kadjo@labelleporte.ci',
        'alias_emails' => ['prince.kadjo@labelleporte.ci'],
        'name_pattern' => '%PRINCE%KADJO%',
        'roles'        => ['chef_agence'],
        'is_admin'     => 0,
        'agence_id'    => 3400, // France Paris
        'agence_label' => 'Agence France - Paris (ID 3400)',
        'permissions'  => [
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
    // 16. Claude Yedess
    [
        'name'         => 'Claude Yedess',
        'poste'        => 'ASSISTANTE DG',
        'email'        => 'claude.yedess@labelleporte.ci',
        'alias_emails' => ['claude.yedess@labelleporte.ci'],
        'name_pattern' => '%YEDESS%',
        'roles'        => ['assistant_dg', 'assistante_dg'],
        'is_admin'     => 0,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Direction)',
        'permissions'  => [
            'colisage_colis'        => [1, 0, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 0, 0, 0],
            'saisir_facture'        => [1, 0, 0, 0],
            'rapports_agence'       => [1, 0, 0, 0],
            'call_center_view'      => [1, 0, 0, 0],
        ]
    ],
    // 17. Dieng Amy
    [
        'name'         => 'Dieng Amy',
        'poste'        => 'AGENT DE SAISIE SENEGAL',
        'email'        => 'amy.dieng@labelleporte.ci',
        'alias_emails' => ['amy.dieng@labelleporte.ci'],
        'name_pattern' => '%DIENG%',
        'roles'        => ['agent_saisie'],
        'is_admin'     => 0,
        'agence_id'    => 3401, // Agence Sénégal
        'agence_label' => 'Agence Sénégal (ID 3401)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 0, 0],
            'colisage_expeditions'  => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
            'saisir_facture'        => [1, 1, 0, 0],
            'exporter_colisage_sans_montant' => [1, 0, 0, 0],
        ]
    ],
    // 18. BRUNELL OMEPIEUR
    [
        'name'         => 'BRUNELL OMEPIEUR',
        'poste'        => 'ADMINISTRATEUR',
        'email'        => 'brunellomepieu@labelleporte.ci',
        'alias_emails' => ['brunellomepieu@labelleporte.ci', 'brunellomepieu01@gmail.com'],
        'name_pattern' => '%BRUNELL%',
        'roles'        => ['admin'],
        'is_admin'     => 1,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Super Admin)',
        'permissions'  => [
            'users'                 => [1, 1, 1, 1],
            'user_permissions'      => [1, 1, 1, 1],
            'colisage_colis'        => [1, 1, 1, 1],
            'colisage_expeditions'  => [1, 1, 1, 1],
            'crm_clients'           => [1, 1, 1, 1],
            'saisir_facture'        => [1, 1, 1, 1],
        ]
    ],
    // 19. SIBRI PAUL
    [
        'name'         => 'SIBRI PAUL',
        'poste'        => 'COMPTABLE',
        'email'        => 'sibri.paulaime@labelleporte.ci',
        'alias_emails' => ['sibri.paulaime@labelleporte.ci'],
        'name_pattern' => '%SIBRI%',
        'roles'        => ['comptable'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
            'saisir_facture'        => [1, 1, 1, 0],
            'finance_retraits'      => [1, 1, 1, 0],
            'finance_compensations' => [1, 1, 1, 0],
            'facturation_factures'  => [1, 1, 1, 0],
            'rapports_agence'       => [1, 1, 0, 0],
            'exporter_rapports_excel'=> [1, 1, 0, 0],
            'exporter_facturation_avec_montant' => [1, 1, 0, 0],
            'colisage_colis'        => [1, 0, 0, 0],
            'crm_clients'           => [1, 1, 1, 0],
        ]
    ],
    // 20. SORO IBRAHIM
    [
        'name'         => 'SORO IBRAHIM',
        'poste'        => 'STAGIAIRE RH',
        'email'        => 'soro.ibrahim@labelleporte.ci',
        'alias_emails' => ['soro.ibrahim@labelleporte.ci'],
        'name_pattern' => '%SORO%',
        'roles'        => ['rh', 'rh_agent', 'stagiaire_rh'],
        'is_admin'     => 0,
        'agence_id'    => 3403, // Abobo Dokui
        'agence_label' => 'Agence Abobo Dokui (ID 3403)',
        'permissions'  => [
            'rh_employees'          => [1, 1, 1, 0],
            'rh_employee_history'   => [1, 1, 1, 0],
            'rh_employee_mutations' => [1, 1, 1, 0],
            'rh_attendance'         => [1, 1, 1, 0],
            'rh_leaves'             => [1, 1, 1, 0],
        ]
    ],
    // 21. Serge Kadjo
    [
        'name'         => 'Serge Kadjo',
        'poste'        => 'DIRECTEUR GENERAL',
        'email'        => 'serge.kadjo@labelleporte.ci',
        'alias_emails' => ['serge.kadjo@labelleporte.ci', 'serges.kadjo@labelleporte.ci'],
        'name_pattern' => '%SERGE%KADJO%',
        'roles'        => ['dg', 'dg_surveillance'],
        'is_admin'     => 1,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Direction Générale)',
        'permissions'  => [
            'colisage_colis'        => [1, 1, 1, 1],
            'colisage_expeditions'  => [1, 1, 1, 1],
            'crm_clients'           => [1, 1, 1, 1],
            'saisir_facture'        => [1, 1, 1, 1],
            'surveillance_module'   => [1, 1, 1, 1],
        ]
    ],
    // 22. Adje Roxane
    [
        'name'         => 'Adje Roxane',
        'poste'        => 'RESPONSABLE RH',
        'email'        => 'roxane.a@labelleporte.ci',
        'alias_emails' => ['roxane.a@labelleporte.ci', 'adjemoriaroxanne@labelleporte.cloud'],
        'name_pattern' => '%ADJE%',
        'roles'        => ['responsable_rh', 'rh', 'rh_manager'],
        'is_admin'     => 0,
        'agence_id'    => null, // Toutes les agences
        'agence_label' => 'Toutes les agences (Direction RH)',
        'permissions'  => [
            'rh_employees'          => [1, 1, 1, 1],
            'rh_employee_history'   => [1, 1, 1, 1],
            'rh_employee_mutations' => [1, 1, 1, 1],
            'rh_exit_reasons'       => [1, 1, 1, 1],
            'rh_functions'          => [1, 1, 1, 1],
            'rh_services'           => [1, 1, 1, 1],
            'rh_statuses'           => [1, 1, 1, 1],
            'rh_contracts'          => [1, 1, 1, 1],
            'rh_payroll_params'     => [1, 1, 1, 1],
            'rh_attendance'         => [1, 1, 1, 1],
            'rh_payroll'            => [1, 1, 1, 1],
            'rh_leaves'             => [1, 1, 1, 1],
        ]
    ],
];

out("Attribution en cours pour les 22 utilisateurs du personnel...", "info");

$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (!$userCols) $userCols = [];
$hasPassword = in_array('password', $userCols, true);
$hasPasswordHash = in_array('password_hash', $userCols, true);
$defaultPassword = password_hash('lbp2026', PASSWORD_BCRYPT);

foreach ($usersList as $index => $uData) {
    $num = $index + 1;
    $validSiteId = resolveValidSiteId($pdo, $uData['agence_id']);

    // A. Recherche de l'utilisateur par e-mail exact ou alias
    $user = null;
    foreach ($uData['alias_emails'] as $em) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$em]);
        $user = $stmt->fetch();
        if ($user) break;
    }

    if (!$user && !empty($uData['name_pattern'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE full_name LIKE ? LIMIT 1");
        $stmt->execute([$uData['name_pattern']]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        // Création de l'utilisateur
        $insertFields = ['full_name', 'email', 'status', 'is_admin'];
        $insertValues = [':full_name', ':email', "'active'", ':is_admin'];
        $params = [
            'full_name' => $uData['name'],
            'email'     => $uData['email'],
            'is_admin'  => $uData['is_admin'],
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
        out(sprintf("[%02d/22] [Créé] %s <%s> (ID: %d) -> %s", $num, $uData['name'], $uData['email'], $userId, $uData['agence_label']), "success");
    } else {
        $userId = (int) $user['id'];
        $stmtUp = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, agence_id = :agence_id, is_admin = :is_admin, status = 'active' WHERE id = :id");
        $stmtUp->execute([
            'full_name' => $uData['name'],
            'email'     => $uData['email'],
            'agence_id' => $validSiteId,
            'is_admin'  => $uData['is_admin'],
            'id'        => $userId
        ]);
        out(sprintf("[%02d/22] [Mis à jour] %s <%s> (ID: %d) -> %s", $num, $uData['name'], $uData['email'], $userId, $uData['agence_label']), "success");
    }

    // B. Rôles dans lbp_user_roles
    $pdo->prepare("DELETE FROM lbp_user_roles WHERE user_id = ?")->execute([$userId]);
    $stmtRole = $pdo->prepare("INSERT INTO lbp_user_roles (user_id, role) VALUES (?, ?)");
    foreach ($uData['roles'] as $r) {
        $stmtRole->execute([$userId, $r]);
    }
    out("    -> Rôle(s) : " . implode(', ', $uData['roles']), "info");

    // C. Permissions
    $stmtEnt = $pdo->prepare("SELECT id FROM permission_entities WHERE code = ? LIMIT 1");
    $stmtPerm = $pdo->prepare("
        INSERT INTO user_permissions (user_id, entity_id, can_view, can_create, can_update, can_delete)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_update = VALUES(can_update), can_delete = VALUES(can_delete)
    ");

    foreach ($uData['permissions'] as $entCode => $rights) {
        $stmtEnt->execute([$entCode]);
        $entId = $stmtEnt->fetchColumn();
        if ($entId) {
            $stmtPerm->execute([$userId, (int)$entId, $rights[0], $rights[1], $rights[2], $rights[3]]);
        }
    }
    out("    -> " . count($uData['permissions']) . " entités de permissions configurées.", "info");

    // D. Liaison rh_employees
    try {
        $stmtEmp = $pdo->prepare("
            UPDATE rh_employees 
            SET user_id = :user_id, site_id = :site_id, agence_id = :site_id, poste = :poste, updated_at = NOW() 
            WHERE (email = :email OR user_id = :user_id)
        ");
        $stmtEmp->execute([
            'user_id'   => $userId,
            'site_id'   => $validSiteId,
            'poste'     => $uData['poste'],
            'email'     => $uData['email'],
        ]);
    } catch (Exception $e) {}
}

out("\n===========================================================", "success");
out("  ATTRIBUTION TERMINÉE AVEC SUCCÈS POUR LES 22 COLLABORATEURS", "success");
out("===========================================================", "success");

if (!$isCli) {
    echo "<p style='margin-top:20px;'><a href='/verify_staff_roles.php' style='display:inline-block; padding:10px 20px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px;'>Lancer l'Audit de Vérification</a> ";
    echo "<a href='/admin/users' style='display:inline-block; padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:4px;'>Gérer dans le Module Admin</a></p>";
    echo "</body></html>";
}
