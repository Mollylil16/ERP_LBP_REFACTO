<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Script de vérification et d'audit des rôles et agences du personnel.
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

function line($text = '') {
    global $isCli;
    if ($isCli) {
        echo $text . PHP_EOL;
    } else {
        echo htmlspecialchars($text) . "<br>\n";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Audit Rôles & Agences</title></head><body style='font-family:monospace; padding:20px; background:#f8f9fa; white-space:pre-wrap;'>";
}

line("=========================================================================================");
line("        AUDIT & VÉRIFICATION DES UTILISATEURS DU PERSONNEL (ERP LA BELLE PORTE)          ");
line("=========================================================================================");
line("PHP Version : " . PHP_VERSION);
line();

$configFile = $basePath . '/config/database.php';
if (!file_exists($configFile)) {
    line("[-] Fichier de configuration BDD introuvable : {$configFile}");
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
    line("[✔] Connexion réussie à la base {$config['dbname']}");
    line();
} catch (Exception $e) {
    line("[-] Erreur de connexion BDD : " . $e->getMessage());
    if (!$isCli) echo "</body></html>";
    exit(1);
}

$staffToCheck = [
    [
        'label'        => 'AKOIBLIN ROXANE',
        'emails'       => ['roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci'],
        'name_pattern' => '%AKOIBLIN%',
    ],
    [
        'label'        => 'KOUAKOU SALES',
        'emails'       => ['sales.kouakou@labelleporte.ci'],
        'name_pattern' => '%SALES%',
    ],
    [
        'label'        => 'DIARRA SIAKA',
        'emails'       => ['siaka.diarra@labelleporte.ci'],
        'name_pattern' => '%SIAKA%DIARRA%',
    ],
    [
        'label'        => 'KOUAME GRACE',
        'emails'       => ['grace.kouame@labelleporte.ci'],
        'name_pattern' => '%KOUAME%GRACE%',
    ],
    [
        'label'        => 'KOLI KONAN ANICET',
        'emails'       => ['anicet.koli@labelleporte.ci'],
        'name_pattern' => '%KOLI%ANICET%',
    ],
    [
        'label'        => 'ABOU CARINE (Mme AGBADAN)',
        'emails'       => ['carine.abou@labelleporte.ci'],
        'name_pattern' => '%AGBADAN%',
    ],
    [
        'label'        => 'ABASSI WILFRIED',
        'emails'       => ['wilfried.abassi@labelleporte.ci'],
        'name_pattern' => '%ABASSI%',
    ],
    [
        'label'        => 'LASSICI MARIAM',
        'emails'       => ['mariam.lassici@labelleporte.ci'],
        'name_pattern' => '%LASSICI%',
    ],
    [
        'label'        => 'KOFFI MARQUEZ',
        'emails'       => ['marquez.koffi@labelleporte.ci'],
        'name_pattern' => '%MARQUEZ%',
    ],
    [
        'label'        => 'ASSOMA ASSI JEAN EUDES',
        'emails'       => ['jeaneudes.assoma@labelleporte.ci'],
        'name_pattern' => '%ASSOMA%',
    ],
    [
        'label'        => 'ADEPO MARIE ESTHER',
        'emails'       => ['estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci'],
        'name_pattern' => '%ADEPO%ESTHER%',
    ],
    [
        'label'        => 'DJAMBITCHE SARAH STEPHANIE',
        'emails'       => ['sarah.djambitche@labelleporte.ci'],
        'name_pattern' => '%SARAH%DJAMBITCHE%',
    ],
    [
        'label'        => 'KARABBOUE AMY',
        'emails'       => ['amy.dieng@labelleporte.ci', 'amy.karabboue@labelleporte.ci'],
        'name_pattern' => '%KARABBOUE%',
    ],
    [
        'label'        => 'SERY GRACE',
        'emails'       => ['grace.sery@labelleporte.ci'],
        'name_pattern' => '%SERY%GRACE%',
    ],
    [
        'label'        => 'KADJO PRINCE',
        'emails'       => ['prince.kadjo@labelleporte.ci'],
        'name_pattern' => '%PRINCE%KADJO%',
    ],
];

$stmtRoles = $pdo->prepare("SELECT role FROM lbp_user_roles WHERE user_id = ?");
$stmtPerms = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND (can_view = 1 OR can_create = 1 OR can_update = 1 OR can_delete = 1)");

foreach ($staffToCheck as $i => $staff) {
    $num = $i + 1;
    $u = null;

    foreach ($staff['emails'] as $em) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.status, u.agence_id, s.name AS agence_name
            FROM users u
            LEFT JOIN company_sites s ON s.id = u.agence_id
            WHERE LOWER(u.email) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$em]);
        $u = $stmt->fetch();
        if ($u) break;
    }

    if (!$u && !empty($staff['name_pattern'])) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.status, u.agence_id, s.name AS agence_name
            FROM users u
            LEFT JOIN company_sites s ON s.id = u.agence_id
            WHERE u.full_name LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$staff['name_pattern']]);
        $u = $stmt->fetch();
    }

    if ($u) {
        $stmtRoles->execute([$u['id']]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        if (!$roles) $roles = ['(aucun)'];

        $stmtPerms->execute([$u['id']]);
        $permsCount = (int) $stmtPerms->fetchColumn();

        $agName = !empty($u['agence_name']) ? $u['agence_name'] : ($u['agence_id'] ? 'ID ' . $u['agence_id'] : 'Siège Abidjan');
        $agLabel = $u['agence_id'] ? "{$agName} (ID: {$u['agence_id']})" : "Siège Abidjan";
        
        line(sprintf("[%02d/15] [✔] %s", $num, $staff['label']));
        line(sprintf("        - Nom en BDD  : %s (ID: %d)", $u['full_name'], $u['id']));
        line(sprintf("        - Email       : %s", $u['email']));
        line(sprintf("        - Agence      : %s", $agLabel));
        line(sprintf("        - Rôle(s)     : %s", implode(', ', $roles)));
        line(sprintf("        - Statut      : %s | Permissions actives : %d", strtoupper($u['status']), $permsCount));
        line();
    } else {
        line(sprintf("[%02d/15] [❌] %s", $num, $staff['label']));
        line(sprintf("        - Email(s) attendu(s) : %s", implode(', ', $staff['emails'])));
        line("        - État                 : Utilisateur non trouvé en base.");
        line();
    }
}

line("=========================================================================================");
line("                             FIN DU RAPPORT D'AUDIT                                      ");
line("=========================================================================================");

if (!$isCli) {
    echo "</body></html>";
}
