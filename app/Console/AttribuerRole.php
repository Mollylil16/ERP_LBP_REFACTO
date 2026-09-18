<?php

/**
 * Attribuer ou retirer un rôle à un compte, depuis le serveur.
 *
 * Administration ne présente que les rôles de son catalogue. Tant qu'un rôle
 * réel n'y figurait pas — « agent_saisie », le plus répandu de l'entreprise —
 * il était impossible de le donner depuis l'écran, et un simple enregistrement
 * du formulaire l'effaçait. Ce script répare ces comptes-là.
 *
 * Il ne fait rien par défaut : il montre ce qu'il changerait. Il faut
 * --appliquer pour qu'il écrive.
 *
 * Usage :
 *   php app/Console/AttribuerRole.php --email=nom@labelleporte.ci --ajouter=agent_saisie
 *   php app/Console/AttribuerRole.php --email=nom@labelleporte.ci --ajouter=agent_saisie --appliquer
 *   php app/Console/AttribuerRole.php --email=nom@labelleporte.ci --retirer=caissiere --appliquer
 *   php app/Console/AttribuerRole.php --sans-role                     (qui n'a aucun rôle)
 *   php app/Console/AttribuerRole.php --nettoyer-vides --appliquer    (rôles vides hérités)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

$arguments = $argv ?? [];
$email = '';
$ajouter = '';
$retirer = '';
$appliquer = in_array('--appliquer', $arguments, true);
$sansRole = in_array('--sans-role', $arguments, true);
$nettoyerVides = in_array('--nettoyer-vides', $arguments, true);

foreach ($arguments as $argument) {
    if (preg_match('/^--email=(.+)$/', $argument, $t)) {
        $email = strtolower(trim($t[1]));
    }
    if (preg_match('/^--ajouter=([a-z_]+)$/', $argument, $t)) {
        $ajouter = $t[1];
    }
    if (preg_match('/^--retirer=([a-z_]+)$/', $argument, $t)) {
        $retirer = $t[1];
    }
}

$config = require BASE_PATH . '/config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion impossible : " . $e->getMessage() . "\n");
    exit(2);
}

/**
 * Rôles que le code déployé connaît, lus dans ses fichiers : le catalogue
 * d'Administration et les rôles hors catalogue.
 *
 * @return array<int, string>
 */
function rolesConnus(): array
{
    $roles = [];

    $admin = @file_get_contents(BASE_PATH . '/app/Services/Admin/AdminService.php');
    if ($admin !== false && preg_match('/const\s+AVAILABLE_ROLES\s*=\s*\[(.*?)\]\s*;/s', $admin, $t)) {
        preg_match_all("/'([a-z_]+)'\s*=>/", $t[1], $trouves);
        $roles = array_merge($roles, $trouves[1]);
    }

    $module = @file_get_contents(BASE_PATH . '/app/Security/ModuleAccess.php');
    if ($module !== false && preg_match('/const\s+ROLES_HORS_CATALOGUE\s*=\s*\[(.*?)\]\s*;/s', $module, $t)) {
        preg_match_all("/'([a-z_]+)'/", $t[1], $trouves);
        $roles = array_merge($roles, $trouves[1]);
    }

    return array_values(array_unique($roles));
}

/** @return array<int, string> */
function rolesDe(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT role FROM lbp_user_roles WHERE user_id = :id ORDER BY role');
    $stmt->execute(['id' => $userId]);

    return array_column($stmt->fetchAll(), 'role');
}

// ---------------------------------------------------------------------------
// Comptes sans aucun rôle : ils peuvent se connecter, mais rien n'est ouvert.
// ---------------------------------------------------------------------------

