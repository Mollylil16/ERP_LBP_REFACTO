<?php

/**
 * Clôture pour le registre les journées encaissées dont personne n'a jamais
 * soumis le point.
 *
 * Mesuré le 24/09/2026 : 1 316 270 FCFA encaissés en un mois sans qu'aucun
 * point ne soit signé — treize journées à Abobo Dokui, sept à l'aéroport.
 *
 * Ce que ce script NE FAIT PAS, et ne peut pas faire : inventer un comptage
 * physique. L'argent de ces journées est déjà parti en banque, personne ne
 * peut plus recompter les billets d'il y a trois semaines. La journée est
 * donc close avec le montant que le logiciel connaît, et la mention
 * explicite qu'aucun comptage n'a eu lieu. Un faux comptage vaudrait moins
 * que pas de comptage du tout : il ferait croire à un contrôle.
 *
 * IL N'ÉCRIT RIEN SANS --appliquer.
 *
 * Usage :
 *   php app/Console/RattraperPointsCaisse.php
 *   php app/Console/RattraperPointsCaisse.php --depuis=2026-09-01 --au=2026-09-30
 *   php app/Console/RattraperPointsCaisse.php --depuis=2026-09-01 --appliquer
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
    fwrite(STDERR, 'Dans ' . basename($e->getFile()) . ' ligne ' . $e->getLine() . "\n");
    exit(3);
});

$arguments = $argv ?? [];
$depuis = '2026-09-01';
$au = date('Y-m-d', strtotime('-1 day'));
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
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(2);
}

$montant = static fn (float $v): string => number_format($v, 0, ',', ' ');
$colonne = static function (?string $texte, int $largeur): string {
    $texte = mb_substr((string) ($texte ?? '—'), 0, $largeur);

    return $texte . str_repeat(' ', max(0, $largeur - mb_strlen($texte)));
};

$colonneAgence = ($pdo->query("SHOW COLUMNS FROM lbp_paiements LIKE 'agence_id'")->fetch() !== false)
    ? 'p.agence_id'
    : 'NULL';

echo "=== RATTRAPAGE DES JOURNÉES SANS POINT DE CAISSE ===\n";
echo 'Période du ' . $depuis . ' au ' . $au . "\n";
echo $appliquer ? "MODE RÉEL : les journées vont être closes.\n\n" : "SIMULATION : rien ne sera écrit.\n\n";

/*
 * Les journées où de l'argent est entré dans une agence sans qu'aucun point
 * n'existe. Un point ouvert mais jamais soumis compte aussi : la journée n'a
 * pas été close.
 */
