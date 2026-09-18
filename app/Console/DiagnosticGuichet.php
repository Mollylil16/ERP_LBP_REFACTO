<?php

/**
 * Pourquoi une agence n'arrive pas à encaisser — LECTURE SEULE.
 *
 * Un encaissement refusé a trois causes possibles, et une seule se corrige dans
 * la base :
 *
 *   1. le code déployé ne connaît pas encore le rôle (correctif non livré) ;
 *   2. le compte porte un rôle que le code n'ouvre pas au guichet ;
 *   3. la facture appartient à une autre agence que celle du compte.
 *
 * Ce script répond aux trois d'un coup, pour une agence donnée. Il n'exécute que
 * des SELECT, et ne charge pas bootstrap/app.php : celui-ci lance le
 * MigrationRunner, qui écrit en base à chaque exécution.
 *
 * Usage :
 *   php app/Console/DiagnosticGuichet.php
 *   php app/Console/DiagnosticGuichet.php --agence=aeroport
 *   php app/Console/DiagnosticGuichet.php --agence=3402
 *   php app/Console/DiagnosticGuichet.php --email=nom.prenom@labelleporte.ci
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
$agenceFiltre = '';
$emailFiltre = '';

foreach ($arguments as $argument) {
    if (preg_match('/^--agence=(.+)$/', $argument, $t)) {
        $agenceFiltre = trim($t[1]);
    }
    if (preg_match('/^--email=(.+)$/', $argument, $t)) {
        $emailFiltre = trim($t[1]);
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

// ---------------------------------------------------------------------------
// 1. Ce que le code déployé autorise
//
// Les listes sont lues dans les fichiers présents sur ce serveur, et non
// recopiées ici : c'est la seule façon de savoir si le correctif est en ligne.
// ---------------------------------------------------------------------------

/** @return array<int, string> */
function listeDuCode(string $fichier, string $constante): array
{
    $source = @file_get_contents(BASE_PATH . $fichier);

    if ($source === false || !preg_match('/const\s+' . preg_quote($constante, '/') . '\s*=\s*\[(.*?)\]\s*;/s', $source, $t)) {
        return [];
    }

    preg_match_all("/'([a-z_]+)'/", $t[1], $roles);

    return $roles[1];
}

$guichet = listeDuCode('/app/Controllers/Finance/FinanceController.php', 'ROLES_GUICHET');
$soumission = listeDuCode('/app/Controllers/Finance/FinanceController.php', 'ROLES_SOUMISSION_POINT');
$cumul = listeDuCode('/app/Controllers/Finance/FinanceController.php', 'ROLES_CUMUL_AGENCE');
$porteeAgence = listeDuCode('/app/Helpers/Auth.php', 'ROLES_PORTEE_AGENCE');
$porteeReseau = listeDuCode('/app/Helpers/Auth.php', 'ROLES_PORTEE_RESEAU');

echo "=== CODE DÉPLOYÉ SUR CE SERVEUR ===\n\n";

if ($guichet === [] || $porteeAgence === []) {
    echo "  /!\\ Les listes de rôles n'ont pas été trouvées dans le code.\n";
    echo "      La version déployée est antérieure au correctif de septembre 2026.\n";
    echo "      Aucun réglage en base ne débloquera l'encaissement : il faut livrer le code.\n\n";
} else {
    printf("  Tiennent le guichet (facturer, encaisser)  : %s\n", implode(', ', $guichet));
    printf("  Soumettent le point de caisse             : %s\n", implode(', ', $soumission));
    printf("  Voient le cumul de l'agence               : %s\n", implode(', ', $cumul));
    printf("  Portée d'agence (ouvrir une facture)      : %s\n\n", implode(', ', $porteeAgence));

    foreach (['agent_saisie', 'agent_enregistrement'] as $attendu) {
        $manque = [];
        if (!in_array($attendu, $guichet, true)) {
            $manque[] = 'guichet';
        }
        if (!in_array($attendu, $porteeAgence, true)) {
            $manque[] = "portée d'agence";
        }
        if ($manque !== []) {
            printf("  /!\\ %s est absent de : %s. Correctif non déployé.\n", $attendu, implode(' et ', $manque));
        }
    }
    echo "\n";
}

// ---------------------------------------------------------------------------
// 2. Les comptes de l'agence, et ce que chacun peut faire
// ---------------------------------------------------------------------------

