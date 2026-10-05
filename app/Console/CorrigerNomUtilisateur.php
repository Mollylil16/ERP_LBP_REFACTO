<?php

/**
 * Corrige le nom affiché d'un compte.
 *
 * Un compte lié à un dossier RH tient son nom de ce dossier : le corriger dans
 * la table des comptes seulement ne tiendrait pas, la prochaine modification de
 * la fiche le réécrirait depuis le RH. Ce script corrige donc les deux, et dit
 * lequel il a touché.
 *
 * IL N'ÉCRIT RIEN SANS --appliquer.
 *
 * Usage :
 *   php app/Console/CorrigerNomUtilisateur.php --email=x@y.ci --nom="Brunell Omepieu"
 *   php app/Console/CorrigerNomUtilisateur.php --email=x@y.ci --nom="Brunell Omepieu" --appliquer
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\nERREUR : " . $e->getMessage() . "\n");
    exit(3);
});

$arguments = $argv ?? [];
$appliquer = in_array('--appliquer', $arguments, true);

$lire = static function (string $cle) use ($arguments): ?string {
    foreach ($arguments as $argument) {
        if (str_starts_with((string) $argument, '--' . $cle . '=')) {
            return trim(substr((string) $argument, strlen($cle) + 3));
        }
    }

    return null;
};

$email = strtolower((string) ($lire('email') ?? ''));
$nom = (string) ($lire('nom') ?? '');

if ($email === '' || $nom === '') {
    fwrite(STDERR, "Usage : --email=adresse --nom=\"Nom Prenoms\" [--appliquer]\n");
    exit(2);
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
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(2);
}

echo "=== CORRECTION DU NOM D'UN COMPTE ===\n\n";

$stmt = $pdo->prepare('SELECT id, full_name, email, rh_employee_id FROM users WHERE LOWER(email) = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$compte = $stmt->fetch();

if (!$compte) {
    fwrite(STDERR, "Aucun compte avec l'adresse « {$email} ».\n");
    exit(1);
}

$ancien = (string) $compte['full_name'];
$employeId = $compte['rh_employee_id'] !== null ? (int) $compte['rh_employee_id'] : 0;

printf("  Compte  : #%d  %s\n", (int) $compte['id'], $compte['email']);
printf("  Avant   : %s\n", $ancien);
printf("  Après   : %s\n", $nom);
printf("  Dossier RH : %s\n\n", $employeId > 0 ? '#' . $employeId . ' (sera corrigé aussi)' : 'aucun');

if ($ancien === $nom) {
    echo "Rien à faire : le compte porte déjà ce nom.\n";
    exit(0);
}

if (!$appliquer) {
    echo "Rien n'a été écrit.\nRelancez avec --appliquer pour corriger.\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    $pdo->prepare('UPDATE users SET full_name = :nom, updated_at = NOW() WHERE id = :id')
        ->execute(['nom' => $nom, 'id' => (int) $compte['id']]);

    if ($employeId > 0) {
        // Sans cette seconde écriture, la prochaine modification de la fiche
        // relirait le nom depuis le dossier RH et annulerait la correction.
        $pdo->prepare('UPDATE rh_employees SET full_name = :nom WHERE id = :id')
            ->execute(['nom' => $nom, 'id' => $employeId]);
    }

    /*
     * La correction entre au journal des comptes comme n'importe quel geste
     * d'administration. Le chaînage est recalculé ici à l'identique de
     * AuditLogService::log() : un script qui corrige en silence est exactement
     * ce que ce journal existe pour empêcher.
     */
    $ancienHash = $pdo->query(
        'SELECT hash_courant FROM lbp_audit_logs WHERE hash_courant IS NOT NULL ORDER BY id DESC LIMIT 1'
    )->fetchColumn() ?: 'GENESIS_LBP_SECURITY_SEED_2026';

    $avant = json_encode(['full_name' => $ancien], JSON_UNESCAPED_UNICODE);
    $apres = json_encode(['full_name' => $nom], JSON_UNESCAPED_UNICODE);
    $quand = date('Y-m-d H:i:s');

    $hash = hash('sha256', sprintf(
        '%d|%s|%s|%d|%s|%s|%s|%s|%s',
        0,
        'update_user',
        'users',
        (int) $compte['id'],
        $avant,
        $apres,
        'console',
        $quand,
        $ancienHash
    ));

    $pdo->prepare('
        INSERT INTO lbp_audit_logs
            (user_id, action, entity_type, entity_id, old_values, new_values,
             ip_address, user_agent, hash_precedent, hash_courant, created_at)
        VALUES
            (NULL, :action, :entite, :id, :avant, :apres, :ip, :ua, :precedent, :courant, :quand)
    ')->execute([
        'action' => 'update_user',
        'entite' => 'users',
        'id' => (int) $compte['id'],
        'avant' => $avant,
        'apres' => $apres,
        'ip' => 'console',
        'ua' => 'CorrigerNomUtilisateur.php',
        'precedent' => $ancienHash,
        'courant' => $hash,
        'quand' => $quand,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

echo "Nom corrigé" . ($employeId > 0 ? ' sur le compte et sur son dossier RH' : '') . ".\n";
echo "La correction figure au journal des comptes.\n";
