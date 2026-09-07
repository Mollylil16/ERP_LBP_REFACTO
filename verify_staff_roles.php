<?php
/**
 * Script de vérification et d'audit des rôles et agences du personnel.
 *
 * Exécution CLI : php verify_staff_roles.php
 * Exécution Web : http://votre-domaine/verify_staff_roles.php
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/vendor/autoload.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

$isCli = (php_sapi_name() === 'cli');

function line(string $text = ''): void {
    global $isCli;
    echo $text . ($isCli ? PHP_EOL : "<br>\n");
}

line("=========================================================================================");
line("        AUDIT & VÉRIFICATION DES UTILISATEURS DU PERSONNEL (ERP LA BELLE PORTE)          ");
line("=========================================================================================");

$pdo = null;
try {
    $pdo = \App\Models\Database::getConnection();
} catch (\Throwable $e) {
    $config = require BASE_PATH . '/config/database.php';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
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
    $mailPrefix = explode('@', $mail)[0];
    $nameFirst = explode(' ', $nomPdf)[0];
    
    $stmtUser->execute([
        'email'      => $mail,
        'email_like' => "%{$mailPrefix}%",
        'name_like'  => "%{$nameFirst}%",
    ]);
    $u = $stmtUser->fetch();

    if ($u) {
        $stmtRoles->execute([$u['id']]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN) ?: ['(aucun)'];

        $stmtPerms->execute([$u['id']]);
        $permsCount = (int) $stmtPerms->fetchColumn();

        $agLabel = $u['agence_name'] ? "{$u['agence_name']} (ID: {$u['agence_id']})" : "[ID: {$u['agence_id']}]";
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
