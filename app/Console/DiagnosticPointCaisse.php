<?php

/**
 * Pourquoi le point de caisse affiche moins que ce que l'agence a compté.
 * LECTURE SEULE — aucune écriture, aucun calcul modifié.
 *
 * Le point de caisse rattache un encaissement à l'agence de la FACTURE :
 *
 *     FROM lbp_paiements p JOIN lbp_factures f ON p.facture_id = f.id
 *     WHERE f.agence_id = :agence_id AND DATE(p.date_paiement) = :date
 *
 * Or l'argent est dans le tiroir de l'agence où il a été encaissé, qui n'est
 * pas toujours celle qui a émis la facture : un client règle à Adjamé un colis
 * enregistré à l'aéroport, ou l'agent choisit une autre « agence de départ »
 * au moment de l'enregistrement. Le billet est à Adjamé, le logiciel le compte
 * à l'aéroport. Adjamé compte donc plus que le logiciel, l'aéroport voit un
 * encaissement qu'il n'a jamais reçu — « les points de caisse sont mélangés ».
 *
 * Ce script mesure l'écart, jour par jour et agence par agence, sur les
 * données réelles.
 *
 * Usage :
 *   php app/Console/DiagnosticPointCaisse.php
 *   php app/Console/DiagnosticPointCaisse.php --depuis=2026-09-01 --au=2026-09-24
 *   php app/Console/DiagnosticPointCaisse.php --agence=3404
 *   php app/Console/DiagnosticPointCaisse.php --detail
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
$depuis = date('Y-m-d', strtotime('-30 days'));
$au = date('Y-m-d');
$agenceFiltre = 0;
$detail = in_array('--detail', $arguments, true);

foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $t)) {
        $depuis = $t[1];
    }
    if (preg_match('/^--au=(\d{4}-\d{2}-\d{2})$/', $argument, $t)) {
        $au = $t[1];
    }
    if (preg_match('/^--agence=(\d+)$/', $argument, $t)) {
        $agenceFiltre = (int) $t[1];
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

$montant = static fn (float $v): string => number_format($v, 0, ',', ' ');

echo "=== POINT DE CAISSE : OÙ EST L'ARGENT, OÙ LE LOGICIEL LE COMPTE ===\n";
echo "Période du " . $depuis . " au " . $au . "\n\n";

// ---------------------------------------------------------------------------
// 1. Le décompte, agence par agence.
//
//    « Compté par le logiciel » reprend exactement la règle du point de caisse
//    (agence de la facture). « Réellement encaissé » suit l'agence de la
//    personne qui a pris l'argent.
// ---------------------------------------------------------------------------

$conditionAgence = $agenceFiltre > 0 ? ' AND s.id = :agence' : '';
$parametres = ['depuis' => $depuis, 'au' => $au];

if ($agenceFiltre > 0) {
    $parametres['agence'] = $agenceFiltre;
}

$stmt = $pdo->prepare("
    SELECT s.id, s.name,
           SUM(CASE WHEN f.agence_id = s.id THEN p.montant ELSE 0 END) AS compte_par_logiciel,
           SUM(CASE WHEN u.agence_id = s.id THEN p.montant ELSE 0 END) AS reellement_encaisse,
           SUM(CASE WHEN u.agence_id = s.id AND f.agence_id <> s.id THEN p.montant ELSE 0 END) AS encaisse_attribue_ailleurs,
           SUM(CASE WHEN f.agence_id = s.id AND u.agence_id <> s.id THEN p.montant ELSE 0 END) AS attribue_ici_sans_etre_encaisse,
           SUM(CASE WHEN u.agence_id = s.id AND f.agence_id <> s.id THEN 1 ELSE 0 END) AS nb_lignes_ailleurs
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    JOIN company_sites s ON s.id = f.agence_id OR s.id = u.agence_id
    WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
      AND p.devise = 'XOF'
      {$conditionAgence}
    GROUP BY s.id, s.name
    HAVING compte_par_logiciel > 0 OR reellement_encaisse > 0
    ORDER BY s.name
");
$stmt->execute($parametres);
$agences = $stmt->fetchAll();

if ($agences === []) {
    echo "Aucun encaissement sur cette période.\n";
    exit(0);
}

printf("  %-34s %16s %16s %14s\n", 'AGENCE', 'COMPTÉ PAR LE', 'RÉELLEMENT', 'ÉCART');
printf("  %-34s %16s %16s %14s\n", '', 'LOGICIEL', 'ENCAISSÉ', '');
echo '  ' . str_repeat('-', 82) . "\n";

foreach ($agences as $a) {
    $ecart = (float) $a['reellement_encaisse'] - (float) $a['compte_par_logiciel'];

    printf(
        "  %-34s %16s %16s %14s\n",
        mb_substr((string) $a['name'], 0, 34),
        $montant((float) $a['compte_par_logiciel']),
        $montant((float) $a['reellement_encaisse']),
        ($ecart > 0 ? '+' : '') . $montant($ecart)
    );
}

echo "\n";
echo "  L'écart est ce que l'agence a dans son tiroir mais que le logiciel\n";
echo "  attribue à une autre agence — ou l'inverse.\n\n";

// ---------------------------------------------------------------------------
// 2. Les encaissements attribués à une autre agence que celle qui les a pris.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT DATE(p.date_paiement) AS jour,
           sp.name AS agence_encaissement,
           sf.name AS agence_facture,
           COUNT(*) AS nb,
           SUM(p.montant) AS total
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    JOIN users u ON u.id = p.caissiere_id
    LEFT JOIN company_sites sp ON sp.id = u.agence_id
    LEFT JOIN company_sites sf ON sf.id = f.agence_id
    WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
      AND p.devise = 'XOF'
      AND u.agence_id IS NOT NULL
      AND f.agence_id <> u.agence_id
    GROUP BY jour, sp.name, sf.name
    ORDER BY jour DESC, total DESC
    LIMIT 40
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$croises = $stmt->fetchAll();

echo "=== ENCAISSEMENTS COMPTÉS DANS UNE AUTRE AGENCE (" . count($croises) . " ligne(s)) ===\n\n";

if ($croises === []) {
    echo "  Aucun : chaque encaissement est compté là où il a été pris.\n\n";
} else {
    printf("  %-12s %-26s %-26s %5s %12s\n", 'JOUR', "ARGENT PRIS À", 'COMPTÉ POUR', 'NB', 'MONTANT');
    echo '  ' . str_repeat('-', 84) . "\n";

    foreach ($croises as $c) {
        printf(
            "  %-12s %-26s %-26s %5d %12s\n",
            $c['jour'],
            mb_substr((string) ($c['agence_encaissement'] ?? '—'), 0, 26),
            mb_substr((string) ($c['agence_facture'] ?? '—'), 0, 26),
            (int) $c['nb'],
            $montant((float) $c['total'])
        );
    }
    echo "\n";
}

// ---------------------------------------------------------------------------
// 3. Les encaissements sans agence connue : le collecteur n'est rattaché à rien.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS nb, COALESCE(SUM(p.montant), 0) AS total
    FROM lbp_paiements p
    LEFT JOIN users u ON u.id = p.caissiere_id
    WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
      AND p.devise = 'XOF'
      AND (p.caissiere_id IS NULL OR u.id IS NULL OR u.agence_id IS NULL)
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$orphelins = $stmt->fetch() ?: ['nb' => 0, 'total' => 0];

echo "=== ENCAISSEMENTS SANS AGENCE D'ORIGINE CONNUE ===\n\n";
printf(
    "  %d encaissement(s), %s FCFA : la personne qui a pris l'argent n'est\n"
    . "  rattachée à aucune agence, ou son compte a été supprimé.\n\n",
    (int) $orphelins['nb'],
    $montant((float) $orphelins['total'])
);

// ---------------------------------------------------------------------------
// 4. Factures emises pour une autre agence que celle de leur auteur.
//
//    L'agence d'une facture est celle de l'« agence de depart » du colis, que
//    l'agent choisit librement au moment de l'enregistrement
//    (FinanceController::factureStore). Indiquer une autre agence de depart
//    envoie la facture — et donc l'argent — ailleurs, alors que la transaction
//    s'est faite au comptoir de l'agent.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT sf.name AS agence_facture,
           su.name AS agence_agent,
           COUNT(*) AS nb,
           SUM(f.montant_total) AS total_facture,
           SUM(f.montant_encaisse) AS total_encaisse
    FROM lbp_factures f
    JOIN users u ON u.id = f.created_by
    LEFT JOIN company_sites sf ON sf.id = f.agence_id
    LEFT JOIN company_sites su ON su.id = u.agence_id
    WHERE DATE(f.date_emission) BETWEEN :depuis AND :au
      AND u.agence_id IS NOT NULL
      AND f.agence_id <> u.agence_id
    GROUP BY sf.name, su.name
    ORDER BY total_encaisse DESC
    LIMIT 30
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$mauvaiseAgence = $stmt->fetchAll();

echo "=== FACTURES ÉMISES POUR UNE AUTRE AGENCE QUE CELLE DE L'AGENT (" . count($mauvaiseAgence) . ") ===

";

if ($mauvaiseAgence === []) {
    echo "  Aucune : chaque facture porte l'agence de l'agent qui l'a établie.

";
} else {
    printf("  %-26s %-26s %5s %14s %14s
", "AGENT AU COMPTOIR DE", 'FACTURE MISE AU COMPTE DE', 'NB', 'FACTURÉ', 'ENCAISSÉ');
    echo '  ' . str_repeat('-', 90) . "
";

    foreach ($mauvaiseAgence as $m) {
        printf(
            "  %-26s %-26s %5d %14s %14s
",
            mb_substr((string) ($m['agence_agent'] ?? '—'), 0, 26),
            mb_substr((string) ($m['agence_facture'] ?? '—'), 0, 26),
            (int) $m['nb'],
            $montant((float) $m['total_facture']),
            $montant((float) $m['total_encaisse'])
        );
    }

    echo "
  La colonne « encaissé » est l'argent que la premiere agence a dans son
";
    echo "  tiroir et que le point de caisse compte pour la seconde.

";
}

// ---------------------------------------------------------------------------
// 5. Le détail ligne à ligne, sur demande.
// ---------------------------------------------------------------------------

if ($detail) {
    $stmt = $pdo->prepare("
        SELECT p.date_paiement, p.montant, p.mode, f.numero_facture,
               u.full_name AS encaisse_par,
               sp.name AS agence_encaissement,
               sf.name AS agence_facture
        FROM lbp_paiements p
        JOIN lbp_factures f ON f.id = p.facture_id
        JOIN users u ON u.id = p.caissiere_id
        LEFT JOIN company_sites sp ON sp.id = u.agence_id
        LEFT JOIN company_sites sf ON sf.id = f.agence_id
        WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
          AND p.devise = 'XOF'
          AND u.agence_id IS NOT NULL
          AND f.agence_id <> u.agence_id
        ORDER BY p.date_paiement DESC
        LIMIT 60
    ");
    $stmt->execute(['depuis' => $depuis, 'au' => $au]);
    $lignes = $stmt->fetchAll();

    echo "=== DÉTAIL DES ENCAISSEMENTS MAL ATTRIBUÉS (" . count($lignes) . ") ===\n\n";

    foreach ($lignes as $l) {
        printf(
            "  %-19s %-20s %10s  pris par %-24s à %-20s → compté pour %s\n",
            substr((string) $l['date_paiement'], 0, 19),
            (string) $l['numero_facture'],
            $montant((float) $l['montant']),
            mb_substr((string) $l['encaisse_par'], 0, 24),
            mb_substr((string) ($l['agence_encaissement'] ?? '—'), 0, 20),
            mb_substr((string) ($l['agence_facture'] ?? '—'), 0, 22)
        );
    }

    echo "\n";
}

echo "Terminé. Aucune donnée n'a été modifiée.\n";
