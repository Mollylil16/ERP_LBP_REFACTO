<?php

/**
 * Le fuseau horaire d'une agence (company_sites.fuseau_horaire).
 *
 * Usage :
 *   php app/Console/DefinirFuseauAgence.php
 *       liste les agences et leur fuseau, sans rien changer
 *   php app/Console/DefinirFuseauAgence.php --agence=3400 --fuseau=Europe/Paris
 *       pose le fuseau de l'agence 3400 (Paris, Bobigny)
 *
 * Toutes les agences naissent à « Africa/Abidjan », l'heure du serveur et de
 * la base (UTC+0 toute l'année). Seule celle de Paris vit ailleurs : UTC+1 en
 * hiver, UTC+2 en été. Les écrans de réception, de suivi et le rapport
 * affichent alors ses heures à l'heure de Paris, avec celle d'Abidjan entre
 * parenthèses.
 *
 * À lancer une fois, à la main : MigrationRunner ne pose aucune donnée (trois
 * incidents en production sont venus d'écritures dans run()). Rejouable : le
 * relancer avec le même fuseau ne change rien.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/bootstrap/app.php';

use App\Database\Schema;
use App\Models\Database;
use App\Repositories\Colisage\PointageColisRepository;
use App\Support\HeureAgence;

$options = getopt('', ['agence:', 'fuseau:']);
$pdo = Database::getConnection();

if (!(new Schema($pdo))->columnExists('company_sites', 'fuseau_horaire')) {
    fwrite(STDERR, "La colonne company_sites.fuseau_horaire n'existe pas encore : ouvrez une page de l'ERP (elle est ajoutée au premier chargement après la mise à jour), puis relancez." . PHP_EOL);
    exit(1);
}

$repo = new PointageColisRepository($pdo);

if (!isset($options['agence'])) {
    echo 'Agences actives et leur fuseau :' . PHP_EOL;
    foreach ($repo->agencesActives() as $a) {
        echo sprintf('  %6d  %-22s %s', $a['id'], $a['fuseau'], $a['name']) . PHP_EOL;
    }
    echo PHP_EOL . 'Pour changer : php app/Console/DefinirFuseauAgence.php --agence=ID --fuseau=Europe/Paris' . PHP_EOL;
    exit(0);
}

$agenceId = (int) $options['agence'];
$fuseau = trim((string) ($options['fuseau'] ?? ''));

if (!HeureAgence::estValide($fuseau)) {
    fwrite(STDERR, "Fuseau inconnu : « {$fuseau} ». Exemples : Europe/Paris, Africa/Abidjan, Africa/Dakar." . PHP_EOL);
    exit(1);
}

$agence = $repo->agence($agenceId);
if ($agence === null) {
    fwrite(STDERR, "Agence {$agenceId} introuvable." . PHP_EOL);
    exit(1);
}

if ($agence['fuseau'] === $fuseau) {
    echo "Rien à changer : {$agence['name']} est déjà à {$fuseau}." . PHP_EOL;
    exit(0);
}

$repo->definirFuseau($agenceId, $fuseau);
$apres = $repo->agence($agenceId);

echo "{$agence['name']} (agence {$agenceId}) : {$agence['fuseau']} -> {$apres['fuseau']}" . PHP_EOL;
echo 'Il y est en ce moment ' . HeureAgence::double(date('Y-m-d H:i:s'), $fuseau, HeureAgence::SERVEUR) . '.' . PHP_EOL;
exit($apres['fuseau'] === $fuseau ? 0 : 1);
