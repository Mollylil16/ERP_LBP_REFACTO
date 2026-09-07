<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Script de vérification et d'audit des 22 rôles et agences du personnel.
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
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Audit Rôles & Agences (22 Utilisateurs)</title></head><body style='font-family:monospace; padding:20px; background:#f8f9fa; white-space:pre-wrap;'>";
}

line("=========================================================================================");
line("        AUDIT & VÉRIFICATION DES 22 UTILISATEURS DU PERSONNEL (ERP LA BELLE PORTE)        ");
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
        'label'  => 'AKOIBLIN ROXANE',
        'emails' => ['roxane.akoiblin@labelleporte.ci'],
    ],
    [
        'label'  => 'KOUAKOU SALES',
        'emails' => ['kouakou.sales@labelleporte.ci', 'sales.kouakou@labelleporte.ci'],
    ],
    [
        'label'  => 'DIARRA SIAKA',
        'emails' => ['siaka.diarra@labelleporte.ci'],
    ],
    [
        'label'  => 'KOUAME Yvette',
        'emails' => ['kouame.yvette@labelleporte.ci', 'yvette.kouame@labelleporte.ci', 'grace.kouame@labelleporte.ci'],
    ],
    [
        'label'  => 'KOLI KONAN ANICET',
        'emails' => ['anicet.konan@labelleporte.ci', 'anicet.koli@labelleporte.ci'],
    ],
    [
        'label'  => 'Mme AGBADAN (Carine Abou)',
        'emails' => ['carine.abou@labelleporte.ci'],
    ],
    [
        'label'  => 'ABASSI WILFRIED',
        'emails' => ['wilfried.abassi@labelleporte.ci'],
    ],
    [
        'label'  => 'LASSICI MARIAM',
        'emails' => ['mariam.lassici@labelleporte.ci'],
    ],
    [
        'label'  => 'KOFFI MARQUEZ',
        'emails' => ['marquez.koffi@labelleporte.ci'],
    ],
    [
        'label'  => 'ASSOMA ASSI JEAN EUDES',
        'emails' => ['jean.eudes@labelleporte.ci', 'jeaneudes.assoma@labelleporte.ci'],
    ],
    [
        'label'  => 'ADEPO MARIE ESTHER',
        'emails' => ['estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci'],
    ],
    [
        'label'  => 'DJAMBITCHE SARAH STEPHANIE',
        'emails' => ['sarah.djambitche@labelleporte.ci'],
    ],
    [
        'label'  => 'KARABBOUE AMY',
        'emails' => ['karabboue.amy@labelleporte.ci'],
    ],
    [
        'label'  => 'SERY GRACE',
        'emails' => ['sery.grace@labelleporte.ci', 'grace.sery@labelleporte.ci'],
    ],
    [
        'label'  => 'KADJO PRINCE',
        'emails' => ['prince.kadjo@labelleporte.ci'],
    ],
    [
        'label'  => 'Claude Yedess (Assistante DG)',
        'emails' => ['claude.yedess@labelleporte.ci'],
    ],
    [
        'label'  => 'Dieng Amy (Saisie Sénégal)',
        'emails' => ['amy.dieng@labelleporte.ci'],
    ],
    [
        'label'  => 'BRUNELL OMEPIEUR (Admin)',
        'emails' => ['brunellomepieu@labelleporte.ci', 'brunellomepieu01@gmail.com'],
    ],
    [
        'label'  => 'SIBRI PAUL (Comptable)',
        'emails' => ['sibri.paulaime@labelleporte.ci'],
    ],
    [
        'label'  => 'SORO IBRAHIM (Stagiaire RH)',
        'emails' => ['soro.ibrahim@labelleporte.ci'],
    ],
    [
        'label'  => 'Serge Kadjo (Directeur Général)',
        'emails' => ['serge.kadjo@labelleporte.ci', 'serges.kadjo@labelleporte.ci'],
    ],
    [
        'label'  => 'Adje Roxane (Responsable RH)',
        'emails' => ['roxane.a@labelleporte.ci'],
    ],
];

$stmtRoles = $pdo->prepare("SELECT role FROM lbp_user_roles WHERE user_id = ?");
$stmtPerms = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND (can_view = 1 OR can_create = 1 OR can_update = 1 OR can_delete = 1)");

foreach ($staffToCheck as $i => $staff) {
    $num = $i + 1;
    $u = null;

    foreach ($staff['emails'] as $em) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.status, u.is_admin, u.agence_id, s.name AS agence_name
            FROM users u
            LEFT JOIN company_sites s ON s.id = u.agence_id
            WHERE LOWER(u.email) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$em]);
        $u = $stmt->fetch();
        if ($u) break;
    }

    if ($u) {
        $stmtRoles->execute([$u['id']]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        if (!$roles) $roles = ['(aucun)'];

        $stmtPerms->execute([$u['id']]);
        $permsCount = (int) $stmtPerms->fetchColumn();

        $agLabel = $u['agence_id'] ? ($u['agence_name'] ? "{$u['agence_name']} (ID: {$u['agence_id']})" : "ID {$u['agence_id']}") : "Toutes les agences (Siège/Direction)";
        $adminTag = $u['is_admin'] ? " [ADMIN]" : "";
        
        line(sprintf("[%02d/22] [✔] %s%s", $num, $staff['label'], $adminTag));
        line(sprintf("        - Nom en BDD  : %s (ID: %d)", $u['full_name'], $u['id']));
        line(sprintf("        - Email       : %s", $u['email']));
        line(sprintf("        - Agence      : %s", $agLabel));
        line(sprintf("        - Rôle(s)     : %s", implode(', ', $roles)));
        line(sprintf("        - Statut      : %s | Permissions actives : %d", strtoupper($u['status']), $permsCount));
        line();
    } else {
        line(sprintf("[%02d/22] [❌] %s", $num, $staff['label']));
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