if ($sansRole) {
    $stmt = $pdo->query("
        SELECT u.full_name, u.email, s.name AS agence
        FROM users u
        LEFT JOIN company_sites s ON s.id = u.agence_id
        LEFT JOIN lbp_user_roles r ON r.user_id = u.id
        WHERE u.status = 'active' AND u.is_admin = 0
        GROUP BY u.id
        HAVING COUNT(NULLIF(r.role, '')) = 0
        ORDER BY s.name, u.full_name
    ");
    $comptes = $stmt->fetchAll();

    echo "=== COMPTES ACTIFS SANS AUCUN RÔLE (" . count($comptes) . ") ===\n\n";
    foreach ($comptes as $c) {
        printf("  %-38s %-38s %s\n", $c['full_name'], $c['email'], $c['agence'] ?? 'aucune agence');
    }
    echo "\n  Ces comptes se connectent mais n'ouvrent ni facture ni encaissement.\n\n";

    exit(0);
}

// ---------------------------------------------------------------------------
// Rôles vides hérités d'anciennes saisies
// ---------------------------------------------------------------------------

if ($nettoyerVides) {
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, u.email
        FROM lbp_user_roles r
        JOIN users u ON u.id = r.user_id
        WHERE TRIM(r.role) = ''
        ORDER BY u.full_name
    ");
    $vides = $stmt->fetchAll();

    echo "=== RÔLES VIDES (" . count($vides) . ") ===\n\n";
    foreach ($vides as $v) {
        printf("  %-38s %s\n", $v['full_name'], $v['email']);
    }

    if ($vides === []) {
        echo "  Aucun.\n";
    } elseif ($appliquer) {
        $pdo->exec("DELETE FROM lbp_user_roles WHERE TRIM(role) = ''");
        echo "\n  Supprimés.\n";
    } else {
        echo "\n  Relancez avec --appliquer pour les supprimer.\n";
    }

    echo "\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Attribuer ou retirer un rôle
// ---------------------------------------------------------------------------

if ($email === '' || ($ajouter === '' && $retirer === '')) {
    fwrite(STDERR, "Usage : --email=… --ajouter=<role> [--appliquer]\n");
    fwrite(STDERR, "        --email=… --retirer=<role> [--appliquer]\n");
    fwrite(STDERR, "        --sans-role | --nettoyer-vides\n");
    exit(1);
}

$connus = rolesConnus();

foreach ([$ajouter, $retirer] as $role) {
    if ($role !== '' && !in_array($role, $connus, true)) {
        fwrite(STDERR, "Le rôle « {$role} » n'existe nulle part dans le code : il n'ouvrirait rien.\n");
        fwrite(STDERR, "Rôles connus : " . implode(', ', $connus) . "\n");
        exit(1);
    }
}

$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.status, u.is_admin, s.name AS agence
    FROM users u
    LEFT JOIN company_sites s ON s.id = u.agence_id
    WHERE LOWER(u.email) = :email
    LIMIT 1
");
$stmt->execute(['email' => $email]);
$compte = $stmt->fetch();

if ($compte === false) {
    fwrite(STDERR, "Aucun compte avec l'adresse {$email}.\n");
    exit(1);
}

$avant = rolesDe($pdo, (int) $compte['id']);

printf(
    "%s  (%s)\n  agence : %s\n  statut : %s\n  rôles  : %s\n\n",
    $compte['full_name'],
    $compte['email'],
    $compte['agence'] ?? 'aucune',
    $compte['status'],
    $avant === [] ? 'AUCUN' : implode(', ', $avant)
);

if ($ajouter !== '' && in_array($ajouter, $avant, true)) {
    echo "  Rien à faire : le compte porte déjà « {$ajouter} ».\n\n";
    exit(0);
}

if ($retirer !== '' && !in_array($retirer, $avant, true)) {
    echo "  Rien à faire : le compte ne porte pas « {$retirer} ».\n\n";
    exit(0);
}

$action = $ajouter !== ''
    ? "ajouter le rôle « {$ajouter} »"
    : "retirer le rôle « {$retirer} »";

if (!$appliquer) {
    echo "  À faire : {$action}.\n";
    echo "  Relancez la même commande avec --appliquer pour l'écrire.\n\n";
    exit(0);
}

if ($ajouter !== '') {
    $stmt = $pdo->prepare('INSERT INTO lbp_user_roles (user_id, role) VALUES (:id, :role)');
    $stmt->execute(['id' => (int) $compte['id'], 'role' => $ajouter]);
} else {
    $stmt = $pdo->prepare('DELETE FROM lbp_user_roles WHERE user_id = :id AND role = :role');
    $stmt->execute(['id' => (int) $compte['id'], 'role' => $retirer]);
}

printf("  Fait : %s.\n  rôles : %s\n\n", $action, implode(', ', rolesDe($pdo, (int) $compte['id'])));
