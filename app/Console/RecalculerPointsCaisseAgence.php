<?php

/**
 * Remet les encaissements passés dans le tiroir où l'argent a réellement été pris.
 *
 * Jusqu'au 24/09/2026, un encaissement était compté dans l'agence de la
 * facture. Un client réglant à Abobo Dokui une facture d'Adjamé laissait son
 * billet à Dokui, que le logiciel comptait pour Adjamé. Depuis, chaque
 * encaissement enregistre l'agence où il a été pris — mais les anciens n'ont
 * pas cette information.
 *
 * Ce script la reconstitue : l'agence de la personne qui a encaissé, et à
 * défaut celle de la facture. Il recalcule ensuite les journées déjà soumises
 * avec la bonne règle.
 *
 * IL NE FAIT RIEN SANS --appliquer. Sans cette option il montre, agence par
 * agence et jour par jour, ce que chaque journée deviendrait.
 *
 * Usage :
 *   php app/Console/RecalculerPointsCaisseAgence.php
 *   php app/Console/RecalculerPointsCaisseAgence.php --depuis=2026-09-01
 *   php app/Console/RecalculerPointsCaisseAgence.php --depuis=2026-09-01 --appliquer
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
$depuis = '2000-01-01';
$au = date('Y-m-d');
$appliquer = in_array('--appliquer', $arguments, true);

foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $t)) {
        $depuis = $t[1];
    }
    if (preg_match('/^--au=(\d{4}-\d{2}-\d{2})$/', $argument, $t)) {
        $au = $t[1];
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

echo "=== REMISE DES ENCAISSEMENTS DANS LE BON TIROIR ===\n";
echo 'Période du ' . $depuis . ' au ' . $au . "\n";
echo $appliquer ? "MODE RÉEL : la base va être modifiée.\n\n" : "SIMULATION : rien ne sera modifié.\n\n";

// ---------------------------------------------------------------------------
// 1. Les encaissements sans agence, et celle qu'ils devraient porter.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS nb,
           SUM(CASE WHEN u.agence_id IS NOT NULL THEN 1 ELSE 0 END) AS par_le_collecteur,
           SUM(CASE WHEN u.agence_id IS NULL THEN 1 ELSE 0 END) AS par_la_facture,
           SUM(CASE WHEN u.agence_id IS NOT NULL AND u.agence_id <> f.agence_id THEN 1 ELSE 0 END) AS changent_d_agence,
           COALESCE(SUM(CASE WHEN u.agence_id IS NOT NULL AND u.agence_id <> f.agence_id THEN p.montant ELSE 0 END), 0) AS montant_deplace
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    WHERE p.agence_id IS NULL
      AND DATE(p.date_paiement) BETWEEN :depuis AND :au
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$aTraiter = $stmt->fetch() ?: [];

printf(
    "  %d encaissement(s) sans agence d'origine.\n"
    . "    %d reprennent l'agence de la personne qui a encaissé\n"
    . "    %d, faute de mieux, celle de la facture\n"
    . "    %d changent donc d'agence, soit %s FCFA déplacés\n\n",
    (int) ($aTraiter['nb'] ?? 0),
    (int) ($aTraiter['par_le_collecteur'] ?? 0),
    (int) ($aTraiter['par_la_facture'] ?? 0),
    (int) ($aTraiter['changent_d_agence'] ?? 0),
    $montant((float) ($aTraiter['montant_deplace'] ?? 0))
);

// ---------------------------------------------------------------------------
// 2. Les journées déjà soumises, et ce qu'elles deviendraient.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT e.id, e.date_jour, e.agence_id, s.name AS agence, e.statut,
           e.total_encaisse_xof AS ancien_encaisse,
           e.solde_caisse_agence_xof AS ancien_solde,
           e.solde_physique_declare AS compte,
           e.ecart_caisse AS ancien_ecart
    FROM lbp_etats_journaliers e
    LEFT JOIN company_sites s ON s.id = e.agence_id
    WHERE e.date_jour BETWEEN :depuis AND :au
    ORDER BY e.date_jour, s.name
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$journees = $stmt->fetchAll();

/**
 * Totaux d'une journée avec la règle corrigée : l'encaissement suit l'agence
 * où il a été pris, reconstituée quand elle manque.
 */
