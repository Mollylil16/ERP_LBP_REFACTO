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

$emailsToCheck = [
    'roxane.akoiblin@labelleporte.ci' => 'AKOIBLIN ROXANE',
    'sales.kouakou@labelleporte.ci'   => 'KOUAKOU SALES',
    'siaka.diarra@labelleporte.ci'    => 'DIARRA SIAKA',
    'grace.kouame@labelleporte.ci'    => 'KOUAME GRACE',
    'anicet.koli@labelleporte.ci'     => 'KOLI KONAN ANICET',
    'carine.abou@labelleporte.ci'     => 'Mme AGBADAN (Carine Abou)',
    'wilfried.abassi@labelleporte.ci' => 'ABASSI WILFRIED',
    'mariam.lassici@labelleporte.ci'  => 'LASSICI MARIAM',
    'marquez.koffi@labelleporte.ci'   => 'KOFFI MARQUEZ',
    'jeaneudes.assoma@labelleporte.ci'=> 'ASSOMA ASSI JEAN EUDES',
    'estelle.adepo@labelleporte.ci'   => 'ADEPO MARIE ESTHER',
    'sarah.djambitche@labelleporte.ci'=> 'DJAMBITCHE SARAH STEPHANIE',
    'amy.dieng@labelleporte.ci'       => 'KARABBOUE AMY',
    'grace.sery@labelleporte.ci'      => 'SERY GRACE',
    'prince.kadjo@labelleporte.ci'    => 'KADJO PRINCE',
];

$stmtUser = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.status, u.agence_id, s.name AS agence_name
    FROM users u
    LEFT JOIN company_sites s ON s.id = u.agence_id
    WHERE u.email = :email 
       OR u.email LIKE :email_like 
       OR u.full_name LIKE :name_like
    LIMIT 1
");

$stmtRoles = $pdo->prepare("SELECT role FROM lbp_user_roles WHERE user_id = ?");
$stmtPerms = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND (can_view = 1 OR can_create = 1 OR can_update = 1 OR can_delete = 1)");

$i = 1;
foreach ($emailsToCheck as $mail => $nomPdf) {
    $mailParts = explode('@', $mail);
    $mailPrefix = $mailParts[0];
    $nomParts = explode(' ', $nomPdf);
    $nameFirst = $nomParts[0];
    
    $stmtUser->execute([
        'email'      => $mail,
        'email_like' => "%{$mailPrefix}%",
        'name_like'  => "%{$nameFirst}%",
    ]);
    $u = $stmtUser->fetch();

    if ($u) {
        $stmtRoles->execute([$u['id']]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        if (!$roles) $roles = ['(aucun)'];

        $stmtPerms->execute([$u['id']]);
        $permsCount = (int) $stmtPerms->fetchColumn();

        $agName = !empty($u['agence_name']) ? $u['agence_name'] : 'ID ' . $u['agence_id'];
        $agLabel = "{$agName} (ID: {$u['agence_id']})";
        
        line(sprintf("[%02d/15] [✔] %s", $i, $nomPdf));
        line(sprintf("        - Nom en BDD  : %s (ID: %d)", $u['full_name'], $u['id']));
        line(sprintf("        - Email       : %s", $u['email']));
        line(sprintf("        - Agence      : %s", $agLabel));
        line(sprintf("        - Rôle(s)     : %s", implode(', ', $roles)));
        line(sprintf("        - Statut      : %s | Permissions actives : %d", strtoupper($u['status']), $permsCount));
        line();
    } else {
        line(sprintf("[%02d/15] [❌] %s", $i, $nomPdf));
        line(sprintf("        - Email attendu : %s", $mail));
        line("        - État          : Utilisateur non trouvé en base.");
        line();
    }
    $i++;
}

line("=========================================================================================");
line("                             FIN DU RAPPORT D'AUDIT                                      ");
line("=========================================================================================");

if (!$isCli) {
    echo "</body></html>";
}
