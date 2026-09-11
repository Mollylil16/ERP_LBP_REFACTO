<?php

/**
 * Audit des encaissements — LECTURE SEULE.
 *
 * Répond à une question simple : les soldes affichés correspondent-ils à
 * l'argent réellement encaissé ?
 *
 * Usage :
 *   php app/Console/AuditEncaissements.php
 *   php app/Console/AuditEncaissements.php --depuis=2026-09-07
 *   php app/Console/AuditEncaissements.php --agence=3403
 *   php app/Console/AuditEncaissements.php --fichier=audit.txt
 *   php app/Console/AuditEncaissements.php --json
 *
 * Ce script n'exécute que des SELECT. Il ne charge volontairement pas
 * bootstrap/app.php : ce dernier déclenche le MigrationRunner, qui écrit dans la
 * base à chaque exécution. Un audit ne doit rien modifier de ce qu'il observe.
 *
 * Aucune donnée nominative de client n'est affichée au-delà du nom porté par la
 * facture, déjà visible dans l'interface.
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
$depuis = '2026-09-07';
$agenceFiltre = 0;
$fichier = null;
$enJson = in_array('--json', $arguments, true);

foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $t)) {
        $depuis = $t[1];
    }
    if (preg_match('/^--agence=(\d+)$/', $argument, $t)) {
        $agenceFiltre = (int) $t[1];
    }
    if (preg_match('/^--fichier=(.+)$/', $argument, $t)) {
        $fichier = $t[1];
    }
}

// --- Connexion en lecture seule, sans passer par le bootstrap applicatif -----
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

$filtreAgence = $agenceFiltre > 0 ? ' AND f.agence_id = :agence' : '';
$paramAgence = $agenceFiltre > 0 ? ['agence' => $agenceFiltre] : [];

/**
 * @param array<string, mixed> $params
 * @return array<int, array<string, mixed>>
 */
$lire = static function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
};

$audit = [];

// =========================================================================
// A. Facture contre paiements : le compteur figé est-il juste ?
// =========================================================================
/*
 * lbp_factures.montant_encaisse est un compteur tenu par le code applicatif.
 * S'il diverge de la somme réelle des paiements, tous les écrans qui s'appuient
 * dessus annoncent un montant qui n'a jamais été encaissé — ou en oublient.
 * C'est la première hypothèse à écarter.
 */