$recalculer = static function (PDO $pdo, int $agenceId, string $jour): array {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END), 0) AS encaisse_xof,
            COALESCE(SUM(CASE WHEN p.devise = 'XOF'
                AND LOWER(COALESCE(NULLIF(p.mode, ''), p.mode_paiement)) IN ('especes', 'espece', 'cash')
                THEN p.montant ELSE 0 END), 0) AS especes_xof
        FROM lbp_paiements p
        JOIN lbp_factures f ON f.id = p.facture_id
        LEFT JOIN users u ON u.id = p.caissiere_id
        WHERE COALESCE(p.agence_id, u.agence_id, f.agence_id) = :agence
          AND DATE(p.date_paiement) = :jour
    ");
    $stmt->execute(['agence' => $agenceId, 'jour' => $jour]);

    return $stmt->fetch() ?: ['encaisse_xof' => 0, 'especes_xof' => 0];
};

echo "=== JOURNÉES SOUMISES : AVANT ET APRÈS (" . count($journees) . ") ===\n\n";

if ($journees === []) {
    echo "  Aucune journée sur cette période.\n\n";
} else {
    printf("  %-11s %-22s %12s %12s %12s %12s\n", 'JOUR', 'AGENCE', 'ATTENDU AV.', 'ATTENDU AP.', 'COMPTÉ', 'ÉCART AP.');
    echo '  ' . str_repeat('-', 88) . "\n";
}

$modifiees = 0;

foreach ($journees as $j) {
    $neuf = $recalculer($pdo, (int) $j['agence_id'], (string) $j['date_jour']);
    $nouveauSolde = (float) $neuf['especes_xof'];
    $ancienSolde = (float) $j['ancien_solde'];

    if (abs($nouveauSolde - $ancienSolde) < 0.01) {
        continue;
    }

    $modifiees++;
    $compte = $j['compte'] === null ? null : (float) $j['compte'];
    $nouvelEcart = $compte === null ? null : round($compte - $nouveauSolde, 2);

    printf(
        "  %-11s %-22s %12s %12s %12s %12s\n",
        (string) $j['date_jour'],
        mb_substr((string) ($j['agence'] ?? '-'), 0, 22),
        $montant($ancienSolde),
        $montant($nouveauSolde),
        $compte === null ? '—' : $montant($compte),
        $nouvelEcart === null ? '—' : (($nouvelEcart > 0 ? '+' : '') . $montant($nouvelEcart))
    );

    if ($appliquer) {
        $maj = $pdo->prepare("
            UPDATE lbp_etats_journaliers
               SET total_encaisse_xof = :encaisse,
                   solde_caisse_agence_xof = :solde,
                   ecart_caisse = CASE WHEN solde_physique_declare IS NULL THEN ecart_caisse
                                       ELSE ROUND(solde_physique_declare - :solde_bis, 2) END,
                   updated_at = NOW()
             WHERE id = :id
        ");
        $maj->execute([
            'encaisse' => (float) $neuf['encaisse_xof'],
            'solde' => $nouveauSolde,
            'solde_bis' => $nouveauSolde,
            'id' => (int) $j['id'],
        ]);
    }
}

if ($journees !== []) {
    printf("\n  %d journée(s) changent de solde. Les autres sont déjà justes.\n\n", $modifiees);
}

// ---------------------------------------------------------------------------
// 3. L'écriture, seulement sur demande explicite.
// ---------------------------------------------------------------------------

if (!$appliquer) {
    echo "Rien n'a été modifié. Relancez avec --appliquer pour écrire ces valeurs.\n";
    exit(0);
}

$maj = $pdo->prepare("
    UPDATE lbp_paiements p
      JOIN lbp_factures f ON f.id = p.facture_id
      LEFT JOIN users u ON u.id = p.caissiere_id
       SET p.agence_id = COALESCE(u.agence_id, f.agence_id)
     WHERE p.agence_id IS NULL
       AND DATE(p.date_paiement) BETWEEN :depuis AND :au
");
$maj->execute(['depuis' => $depuis, 'au' => $au]);

printf("\n%d encaissement(s) ont reçu leur agence d'origine.\n", $maj->rowCount());
printf("%d journée(s) recalculées.\n", $modifiees);
echo "\nTerminé.\n";
