<?php

/**
 * Remise a l'endroit des factures et des reglements dates du lendemain.
 *
 * Usage :
 *   php app/Console/CorrigerDatesEncaissements.php                    (analyse seule)
 *   php app/Console/CorrigerDatesEncaissements.php --depuis=2026-09-01
 *   php app/Console/CorrigerDatesEncaissements.php --agence=3403
 *   php app/Console/CorrigerDatesEncaissements.php --appliquer --id-paiement-max=... --id-facture-max=...
 *
 * Ce que ce script repare
 * -----------------------
 * Jusqu'au 13/09/2026, trois depots ecrivaient la date du LENDEMAIN des qu'il
 * etait plus de 15h :
 *
 *   PaiementRepository::create()   -> le reglement
 *   FactureRepository::create()    -> la facture
 *   ColisageRepository::create()   -> le colis
 *
 * Seul le troisieme etait voulu : un colis depose apres 15h part avec le
 * chargement du lendemain. Les deux autres etaient une erreur. Une facture
 * reglee au guichet a 17h03 etait datee du lendemain, alors que l'argent etait
 * dans le tiroir le soir meme. La caissiere comptait plus que ce que l'ecran
 * lui affichait, et l'ecart restait inexplique.
 *
 * Le code est corrige depuis. Ce script s'occupe de ce qui est deja ecrit.
 *
 * Comment une ligne deplacee se reconnait
 * ---------------------------------------
 * Le decalage conservait l'heure et ajoutait exactement un jour. L'application
 * ne pouvait donc JAMAIS enregistrer une ligne dont l'heure stockee soit
 * >= 15:00:00 : passe 15h, l'heure partait sur le lendemain. Toute ligne
 * ecrite avant le correctif et portant une heure >= 15:00:00 est donc une
 * ligne deplacee, et sa vraie date est la veille. Le critere est exact, pas
 * approximatif.
 *
 * Pourquoi le script est rejouable
 * --------------------------------
 * Le critere horaire reste vrai apres coup : un reglement ramene au 10/09 a
 * 15h49 porte toujours 15h49. Le relancer tel quel le reculerait au 09/09.
 * Chaque ligne recalee est donc inscrite dans lbp_audit_logs sous l'action
 * ACTION_RECALAGE, et les passages suivants l'ecartent. Cette table porte un
 * chainage SHA-256 verifie par VerifyAuditIntegrity : la correction laisse
 * une trace qu'on ne peut pas effacer discretement, ce qui est la moindre des
 * choses pour une operation qui deplace de l'argent d'une journee a l'autre.
 *
 * La borne d'identifiant
 * ----------------------
 * Une fois le correctif en ligne, un reglement pris a 17h porte legitimement
 * 17h le jour meme : il ne doit surtout pas etre recule. Comme lbp_paiements
 * n'a pas de colonne de creation, on se sert de l'identifiant auto-incremente,
 * qui suit l'ordre d'insertion. L'analyse affiche l'identifiant le plus haut
 * qu'elle a vu ; --appliquer exige qu'on le lui repasse, et ne touche rien
 * au-dela. C'est ce qui rend le script rejouable sans degat.
 *
 * Ce qui n'est jamais touche
 * --------------------------
 * Le comptage physique declare par la caissiere, son explication d'ecart, les
 * montants, les modes de reglement, les colis. Seules deux colonnes de date
 * bougent : lbp_paiements.date_paiement et lbp_factures.date_emission.
 *
 * Apres ce script
 * ---------------
 * Les points de caisse deja soumis gardent leurs totaux figes. Enchainer avec
 *   php app/Console/RecalculerPointsCaisse.php --depuis=...
 * pour les remettre d'aplomb.
 *
 * Sans --appliquer, AUCUNE ecriture n'est faite.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

// On se connecte sans passer par bootstrap/app.php : celui-ci lance le
// MigrationRunner, qui execute du DDL a chaque appel. Un script qu'on lance
// sur la production pour reparer des dates n'a pas a modifier le schema.
$config = require BASE_PATH . '/config/database.php';

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'] ?? 3306,
        $config['dbname'],
        $config['charset'] ?? 'utf8mb4'
    ),
    $config['username'],
    $config['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$arguments = $argv ?? [];
$appliquer = in_array('--appliquer', $arguments, true);

$depuis = '2000-01-01';
$agenceFiltre = 0;
$idPaiementMax = 0;
$idFactureMax = 0;

foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $trouve)) {
        $depuis = $trouve[1];
    }
    if (preg_match('/^--agence=(\d+)$/', $argument, $trouve)) {
        $agenceFiltre = (int) $trouve[1];
    }
    if (preg_match('/^--id-paiement-max=(\d+)$/', $argument, $trouve)) {
        $idPaiementMax = (int) $trouve[1];
    }
    if (preg_match('/^--id-facture-max=(\d+)$/', $argument, $trouve)) {
        $idFactureMax = (int) $trouve[1];
    }
}

/** Action inscrite dans lbp_audit_logs pour chaque ligne remise a l'endroit. */
const ACTION_RECALAGE = 'recalage_date_bascule_15h';