$sql = "
    SELECT u.id, u.full_name, u.email, u.status, u.is_admin, u.agence_id,
           s.name AS agence, s.is_active AS agence_active,
           GROUP_CONCAT(r.role ORDER BY r.role SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN company_sites s ON s.id = u.agence_id
    LEFT JOIN lbp_user_roles r ON r.user_id = u.id
    WHERE 1 = 1
";
$params = [];

if ($emailFiltre !== '') {
    $sql .= " AND u.email = :email";
    $params['email'] = $emailFiltre;
} elseif ($agenceFiltre !== '') {
    if (ctype_digit($agenceFiltre)) {
        $sql .= " AND u.agence_id = :agence";
        $params['agence'] = (int) $agenceFiltre;
    } else {
        $sql .= " AND s.name LIKE :agence";
        $params['agence'] = '%' . $agenceFiltre . '%';
    }
} else {
    $sql .= " AND u.status = 'active'";
}

$sql .= " GROUP BY u.id ORDER BY s.name, u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comptes = $stmt->fetchAll();

echo "=== COMPTES (" . count($comptes) . ") ===\n\n";

$rolesInconnus = [];

foreach ($comptes as $c) {
    $roles = $c['roles'] === null ? [] : array_map('trim', explode(',', (string) $c['roles']));
    $admin = (int) $c['is_admin'] === 1;

    $peutOuvrir = $admin
        || array_intersect($roles, $porteeReseau) !== []
        || ($c['agence_id'] !== null && array_intersect($roles, $porteeAgence) !== []);
    $peutEncaisser = $admin || array_intersect($roles, $guichet) !== [];
    $peutSoumettre = $admin || array_intersect($roles, $soumission) !== [];

    printf(
        "  %-32s %-38s\n    agence  : %s\n    rôles   : %s\n    statut  : %s\n    facturer/encaisser : %s   ouvrir la facture de son agence : %s   soumettre le point : %s\n",
        $c['full_name'],
        $c['email'],
        $c['agence_id'] === null
            ? 'AUCUNE (toute facture lui sera refusée)'
            : ($c['agence'] === null
                ? 'n° ' . $c['agence_id'] . ' — AGENCE SUPPRIMÉE (toute facture lui sera refusée)'
                : $c['agence'] . ' (n° ' . $c['agence_id'] . ')' . ((int) $c['agence_active'] === 1 ? '' : ' — inactive')),
        $roles === [] ? 'AUCUN' : implode(', ', $roles),
        $c['status'] . ($admin ? ' · administrateur' : ''),
        $peutEncaisser ? 'oui' : 'NON',
        $peutOuvrir ? 'oui' : 'NON',
        $peutSoumettre ? 'oui' : 'NON'
    );

    foreach ($roles as $role) {
        if ($role !== '' && !in_array($role, array_merge($guichet, $porteeAgence, $porteeReseau), true)) {
            $rolesInconnus[$role][] = $c['full_name'];
        }
    }

    echo "\n";
}

if ($rolesInconnus !== []) {
    echo "=== RÔLES QUE LE GUICHET NE CONNAÎT PAS ===\n\n";
    foreach ($rolesInconnus as $role => $porteurs) {
        printf("  %-24s %d compte(s) : %s\n", $role, count($porteurs), implode(', ', array_slice($porteurs, 0, 4)));
    }
    echo "\n  Ces rôles n'ouvrent ni la facture ni l'encaissement. Deux solutions :\n";
    echo "  soit le compte reçoit un rôle du guichet dans Administration, soit le rôle\n";
    echo "  est ajouté aux listes du code.\n\n";
}

// ---------------------------------------------------------------------------
// 3. Les factures sont-elles dans la bonne agence ?
//
// Le message « Cette facture appartient à une autre agence » compare
// lbp_factures.agence_id à l'agence du compte. Une facture établie avec une
// « agence de départ » différente échappe donc à celui qui l'a créée.
// ---------------------------------------------------------------------------

$idsComptes = array_column($comptes, 'id');

if ($idsComptes !== []) {
    $marques = implode(',', array_fill(0, count($idsComptes), '?'));

    $stmt = $pdo->prepare("
        SELECT f.numero_facture, f.agence_id, sf.name AS agence_facture,
               u.full_name AS cree_par, u.agence_id AS agence_agent, su.name AS agence_agent_nom,
               f.statut, f.date_emission
        FROM lbp_factures f
        JOIN users u ON u.id = f.created_by
        LEFT JOIN company_sites sf ON sf.id = f.agence_id
        LEFT JOIN company_sites su ON su.id = u.agence_id
        WHERE f.created_by IN ({$marques})
          AND (f.agence_id <> u.agence_id OR u.agence_id IS NULL)
        ORDER BY f.id DESC
        LIMIT 20
    ");
    $stmt->execute($idsComptes);
    $ecarts = $stmt->fetchAll();

    echo "=== FACTURES ÉTABLIES HORS DE L'AGENCE DE L'AGENT (" . count($ecarts) . ") ===\n\n";

    if ($ecarts === []) {
        echo "  Aucune : toutes les factures créées par ces comptes portent bien leur agence.\n";
        echo "  Un refus restant vient donc du rôle, pas de la facture.\n\n";
    } else {
        foreach ($ecarts as $e) {
            printf(
                "  %-18s %s  facture rattachée à %s, agent rattaché à %s (%s)\n",
                $e['numero_facture'],
                substr((string) $e['date_emission'], 0, 10),
                $e['agence_facture'] ?? ('n° ' . $e['agence_id']),
                $e['agence_agent_nom'] ?? ('n° ' . ($e['agence_agent'] ?? 'aucune')),
                $e['cree_par']
            );
        }
        echo "\n  Ces factures sont refusées à leur propre auteur : le champ « Agence de départ »\n";
        echo "  du colis a été laissé sur une autre agence au moment de l'enregistrement.\n\n";
    }
}

echo "Terminé. Aucune donnée n'a été modifiée.\n";