$audit['desync_factures'] = $lire("
    SELECT
        f.id, f.numero_facture, f.agence_id, s.name AS agence,
        DATE(f.date_emission) AS date_emission, f.statut,
        f.montant_total,
        f.montant_encaisse AS compteur_facture,
        COALESCE(p.somme_paiements, 0) AS somme_paiements,
        ROUND(f.montant_encaisse - COALESCE(p.somme_paiements, 0), 2) AS ecart,
        f.montant_restant
    FROM lbp_factures f
    LEFT JOIN company_sites s ON s.id = f.agence_id
    LEFT JOIN (
        SELECT facture_id, SUM(montant) AS somme_paiements
        FROM lbp_paiements
        WHERE devise = 'XOF'
        GROUP BY facture_id
    ) p ON p.facture_id = f.id
    WHERE DATE(f.date_emission) >= :depuis
      AND f.devise = 'XOF'
      AND ABS(f.montant_encaisse - COALESCE(p.somme_paiements, 0)) > 0.01
      {$filtreAgence}
    ORDER BY ABS(f.montant_encaisse - COALESCE(p.somme_paiements, 0)) DESC
    LIMIT 200
", ['depuis' => $depuis] + $paramAgence);

// =========================================================================
// B. Jour par jour : paiements réels contre point de caisse enregistré
// =========================================================================
$audit['par_jour'] = $lire("
    SELECT
        j.jour, j.agence_id, s.name AS agence,
        j.paiements_reels_xof, j.nb_paiements,
        e.total_encaisse_xof AS point_enregistre,
        e.solde_caisse_agence_xof AS solde_theorique,
        e.solde_physique_declare,
        e.ecart_caisse,
        e.explication_ecart,
        e.statut,
        ROUND(j.paiements_reels_xof - COALESCE(e.total_encaisse_xof, 0), 2) AS ecart_point
    FROM (
        SELECT
            DATE(p.date_paiement) AS jour,
            f.agence_id,
            SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS paiements_reels_xof,
            COUNT(*) AS nb_paiements
        FROM lbp_paiements p
        JOIN lbp_factures f ON f.id = p.facture_id
        WHERE DATE(p.date_paiement) >= :depuis
          {$filtreAgence}
        GROUP BY DATE(p.date_paiement), f.agence_id
    ) j
    LEFT JOIN company_sites s ON s.id = j.agence_id
    LEFT JOIN lbp_etats_journaliers e ON e.agence_id = j.agence_id AND e.date_jour = j.jour
    ORDER BY j.jour ASC, s.name ASC
", ['depuis' => $depuis] + $paramAgence);

// =========================================================================
// C. Qui a encaissé, et combien
// =========================================================================
$audit['par_encaisseur'] = $lire("
    SELECT
        COALESCE(u.full_name, CONCAT('NON IDENTIFIÉ (id ', COALESCE(p.caissiere_id, 0), ')')) AS encaisseur,
        s.name AS agence,
        COUNT(*) AS nb_paiements,
        SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS total_xof,
        MIN(DATE(p.date_paiement)) AS premier_jour,
        MAX(DATE(p.date_paiement)) AS dernier_jour
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE DATE(p.date_paiement) >= :depuis
      {$filtreAgence}
    GROUP BY encaisseur, s.name
    ORDER BY total_xof DESC
", ['depuis' => $depuis] + $paramAgence);

// =========================================================================
// C bis. Qui a encaissé, jour par jour
// =========================================================================
/*
 * Le cumul par personne ne suffit pas quand un seul jour pose question : il faut
 * pouvoir dire, pour ce jour-là, qui a pris quoi.
 */
$audit['par_jour_encaisseur'] = $lire("
    SELECT
        DATE(p.date_paiement) AS jour,
        s.name AS agence,
        COALESCE(u.full_name, CONCAT('NON IDENTIFIÉ (id ', COALESCE(p.caissiere_id, 0), ')')) AS encaisseur,
        COUNT(*) AS nb,
        SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS total_xof,
        SUM(CASE WHEN DATE(p.date_paiement) <> DATE(f.date_emission) THEN p.montant ELSE 0 END) AS dont_anteriorite
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE DATE(p.date_paiement) >= :depuis
      {$filtreAgence}
    GROUP BY jour, s.name, encaisseur
    ORDER BY jour ASC, total_xof DESC
", ['depuis' => $depuis] + $paramAgence);

// =========================================================================
// D. Encaissements tardifs : réglés un autre jour que l'émission
// =========================================================================
$audit['reglements_tardifs'] = $lire("
    SELECT
        f.numero_facture,
        s.name AS agence,
        DATE(f.date_emission) AS emise_le,
        DATE(p.date_paiement) AS payee_le,
        DATEDIFF(DATE(p.date_paiement), DATE(f.date_emission)) AS jours_ecart,
        TIME(f.date_emission) AS heure_emission,
        p.montant,
        p.devise,
        COALESCE(u.full_name, 'Non identifié') AS encaisse_par
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE DATE(p.date_paiement) >= :depuis
      AND DATE(p.date_paiement) <> DATE(f.date_emission)
      {$filtreAgence}
    ORDER BY jours_ecart DESC, p.date_paiement DESC
    LIMIT 200
", ['depuis' => $depuis] + $paramAgence);

// =========================================================================
// E. Anomalies structurelles
// =========================================================================
$audit['anomalies'] = [];

$audit['anomalies']['paiements_sans_encaisseur'] = $lire("
    SELECT COUNT(*) AS nb, SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS montant
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    WHERE DATE(p.date_paiement) >= :depuis
      AND (p.caissiere_id IS NULL OR p.caissiere_id = 0)
      {$filtreAgence}
", ['depuis' => $depuis] + $paramAgence);

$audit['anomalies']['paiements_orphelins'] = $lire("
    SELECT COUNT(*) AS nb, SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS montant
    FROM lbp_paiements p
    LEFT JOIN lbp_factures f ON f.id = p.facture_id
    WHERE DATE(p.date_paiement) >= :depuis AND f.id IS NULL
", ['depuis' => $depuis]);

$audit['anomalies']['paiements_sur_facture_annulee'] = $lire("
    SELECT f.numero_facture, s.name AS agence, DATE(p.date_paiement) AS jour, p.montant
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE DATE(p.date_paiement) >= :depuis AND f.statut = 'annulee'
      {$filtreAgence}
    ORDER BY p.date_paiement DESC
    LIMIT 100
", ['depuis' => $depuis] + $paramAgence);

$audit['anomalies']['surpaiements'] = $lire("
    SELECT
        f.numero_facture, s.name AS agence, DATE(f.date_emission) AS emise_le,
        f.montant_total, COALESCE(p.somme, 0) AS encaisse_reel,
        ROUND(COALESCE(p.somme, 0) - f.montant_total, 2) AS excedent
    FROM lbp_factures f
    LEFT JOIN company_sites s ON s.id = f.agence_id
    LEFT JOIN (
        SELECT facture_id, SUM(montant) AS somme FROM lbp_paiements WHERE devise = 'XOF' GROUP BY facture_id
    ) p ON p.facture_id = f.id
    WHERE DATE(f.date_emission) >= :depuis
      AND f.devise = 'XOF'
      AND COALESCE(p.somme, 0) - f.montant_total > 0.01
      {$filtreAgence}
    ORDER BY excedent DESC
    LIMIT 100
", ['depuis' => $depuis] + $paramAgence);

$audit['anomalies']['doublons_probables'] = $lire("
    SELECT
        f.numero_facture, s.name AS agence, p.montant,
        DATE_FORMAT(p.date_paiement, '%Y-%m-%d %H:%i') AS minute,
        COUNT(*) AS nb_lignes
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE DATE(p.date_paiement) >= :depuis
      {$filtreAgence}
    GROUP BY f.numero_facture, s.name, p.montant, minute
    HAVING nb_lignes > 1
    ORDER BY nb_lignes DESC
    LIMIT 100
", ['depuis' => $depuis] + $paramAgence);

$audit['anomalies']['modes_non_reconnus'] = $lire("
    SELECT
        LOWER(COALESCE(NULLIF(p.mode, ''), NULLIF(p.mode_paiement, ''), 'VIDE')) AS mode_brut,
        COUNT(*) AS nb,
        SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) AS montant
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    WHERE DATE(p.date_paiement) >= :depuis
      {$filtreAgence}
    GROUP BY mode_brut
    ORDER BY montant DESC
", ['depuis' => $depuis] + $paramAgence);

$audit['anomalies']['colis_apres_15h'] = $lire("
    SELECT
        DATE(c.created_at) AS jour, s.name AS agence,
        COUNT(*) AS nb_colis_apres_15h
    FROM lbp_colis c
    LEFT JOIN company_sites s ON s.id = c.agence_depart_id
    WHERE DATE(c.created_at) >= :depuis AND TIME(c.created_at) >= '15:00:00'
    GROUP BY jour, s.name
    ORDER BY jour ASC
", ['depuis' => $depuis]);

// =========================================================================
// Restitution
// =========================================================================
if ($enJson) {
    $sortie = json_encode(['depuis' => $depuis, 'agence' => $agenceFiltre, 'audit' => $audit],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    $sortie = rapport($audit, $depuis, $agenceFiltre);
}

if ($fichier !== null) {
    file_put_contents($fichier, $sortie);
    echo 'Rapport écrit dans : ' . $fichier . PHP_EOL;
    echo 'Taille : ' . number_format((float) filesize($fichier), 0, ',', ' ') . ' octets' . PHP_EOL;
} else {
    echo $sortie;
}

exit(0);

/**
 * @param array<string, mixed> $audit
 */
function rapport(array $audit, string $depuis, int $agence): string
{
    $trait = str_repeat('=', 100);
    $fin = str_repeat('-', 100);
    $argent = static fn(float $v): string => number_format($v, 0, ',', ' ');

    $l = [];
    $l[] = '';
    $l[] = $trait;
    $l[] = 'AUDIT DES ENCAISSEMENTS — lecture seule';
    $l[] = 'Depuis le ' . $depuis . ($agence > 0 ? ', agence #' . $agence : ', toutes agences');
    $l[] = 'Généré le ' . date('d/m/Y à H:i');
    $l[] = $trait;

    // --- A ---
    $l[] = '';
    $l[] = '1. COMPTEUR DE LA FACTURE CONTRE PAIEMENTS RÉELS';
    $l[] = $fin;
    $l[] = 'Le montant_encaisse de chaque facture est un compteur tenu par le code.';
    $l[] = 'S\'il diverge de la somme des paiements, les écrans annoncent un montant faux.';
    $l[] = '';

    if ($audit['desync_factures'] === []) {
        $l[] = '   Aucun écart. Les compteurs correspondent aux paiements.';
    } else {
        $totalEcart = array_sum(array_map(static fn($r) => (float) $r['ecart'], $audit['desync_factures']));
        $l[] = '   ' . count($audit['desync_factures']) . ' facture(s) désynchronisée(s), écart cumulé '
            . $argent($totalEcart) . ' XOF.';
        $l[] = '';
        $l[] = sprintf('   %-22s %-18s %-12s %14s %14s %12s', 'FACTURE', 'AGENCE', 'ÉMISE LE', 'COMPTEUR', 'PAIEMENTS', 'ÉCART');
        foreach (array_slice($audit['desync_factures'], 0, 40) as $r) {
            $l[] = sprintf('   %-22s %-18s %-12s %14s %14s %12s',
                mb_strimwidth((string) $r['numero_facture'], 0, 22),
                mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 18),
                (string) $r['date_emission'],
                $argent((float) $r['compteur_facture']),
                $argent((float) $r['somme_paiements']),
                $argent((float) $r['ecart']));
        }
        if (count($audit['desync_factures']) > 40) {
            $l[] = '   ... et ' . (count($audit['desync_factures']) - 40) . ' autre(s).';
        }
    }

    // --- B ---
    $l[] = '';
    $l[] = '2. JOUR PAR JOUR — PAIEMENTS RÉELS CONTRE POINT DE CAISSE ENREGISTRÉ';
    $l[] = $fin;
    $l[] = 'PAIEMENTS  : ce que le système a enregistré comme encaissé ce jour-là.';
    $l[] = 'THÉORIQUE  : ce qui devrait se trouver dans le tiroir (espèces uniquement).';
    $l[] = 'PHYSIQUE   : ce que la caissière a réellement compté et déclaré.';
    $l[] = 'ÉCART CAISSE : physique moins théorique. C\'est le seul écart qui parle d\'argent manquant.';
    $l[] = 'Δ SYSTÈME  : paiements réels moins point enregistré. Un écart ici signale une';
    $l[] = '             opération saisie après la soumission du point, pas un manquant.';
    $l[] = '';
    $l[] = sprintf('   %-12s %-24s %13s %4s %13s %13s %13s %13s %-10s',
        'JOUR', 'AGENCE', 'PAIEMENTS', 'NB', 'THÉORIQUE', 'PHYSIQUE', 'ÉCART CAISSE', 'Δ SYSTÈME', 'STATUT');

    foreach ($audit['par_jour'] as $r) {
        $soumis = $r['point_enregistre'] !== null;
        $physique = $r['solde_physique_declare'];

        // L'écart de caisse est recalculé ici, et non repris tel quel : la
        // valeur figée à la soumission peut dater d'avant la correction du
        // solde théorique. Recalculer donne l'écart réel d'aujourd'hui.
        $ecartCaisse = ($soumis && $physique !== null)
            ? round((float) $physique - (float) $r['solde_theorique'], 2)
            : null;

        $l[] = sprintf('   %-12s %-24s %13s %4d %13s %13s %13s %13s %-10s',
            (string) $r['jour'],
            mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 24),
            $argent((float) $r['paiements_reels_xof']),
            (int) $r['nb_paiements'],
            $soumis ? $argent((float) $r['solde_theorique']) : '—',
            $physique !== null ? $argent((float) $physique) : '—',
            $ecartCaisse !== null ? $argent($ecartCaisse) : '—',
            $soumis ? $argent((float) $r['ecart_point']) : '—',
            (string) ($r['statut'] ?? 'aucun'));

        // L'explication écrite par la caissière est souvent la réponse : on la
        // montre sous la ligne plutôt que de la laisser dans la base.
        $explication = trim((string) ($r['explication_ecart'] ?? ''));
        if ($explication !== '') {
            $l[] = '                  → explication déclarée : ' . $explication;
        } elseif ($ecartCaisse !== null && abs($ecartCaisse) > 0.01) {
            $l[] = '                  → écart non expliqué.';
        }
    }

    // --- C ---
    $l[] = '';
    $l[] = '3. QUI A ENCAISSÉ';
    $l[] = $fin;
    $l[] = sprintf('   %-34s %-26s %6s %16s %-12s %-12s',
        'ENCAISSEUR', 'AGENCE', 'NB', 'TOTAL XOF', 'DU', 'AU');

    foreach ($audit['par_encaisseur'] as $r) {
        $l[] = sprintf('   %-34s %-26s %6d %16s %-12s %-12s',
            mb_strimwidth((string) $r['encaisseur'], 0, 34),
            mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 26),
            (int) $r['nb_paiements'],
            $argent((float) $r['total_xof']),
            (string) $r['premier_jour'],
            (string) $r['dernier_jour']);
    }

    // --- C bis ---
    $l[] = '';
    $l[] = '3 bis. QUI A ENCAISSÉ, JOUR PAR JOUR';
    $l[] = $fin;
    $l[] = 'La colonne « dont antériorité » isole ce qui règle une facture d\'un autre jour :';
    $l[] = 'c\'est de l\'argent bien encaissé ce jour, mais absent du facturé du jour.';
    $l[] = '';
    $l[] = sprintf('   %-12s %-24s %-34s %4s %14s %16s',
        'JOUR', 'AGENCE', 'ENCAISSEUR', 'NB', 'TOTAL XOF', 'DONT ANTÉRIORITÉ');

    $jourPrecedent = '';
    foreach ($audit['par_jour_encaisseur'] as $r) {
        $jour = (string) $r['jour'];
        $l[] = sprintf('   %-12s %-24s %-34s %4d %14s %16s',
            $jour === $jourPrecedent ? '' : $jour,
            $jour === $jourPrecedent ? '' : mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 24),
            mb_strimwidth((string) $r['encaisseur'], 0, 34),
            (int) $r['nb'],
            $argent((float) $r['total_xof']),
            (float) $r['dont_anteriorite'] > 0 ? $argent((float) $r['dont_anteriorite']) : '—');
        $jourPrecedent = $jour;
    }

    // --- D ---
    $l[] = '';
    $l[] = '4. RÈGLEMENTS PORTANT SUR UNE FACTURE D\'UN AUTRE JOUR';
    $l[] = $fin;
    $l[] = 'Ce sont eux qui font diverger « encaissé du jour » et « factures du jour ».';
    $l[] = '';

    if ($audit['reglements_tardifs'] === []) {
        $l[] = '   Aucun. Toutes les factures sont réglées le jour de leur émission.';
    } else {
        $total = array_sum(array_map(static fn($r) => (float) $r['montant'], $audit['reglements_tardifs']));
        $l[] = '   ' . count($audit['reglements_tardifs']) . ' règlement(s), ' . $argent($total) . ' XOF au total.';
        $l[] = '';
        $l[] = sprintf('   %-22s %-16s %-18s %-12s %6s %12s %-34s',
            'FACTURE', 'AGENCE', 'ÉMISE LE', 'PAYÉE LE', 'JOURS', 'MONTANT', 'ENCAISSÉ PAR');
        foreach (array_slice($audit['reglements_tardifs'], 0, 60) as $r) {
            $l[] = sprintf('   %-22s %-16s %-18s %-12s %6d %12s %-34s',
                mb_strimwidth((string) $r['numero_facture'], 0, 22),
                mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 16),
                (string) $r['emise_le'] . ' ' . substr((string) $r['heure_emission'], 0, 5),
                (string) $r['payee_le'],
                (int) $r['jours_ecart'],
                $argent((float) $r['montant']),
                mb_strimwidth((string) $r['encaisse_par'], 0, 34));
        }
        if (count($audit['reglements_tardifs']) > 60) {
            $l[] = '   ... et ' . (count($audit['reglements_tardifs']) - 60) . ' autre(s).';
        }
    }

    // --- E ---
    $l[] = '';
    $l[] = '5. ANOMALIES';
    $l[] = $fin;

    $sansEnc = $audit['anomalies']['paiements_sans_encaisseur'][0] ?? [];
    $l[] = sprintf('   Paiements sans encaisseur identifié  : %d ligne(s), %s XOF',
        (int) ($sansEnc['nb'] ?? 0), $argent((float) ($sansEnc['montant'] ?? 0)));

    $orph = $audit['anomalies']['paiements_orphelins'][0] ?? [];
    $l[] = sprintf('   Paiements sans facture rattachée     : %d ligne(s), %s XOF',
        (int) ($orph['nb'] ?? 0), $argent((float) ($orph['montant'] ?? 0)));

    $l[] = sprintf('   Paiements sur facture annulée        : %d', count($audit['anomalies']['paiements_sur_facture_annulee']));
    $l[] = sprintf('   Factures payées au-delà du dû        : %d', count($audit['anomalies']['surpaiements']));
    $l[] = sprintf('   Doublons probables (même minute)     : %d', count($audit['anomalies']['doublons_probables']));

    $l[] = '';
    $l[] = '   Répartition des modes de règlement :';
    foreach ($audit['anomalies']['modes_non_reconnus'] as $r) {
        $l[] = sprintf('      %-22s %6d ligne(s)  %16s XOF',
            (string) $r['mode_brut'], (int) $r['nb'], $argent((float) $r['montant']));
    }

    if ($audit['anomalies']['surpaiements'] !== []) {
        $l[] = '';
        $l[] = '   Détail des surpaiements :';
        foreach (array_slice($audit['anomalies']['surpaiements'], 0, 20) as $r) {
            $l[] = sprintf('      %-22s %-16s dû %12s, encaissé %12s, excédent %12s',
                mb_strimwidth((string) $r['numero_facture'], 0, 22),
                mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 16),
                $argent((float) $r['montant_total']),
                $argent((float) $r['encaisse_reel']),
                $argent((float) $r['excedent']));
        }
    }

    if ($audit['anomalies']['doublons_probables'] !== []) {
        $l[] = '';
        $l[] = '   Détail des doublons probables :';
        foreach (array_slice($audit['anomalies']['doublons_probables'], 0, 20) as $r) {
            $l[] = sprintf('      %-22s %-16s %s  %12s XOF  x%d',
                mb_strimwidth((string) $r['numero_facture'], 0, 22),
                mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 16),
                (string) $r['minute'],
                $argent((float) $r['montant']),
                (int) $r['nb_lignes']);
        }
    }

    $l[] = '';
    $l[] = '   Colis saisis après 15 h (bascule au lendemain) :';
    if ($audit['anomalies']['colis_apres_15h'] === []) {
        $l[] = '      Aucun.';
    } else {
        foreach ($audit['anomalies']['colis_apres_15h'] as $r) {
            $l[] = sprintf('      %-12s %-20s %4d colis', (string) $r['jour'],
                mb_strimwidth((string) ($r['agence'] ?? '—'), 0, 20), (int) $r['nb_colis_apres_15h']);
        }
    }

    $l[] = '';
    $l[] = $trait;
    $l[] = 'Fin de l\'audit. Aucune donnée n\'a été modifiée.';
    $l[] = $trait;
    $l[] = '';

    return implode(PHP_EOL, $l);
}