/**
 * Identifiants deja remis a l'endroit par un passage precedent.
 *
 * @return array<int, int>
 */
$dejaRecalees = static function (PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT entity_id FROM lbp_audit_logs'
            . ' WHERE action = :action AND entity_type = :entite'
        );
        $stmt->execute(['action' => ACTION_RECALAGE, 'entite' => $table]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable) {
        // Table d'audit absente : on ne peut pas garantir l'idempotence, mais
        // les bornes d'identifiant restent le garde-fou principal.
        return [];
    }
};

$ligne = static fn (string $c = '-'): string => str_repeat($c, 78);
$fr = static fn (float $m): string => number_format($m, 0, ',', ' ') . ' XOF';
$veille = static fn (string $dt): string => (new DateTime($dt))->modify('-1 day')->format('Y-m-d H:i:s');

echo $ligne('=') . PHP_EOL;
echo "REMISE A L'ENDROIT DES DATES DE FACTURATION ET D'ENCAISSEMENT" . PHP_EOL;
echo $ligne('=') . PHP_EOL;
echo 'Base      : ' . $config['dbname'] . ' sur ' . $config['host'] . PHP_EOL;
echo 'Periode   : a partir du ' . $depuis . PHP_EOL;
echo 'Agence    : ' . ($agenceFiltre > 0 ? (string) $agenceFiltre : 'toutes') . PHP_EOL;
echo 'Mode      : ' . ($appliquer ? '*** ECRITURE ***' : 'analyse seule, rien ne sera ecrit') . PHP_EOL;
echo 'Lance le  : ' . date('d/m/Y H:i:s') . PHP_EOL;
echo $ligne('=') . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// 1. Les reglements deplaces
// ---------------------------------------------------------------------------

$sqlPaiements = "
    SELECT p.id, p.date_paiement, p.montant, p.devise, p.mode,
           f.numero_facture, f.date_emission, f.agence_id,
           s.name AS agence_name,
           u.full_name AS encaisse_par
    FROM lbp_paiements p
    JOIN lbp_factures f ON f.id = p.facture_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    LEFT JOIN users u ON u.id = p.caissiere_id
    WHERE TIME(p.date_paiement) >= '15:00:00'
      AND p.date_paiement >= :depuis
";
$params = ['depuis' => $depuis . ' 00:00:00'];

if ($agenceFiltre > 0) {
    $sqlPaiements .= ' AND f.agence_id = :agence';
    $params['agence'] = $agenceFiltre;
}
if ($idPaiementMax > 0) {
    $sqlPaiements .= ' AND p.id <= :idmax';
    $params['idmax'] = $idPaiementMax;
}
$sqlPaiements .= ' ORDER BY p.date_paiement ASC, p.id ASC';

$stmt = $pdo->prepare($sqlPaiements);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

$paiementsDejaFaits = $dejaRecalees($pdo, 'lbp_paiements');
$nbPaiementsEcartes = 0;
if ($paiementsDejaFaits !== []) {
    $avant = count($paiements);
    $paiements = array_values(array_filter(
        $paiements,
        static fn (array $p): bool => !in_array((int) $p['id'], $paiementsDejaFaits, true)
    ));
    $nbPaiementsEcartes = $avant - count($paiements);
}

echo '1. REGLEMENTS DATES DU LENDEMAIN' . PHP_EOL;
echo $ligne() . PHP_EOL;