$stmt = $pdo->prepare("
    SELECT jours.jour, jours.agence_id, s.name AS agence,
           jours.total, jours.especes, jours.nb,
           e.id AS etat_id
    FROM (
        SELECT DATE(p.date_paiement) AS jour,
               COALESCE({$colonneAgence}, u.agence_id, f.agence_id) AS agence_id,
               SUM(p.montant) AS total,
               SUM(CASE WHEN LOWER(COALESCE(NULLIF(p.mode, ''), NULLIF(p.mode_paiement, ''), 'especes'))
                        IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) AS especes,
               COUNT(*) AS nb
        FROM lbp_paiements p
        JOIN lbp_factures f ON f.id = p.facture_id
        LEFT JOIN users u ON u.id = p.caissiere_id
        WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
          AND p.devise = 'XOF'
        GROUP BY jour, agence_id
    ) AS jours
    JOIN company_sites s ON s.id = jours.agence_id
    LEFT JOIN lbp_etats_journaliers e
           ON e.agence_id = jours.agence_id
          AND e.date_jour = jours.jour
          AND e.statut IN ('soumis', 'consolide')
    WHERE e.id IS NULL
    ORDER BY s.name, jours.jour
");
$stmt->execute(['depuis' => $depuis, 'au' => $au]);
$journees = $stmt->fetchAll();

if ($journees === []) {
    echo "Aucune journée à rattraper : toutes les journées encaissées ont leur point.\n";
    exit(0);
}

printf("  %s %s %6s %14s %14s\n", $colonne('AGENCE', 30), $colonne('JOUR', 11), 'OPÉR.', 'ENCAISSÉ', 'DONT ESPÈCES');
echo '  ' . str_repeat('-', 80) . "\n";

$total = 0.0;

foreach ($journees as $j) {
    $total += (float) $j['total'];

    printf(
        "  %s %s %6d %14s %14s\n",
        $colonne((string) $j['agence'], 30),
        $colonne((string) $j['jour'], 11),
        (int) $j['nb'],
        $montant((float) $j['total']),
        $montant((float) $j['especes'])
    );
}

printf("\n  %d journée(s), %s FCFA au total.\n\n", count($journees), $montant($total));

if (!$appliquer) {
    echo "Rien n'a été écrit.\n";
    echo "Relancez avec --appliquer pour clore ces journées.\n\n";
    echo "Ce qui sera écrit : le montant connu du logiciel, la mention « aucun comptage\n";
    echo "physique » et la date de régularisation. Aucun comptage ne sera invente :\n";
    echo "les billets de ces journées-là ne peuvent plus être recomptés.\n";
    exit(0);
}

$explication = 'Régularisation du ' . date('d/m/Y')
    . ' : journée jamais soumise à l\'époque, aucun comptage physique n\'a été effectué.';

$inserer = $pdo->prepare("
    INSERT INTO lbp_etats_journaliers
        (agence_id, date_jour, nb_colis_enregistres, nb_factures_emises,
         total_facture_xof, total_encaisse_xof, solde_caisse_agence_xof,
         solde_physique_declare, ecart_caisse, explication_ecart,
         statut, date_soumission, soumission_retroactive, justification_retard, created_at)
    VALUES
        (:agence, :jour, 0, 0,
         0, :encaisse, :especes,
         NULL, 0, :explication,
         'soumis', NOW(), 1, 'Régularisation', NOW())
");

$mettreAJour = $pdo->prepare("
    UPDATE lbp_etats_journaliers
       SET total_encaisse_xof = :encaisse,
           solde_caisse_agence_xof = :especes,
           solde_physique_declare = NULL,
           ecart_caisse = 0,
           explication_ecart = :explication,
           statut = 'soumis',
           date_soumission = NOW(),
           soumission_retroactive = 1,
           justification_retard = 'Régularisation',
           updated_at = NOW()
     WHERE id = :id
");

$pdo->beginTransaction();

try {
    $creees = 0;
    $completees = 0;

    foreach ($journees as $j) {
        $valeurs = [
            'encaisse' => (float) $j['total'],
            'especes' => (float) $j['especes'],
            'explication' => $explication,
        ];

        // Un point ouvert puis laisse en plan se complete ; sinon on le cree.
        $stmtExistant = $pdo->prepare('SELECT id FROM lbp_etats_journaliers WHERE agence_id = :agence AND date_jour = :jour LIMIT 1');
        $stmtExistant->execute(['agence' => (int) $j['agence_id'], 'jour' => (string) $j['jour']]);
        $existant = $stmtExistant->fetchColumn();

        if ($existant !== false) {
            $mettreAJour->execute($valeurs + ['id' => (int) $existant]);
            $completees++;
        } else {
            $inserer->execute($valeurs + ['agence' => (int) $j['agence_id'], 'jour' => (string) $j['jour']]);
            $creees++;
        }
    }

    $pdo->commit();

    printf("%d journée(s) closes, dont %d brouillons complétés.\n", $creees + $completees, $completees);
    echo "Chacune porte la mention : « " . $explication . " »\n";
    echo "\nElles apparaissent désormais comme soumises, sans comptage physique — ce qui\n";
    echo "est la vérité. Le directeur peut les distinguer d'une journée réellement comptée.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
