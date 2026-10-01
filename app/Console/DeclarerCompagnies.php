<?php

/**
 * Déclare les compagnies avec lesquelles LBP expédie, et leur donne leur type.
 *
 * Le rapprochement des envois propose la liste des prestataires en activité :
 * une compagnie absente du référentiel ne peut être choisie ni par l'agent
 * export ni par le comptable, qui reste alors devant une facture qu'il ne peut
 * rattacher à personne.
 *
 * Indiquées par la direction le 30/09/2026 : AIR CI, AIR FRET, K2S.
 *
 * Ce script ne renomme rien et ne supprime rien. Il crée ce qui manque, et
 * donne son type à une compagnie qui n'en a pas — les transitaires repris de
 * l'ancienne base portent un type vide. Une compagnie déjà enregistrée sous un
 * autre nom est signalée, pas touchée : c'est à la direction de trancher.
 *
 * IL N'ÉCRIT RIEN SANS --appliquer.
 *
 * Usage :
 *   php app/Console/DeclarerCompagnies.php
 *   php app/Console/DeclarerCompagnies.php --appliquer
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

/**
 * Les compagnies de transport de LBP, telles que la direction les nomme.
 *
 * « AIR CI » n'y figure pas : la direction confirme le 01/10/2026 que c'est le
 * nom court d'« Air Cote d'Ivoire », deja presente au referentiel. La creer
 * aurait fabrique un doublon, et les envois se seraient repartis entre deux
 * lignes pour une seule compagnie.
 */
const COMPAGNIES = ['AIR FRET', 'K2S'];

const TYPE_COMPAGNIE = 'COMPAGNIE_AERIENNE';

$arguments = $argv ?? [];
$appliquer = in_array('--appliquer', $arguments, true);

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

/** Compare deux noms sans se soucier des accents, de la casse ni des espaces. */
$comparable = static function (string $nom): string {
    $sans = iconv('UTF-8', 'ASCII//TRANSLIT', $nom);

    return preg_replace('/[^a-z0-9]/', '', strtolower($sans === false ? $nom : $sans)) ?? '';
};

$colonne = static function (?string $texte, int $largeur): string {
    $texte = mb_substr((string) ($texte ?? '—'), 0, $largeur);

    return $texte . str_repeat(' ', max(0, $largeur - mb_strlen($texte)));
};

$existants = $pdo->query('SELECT id, name, type, is_active FROM lbp_prestataires ORDER BY name')->fetchAll();

echo "=== COMPAGNIES DE TRANSPORT ===\n\n";
echo "Référentiel actuel :\n";
printf("  %s %s %s\n", $colonne('NOM', 30), $colonne('TYPE', 22), 'ÉTAT');
echo '  ' . str_repeat('-', 62) . "\n";

foreach ($existants as $p) {
    printf(
        "  %s %s %s\n",
        $colonne((string) $p['name'], 30),
        $colonne(((string) $p['type']) !== '' ? (string) $p['type'] : '(sans type)', 22),
        $p['is_active'] ? 'actif' : 'inactif'
    );
}

echo "\nCe qui est attendu :\n";

$aCreer = [];
$aTyper = [];
$aReactiver = [];

foreach (COMPAGNIES as $attendue) {
    $trouvee = null;
    foreach ($existants as $p) {
        if ($comparable((string) $p['name']) === $comparable($attendue)) {
            $trouvee = $p;
            break;
        }
    }

    if ($trouvee === null) {
        $aCreer[] = $attendue;
        printf("  %s à créer\n", $colonne($attendue, 30));
        continue;
    }

    $notes = [];
    if (((string) $trouvee['type']) !== TYPE_COMPAGNIE) {
        $aTyper[] = (int) $trouvee['id'];
        $notes[] = 'type à poser';
    }
    if (!$trouvee['is_active']) {
        $aReactiver[] = (int) $trouvee['id'];
        $notes[] = 'à réactiver';
    }

    printf("  %s %s\n", $colonne($attendue, 30), $notes === [] ? 'déjà en place' : implode(', ', $notes));
}

/*
 * Une compagnie enregistrée sous un autre nom ferait doublon si on la
 * recréait. Le rapprochement du nom est trop incertain pour être automatique :
 * le script signale, la direction tranche.
 */
$proches = [];
foreach ($aCreer as $attendue) {
    $debut = substr($comparable($attendue), 0, 3);
    foreach ($existants as $p) {
        if ($debut !== '' && str_starts_with($comparable((string) $p['name']), $debut)) {
            $proches[$attendue][] = (string) $p['name'];
        }
    }
}

if ($proches !== []) {
    echo "\nNoms voisins déjà présents — vérifiez qu'il ne s'agit pas de la même compagnie :\n";
    foreach ($proches as $attendue => $noms) {
        printf("  %s ← %s\n", $colonne((string) $attendue, 30), implode(', ', array_unique($noms)));
    }
    echo "  (le script ne renomme rien : renommez depuis Colisage → Transporteurs si c'est la même.)\n";
}

if ($aCreer === [] && $aTyper === [] && $aReactiver === []) {
    echo "\nRien à faire : les trois compagnies sont en place.\n";
    exit(0);
}

if (!$appliquer) {
    echo "\nRien n'a été écrit.\n";
    echo "Relancez avec --appliquer pour créer et typer ce qui manque.\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    $creer = $pdo->prepare("
        INSERT INTO lbp_prestataires (type, name, country, is_active, created_at)
        VALUES (:type, :nom, 'Cote d Ivoire', 1, NOW())
    ");
    foreach ($aCreer as $nom) {
        $creer->execute(['type' => TYPE_COMPAGNIE, 'nom' => $nom]);
    }

    if ($aTyper !== []) {
        $pdo->prepare('UPDATE lbp_prestataires SET type = :type, updated_at = NOW() WHERE id IN (' . implode(',', $aTyper) . ')')
            ->execute(['type' => TYPE_COMPAGNIE]);
    }

    if ($aReactiver !== []) {
        $pdo->exec('UPDATE lbp_prestataires SET is_active = 1, updated_at = NOW() WHERE id IN (' . implode(',', $aReactiver) . ')');
    }

    $pdo->commit();

    printf(
        "\n%d compagnie(s) créée(s), %d typée(s), %d réactivée(s).\n",
        count($aCreer),
        count($aTyper),
        count($aReactiver)
    );
    echo "Elles apparaissent désormais dans le filtre Compagnie du rapprochement des envois.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