if ($nbPaiementsEcartes > 0) {
    echo $nbPaiementsEcartes . ' reglement(s) deja recale(s) lors d\'un passage precedent,'
        . ' ecarte(s).' . PHP_EOL;
}

$plusHautIdPaiement = 0;

if ($paiements === []) {
    echo 'Aucun. Rien a recaler de ce cote.' . PHP_EOL . PHP_EOL;
} else {
    printf(
        "%-6s %-14s %-12s %-19s %-19s %-18s %s" . PHP_EOL,
        'id',
        'facture',
        'montant',
        'date en base',
        'date reelle',
        'encaisse par',
        'agence'
    );
    echo $ligne() . PHP_EOL;

    $impactParJour = [];

    foreach ($paiements as $p) {
        $reelle = $veille((string) $p['date_paiement']);
        $plusHautIdPaiement = max($plusHautIdPaiement, (int) $p['id']);

        printf(
            "%-6d %-14s %-12s %-19s %-19s %-18s %s" . PHP_EOL,
            (int) $p['id'],
            (string) $p['numero_facture'],
            number_format((float) $p['montant'], 0, ',', ' '),
            (string) $p['date_paiement'],
            $reelle,
            substr((string) ($p['encaisse_par'] ?? 'inconnu'), 0, 18),
            (string) ($p['agence_name'] ?? 'agence ' . $p['agence_id'])
        );

        if ((string) $p['devise'] === 'XOF') {
            $cle = (string) ($p['agence_name'] ?? $p['agence_id']);
            $jourQuitte = substr((string) $p['date_paiement'], 0, 10);
            $jourRejoint = substr($reelle, 0, 10);

            $impactParJour[$cle][$jourQuitte]['sort'] = ($impactParJour[$cle][$jourQuitte]['sort'] ?? 0) + (float) $p['montant'];
            $impactParJour[$cle][$jourRejoint]['entre'] = ($impactParJour[$cle][$jourRejoint]['entre'] ?? 0) + (float) $p['montant'];
        }
    }

    echo $ligne() . PHP_EOL;
    echo count($paiements) . ' reglement(s) a recaler.' . PHP_EOL;
    echo 'Identifiant de paiement le plus haut concerne : ' . $plusHautIdPaiement . PHP_EOL . PHP_EOL;

    // -----------------------------------------------------------------------
    // 2. Ce que cela deplace, agence par agence et jour par jour
    // -----------------------------------------------------------------------

    echo '2. EFFET SUR LES SOLDES QUOTIDIENS' . PHP_EOL;
    echo $ligne() . PHP_EOL;
    echo 'Ce que chaque journee perd et gagne une fois les dates remises.' . PHP_EOL . PHP_EOL;

    foreach ($impactParJour as $agence => $jours) {
        ksort($jours);
        echo $agence . PHP_EOL;
        printf("  %-12s %16s %16s %16s" . PHP_EOL, 'jour', 'sort du jour', 'entre au jour', 'solde du jour');
        foreach ($jours as $jour => $mouvements) {
            $sort = (float) ($mouvements['sort'] ?? 0);
            $entre = (float) ($mouvements['entre'] ?? 0);
            printf(
                "  %-12s %16s %16s %16s" . PHP_EOL,
                $jour,
                $sort > 0 ? '- ' . number_format($sort, 0, ',', ' ') : '',
                $entre > 0 ? '+ ' . number_format($entre, 0, ',', ' ') : '',
                ($entre - $sort >= 0 ? '+ ' : '- ') . number_format(abs($entre - $sort), 0, ',', ' ')
            );
        }
        echo PHP_EOL;
    }
}

// ---------------------------------------------------------------------------
// 3. Les factures deplacees
// ---------------------------------------------------------------------------

$sqlFactures = "
    SELECT f.id, f.numero_facture, f.date_emission, f.montant_total,
           f.montant_encaisse, f.devise, f.statut, f.agence_id,
           s.name AS agence_name
    FROM lbp_factures f
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE TIME(f.date_emission) >= '15:00:00'
      AND f.date_emission >= :depuis
";
$paramsF = ['depuis' => $depuis . ' 00:00:00'];

if ($agenceFiltre > 0) {
    $sqlFactures .= ' AND f.agence_id = :agence';
    $paramsF['agence'] = $agenceFiltre;
}
if ($idFactureMax > 0) {
    $sqlFactures .= ' AND f.id <= :idmax';
    $paramsF['idmax'] = $idFactureMax;
}
$sqlFactures .= ' ORDER BY f.date_emission ASC, f.id ASC';

$stmt = $pdo->prepare($sqlFactures);
$stmt->execute($paramsF);
$factures = $stmt->fetchAll();

$facturesDejaFaites = $dejaRecalees($pdo, 'lbp_factures');
$nbFacturesEcartees = 0;
if ($facturesDejaFaites !== []) {
    $avant = count($factures);
    $factures = array_values(array_filter(
        $factures,
        static fn (array $f): bool => !in_array((int) $f['id'], $facturesDejaFaites, true)
    ));
    $nbFacturesEcartees = $avant - count($factures);
}

echo '3. FACTURES DATEES DU LENDEMAIN' . PHP_EOL;
echo $ligne() . PHP_EOL;

if ($nbFacturesEcartees > 0) {
    echo $nbFacturesEcartees . ' facture(s) deja recalee(s) lors d\'un passage precedent,'
        . ' ecartee(s).' . PHP_EOL;
}

$plusHautIdFacture = 0;

if ($factures === []) {
    echo 'Aucune.' . PHP_EOL . PHP_EOL;
} else {
    printf(
        "%-6s %-14s %-12s %-10s %-19s %-19s %s" . PHP_EOL,
        'id',
        'facture',
        'montant',
        'statut',
        'date en base',
        'date reelle',
        'agence'
    );
    echo $ligne() . PHP_EOL;

    foreach ($factures as $f) {
        $plusHautIdFacture = max($plusHautIdFacture, (int) $f['id']);
        printf(
            "%-6d %-14s %-12s %-10s %-19s %-19s %s" . PHP_EOL,
            (int) $f['id'],
            (string) $f['numero_facture'],
            number_format((float) $f['montant_total'], 0, ',', ' '),
            (string) $f['statut'],
            (string) $f['date_emission'],
            $veille((string) $f['date_emission']),
            (string) ($f['agence_name'] ?? 'agence ' . $f['agence_id'])
        );
    }

    echo $ligne() . PHP_EOL;
    echo count($factures) . ' facture(s) a recaler.' . PHP_EOL;
    echo 'Identifiant de facture le plus haut concerne : ' . $plusHautIdFacture . PHP_EOL . PHP_EOL;
}

// ---------------------------------------------------------------------------
// 4. Les points de caisse qu'il faudra recalculer ensuite
// ---------------------------------------------------------------------------

$joursTouches = [];
foreach ($paiements as $p) {
    $joursTouches[substr((string) $p['date_paiement'], 0, 10)] = true;
    $joursTouches[substr($veille((string) $p['date_paiement']), 0, 10)] = true;
}
foreach ($factures as $f) {
    $joursTouches[substr((string) $f['date_emission'], 0, 10)] = true;
    $joursTouches[substr($veille((string) $f['date_emission']), 0, 10)] = true;
}

echo '4. POINTS DE CAISSE A RECALCULER APRES COUP' . PHP_EOL;
echo $ligne() . PHP_EOL;

if ($joursTouches === []) {
    echo 'Aucun jour touche.' . PHP_EOL . PHP_EOL;
} else {
    $jours = array_keys($joursTouches);
    sort($jours);

    $marqueurs = implode(',', array_fill(0, count($jours), '?'));
    $stmt = $pdo->prepare("
        SELECT e.date_jour, e.statut, e.total_encaisse_xof,
               e.solde_physique_declare, e.ecart_caisse, s.name AS agence_name
        FROM lbp_etats_journaliers e
        LEFT JOIN company_sites s ON s.id = e.agence_id
        WHERE e.date_jour IN ({$marqueurs})
        ORDER BY e.date_jour ASC
    ");
    $stmt->execute($jours);
    $points = $stmt->fetchAll();

    if ($points === []) {
        echo "Aucun point de caisse n'a ete soumis sur ces journees ("
            . implode(', ', $jours) . ').' . PHP_EOL;
        echo "Rien a recalculer : il n'y a pas de total fige a corriger." . PHP_EOL . PHP_EOL;
    } else {
        printf("%-12s %-22s %-10s %16s %16s" . PHP_EOL, 'jour', 'agence', 'statut', 'encaisse fige', 'physique declare');
        foreach ($points as $pt) {
            printf(
                "%-12s %-22s %-10s %16s %16s" . PHP_EOL,
                (string) $pt['date_jour'],
                substr((string) ($pt['agence_name'] ?? ''), 0, 22),
                (string) $pt['statut'],
                number_format((float) $pt['total_encaisse_xof'], 0, ',', ' '),
                number_format((float) $pt['solde_physique_declare'], 0, ',', ' ')
            );
        }
        echo PHP_EOL;
        echo 'Ces totaux sont figes depuis leur soumission. Apres cette correction,' . PHP_EOL;
        echo 'enchainer avec :' . PHP_EOL;
        echo '  php app/Console/RecalculerPointsCaisse.php --depuis=' . $jours[0] . PHP_EOL;
        echo "Le comptage physique et l'explication de la caissiere restent intacts." . PHP_EOL . PHP_EOL;
    }
}

// ---------------------------------------------------------------------------
// 5. Ecriture
// ---------------------------------------------------------------------------

echo $ligne('=') . PHP_EOL;

if (!$appliquer) {
    echo "ANALYSE SEULE : rien n'a ete ecrit." . PHP_EOL . PHP_EOL;

    if ($paiements !== [] || $factures !== []) {
        echo "Avant d'appliquer, sauvegarder les deux tables :" . PHP_EOL . PHP_EOL;
        echo '  mysqldump -u UTILISATEUR -p ' . $config['dbname']
            . ' lbp_paiements lbp_factures > ~/sauvegardes/avant-recalage-'
            . date('Ymd-His') . '.sql' . PHP_EOL . PHP_EOL;
        echo 'Puis, en reprenant les bornes affichees plus haut :' . PHP_EOL . PHP_EOL;
        echo '  php app/Console/CorrigerDatesEncaissements.php --appliquer \\' . PHP_EOL;
        echo '      --depuis=' . $depuis . ' \\' . PHP_EOL;
        echo '      --id-paiement-max=' . $plusHautIdPaiement . ' \\' . PHP_EOL;
        echo '      --id-facture-max=' . $plusHautIdFacture . PHP_EOL . PHP_EOL;
        echo 'Ces deux bornes protegent les lignes ecrites apres le correctif :' . PHP_EOL;
        echo 'passe le deploiement, un reglement de 17h est legitime et ne doit' . PHP_EOL;
        echo 'surtout pas etre recule.' . PHP_EOL . PHP_EOL;
        echo 'QUAND LANCER LA CORRECTION' . PHP_EOL;
        echo str_repeat('-', 78) . PHP_EOL;
        echo "Le matin, avant 15h, et de preference avant l'ouverture des agences." . PHP_EOL . PHP_EOL;
        echo 'La raison : une fois le correctif en ligne, un reglement pris a 17h' . PHP_EOL;
        echo 'porte legitimement 17h le jour meme. Il ressort donc du meme critere' . PHP_EOL;
        echo "que les lignes a recaler, et la borne affichee ci-dessus l'engloberait." . PHP_EOL;
        echo "Tant qu'il n'est pas 15h, aucune ligne legitime ne peut porter une" . PHP_EOL;
        echo "heure tardive : la borne ne designe alors que d'anciennes lignes." . PHP_EOL . PHP_EOL;
        echo 'Si la correction doit se faire en cours de journee, relever plutot' . PHP_EOL;
        echo "l'identifiant maximal AVANT de deployer le correctif :" . PHP_EOL;
        echo '  SELECT MAX(id) FROM lbp_paiements;' . PHP_EOL;
        echo '  SELECT MAX(id) FROM lbp_factures;' . PHP_EOL;
        echo 'et passer ces deux valeurs en bornes.' . PHP_EOL;
    }

    echo $ligne('=') . PHP_EOL;
    exit(0);
}

if ($idPaiementMax === 0 && $idFactureMax === 0) {
    echo "REFUS : --appliquer exige au moins une borne d'identifiant." . PHP_EOL . PHP_EOL;
    echo 'Sans borne, le script reculerait aussi les reglements pris apres le' . PHP_EOL;
    echo "correctif, qui portent legitimement une heure tardive. Relancer d'abord" . PHP_EOL;
    echo 'sans --appliquer : la commande complete y est affichee.' . PHP_EOL;
    echo $ligne('=') . PHP_EOL;
    exit(1);
}

if ($paiements === [] && $factures === []) {
    echo 'Rien a ecrire.' . PHP_EOL;
    echo $ligne('=') . PHP_EOL;
    exit(0);
}

echo 'ECRITURE EN COURS' . PHP_EOL . PHP_EOL;

$journal = [];

// Reprise du chainage SHA-256 de lbp_audit_logs, a l'identique de
// AuditLogService::log(). On le recalcule ici plutot que d'appeler le service :
// celui-ci passe par bootstrap/app.php, donc par le MigrationRunner, qui
// executerait du DDL sur la production avant meme la premiere ecriture.
const GENESIS_AUDIT = 'GENESIS_LBP_SECURITY_SEED_2026';

$dernierHash = null;
try {
    $dernierHash = $pdo->query(
        'SELECT hash_courant FROM lbp_audit_logs WHERE hash_courant IS NOT NULL ORDER BY id DESC LIMIT 1'
    )->fetchColumn() ?: null;
} catch (Throwable) {
    $dernierHash = null;
}

/**
 * Forme canonique d'un document JSON telle que MySQL la rendra a la relecture.
 *
 * old_values et new_values sont des colonnes de type JSON : MySQL les
 * renormalise a l'ecriture, notamment en inserant une espace apres chaque
 * deux-points. Hacher la chaine produite par json_encode() donnerait donc une
 * empreinte que verifyChainIntegrity(), qui relit la valeur stockee, ne
 * retrouverait jamais - et la piste d'audit signalerait une rupture alors que
 * personne n'a rien falsifie. On demande donc la normalisation a MySQL avant
 * de hacher.
 *
 * Le meme piege attend AuditLogService::log(), qui hache l'avant-normalisation.
 */
$normaliserJson = static function (PDO $pdo, string $json): string {
    try {
        $stmt = $pdo->prepare('SELECT CAST(:valeur AS JSON) AS canonique');
        $stmt->execute(['valeur' => $json]);
        $canonique = $stmt->fetchColumn();

        return is_string($canonique) ? $canonique : $json;
    } catch (Throwable) {
        return $json;
    }
};

$insertAudit = null;
try {
    $insertAudit = $pdo->prepare('
        INSERT INTO lbp_audit_logs (
            user_id, action, entity_type, entity_id,
            old_values, new_values, ip_address,
            user_agent, hash_precedent, hash_courant, created_at
        ) VALUES (
            :user_id, :action, :entity_type, :entity_id,
            :old_values, :new_values, :ip_address,
            :user_agent, :hash_precedent, :hash_courant, :created_at
        )
    ');
} catch (Throwable) {
    $insertAudit = null;
}

/**
 * Inscrit une ligne recalee dans la piste d'audit, en prolongeant la chaine.
 */
$tracer = static function (string $table, int $id, string $ancienne, string $nouvelle) use (
    $pdo,
    $insertAudit,
    $normaliserJson,
    &$dernierHash
): void {
    if ($insertAudit === null) {
        return;
    }

    $colonne = $table === 'lbp_paiements' ? 'date_paiement' : 'date_emission';
    $ancienJson = $normaliserJson($pdo, (string) json_encode([$colonne => $ancienne], JSON_UNESCAPED_UNICODE));
    $nouveauJson = $normaliserJson($pdo, (string) json_encode([$colonne => $nouvelle], JSON_UNESCAPED_UNICODE));
    $quand = date('Y-m-d H:i:s');
    $ip = 'cli';
    $precedent = $dernierHash ?: GENESIS_AUDIT;

    $courant = hash('sha256', sprintf(
        '%d|%s|%s|%d|%s|%s|%s|%s|%s',
        0,
        ACTION_RECALAGE,
        $table,
        $id,
        $ancienJson,
        $nouveauJson,
        $ip,
        $quand,
        $precedent
    ));

    $insertAudit->execute([
        // Aucun utilisateur connecte : le script tourne en ligne de commande.
        // La colonne est nullable et porte une cle etrangere vers users, donc
        // NULL est la seule valeur acceptable ici. Le paiement de la chaine de
        // hachage, lui, garde le 0 attendu par AuditLogService.
        'user_id' => null,
        'action' => ACTION_RECALAGE,
        'entity_type' => $table,
        'entity_id' => $id,
        'old_values' => $ancienJson,
        'new_values' => $nouveauJson,
        'ip_address' => $ip,
        'user_agent' => 'CorrigerDatesEncaissements.php',
        'hash_precedent' => $precedent,
        'hash_courant' => $courant,
        'created_at' => $quand,
    ]);

    $dernierHash = $courant;
};

$pdo->beginTransaction();

try {
    $majPaiement = $pdo->prepare(
        'UPDATE lbp_paiements SET date_paiement = :nouvelle WHERE id = :id AND date_paiement = :ancienne'
    );
    $majFacture = $pdo->prepare(
        'UPDATE lbp_factures SET date_emission = :nouvelle WHERE id = :id AND date_emission = :ancienne'
    );

    $nbPaiements = 0;
    foreach ($paiements as $p) {
        $ancienne = (string) $p['date_paiement'];
        $nouvelle = $veille($ancienne);
        $majPaiement->execute(['nouvelle' => $nouvelle, 'id' => (int) $p['id'], 'ancienne' => $ancienne]);
        $nbPaiements += $majPaiement->rowCount();
        $tracer('lbp_paiements', (int) $p['id'], $ancienne, $nouvelle);
        $journal[] = sprintf(
            'paiement #%d (%s, %s) : %s -> %s',
            (int) $p['id'],
            (string) $p['numero_facture'],
            $fr((float) $p['montant']),
            $ancienne,
            $nouvelle
        );
    }

    $nbFactures = 0;
    foreach ($factures as $f) {
        $ancienne = (string) $f['date_emission'];
        $nouvelle = $veille($ancienne);
        $majFacture->execute(['nouvelle' => $nouvelle, 'id' => (int) $f['id'], 'ancienne' => $ancienne]);
        $nbFactures += $majFacture->rowCount();
        $tracer('lbp_factures', (int) $f['id'], $ancienne, $nouvelle);
        $journal[] = sprintf(
            'facture #%d (%s, %s) : %s -> %s',
            (int) $f['id'],
            (string) $f['numero_facture'],
            $fr((float) $f['montant_total']),
            $ancienne,
            $nouvelle
        );
    }

    $pdo->commit();

    echo $nbPaiements . ' reglement(s) recale(s).' . PHP_EOL;
    echo $nbFactures . ' facture(s) recalee(s).' . PHP_EOL;
    echo $insertAudit !== null
        ? 'Chaque ligne est inscrite dans lbp_audit_logs : un second passage les ecartera.' . PHP_EOL . PHP_EOL
        : 'ATTENTION : piste d\'audit indisponible, ne pas relancer ce script.' . PHP_EOL . PHP_EOL;

    // Trace ecrite hors du dossier servi par le serveur web : le .htaccess
    // sert directement tout fichier existant, un journal depose dans
    // public_html serait telechargeable par n'importe qui.
    $dossier = getenv('HOME') ?: sys_get_temp_dir();
    $fichier = rtrim($dossier, '/\\') . '/recalage-dates-' . date('Ymd-His') . '.log';
    file_put_contents(
        $fichier,
        'Recalage lance le ' . date('d/m/Y H:i:s') . PHP_EOL
            . 'Base : ' . $config['dbname'] . PHP_EOL
            . str_repeat('-', 78) . PHP_EOL
            . implode(PHP_EOL, $journal) . PHP_EOL
    );
    echo 'Journal des lignes modifiees : ' . $fichier . PHP_EOL . PHP_EOL;

    echo "Etape suivante : remettre d'aplomb les points de caisse figes." . PHP_EOL;
    echo '  php app/Console/RecalculerPointsCaisse.php --depuis=' . $depuis . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'ECHEC : ' . $e->getMessage() . PHP_EOL;
    echo "Aucune modification n'a ete conservee." . PHP_EOL;
    echo $ligne('=') . PHP_EOL;
    exit(1);
}

echo $ligne('=') . PHP_EOL;
