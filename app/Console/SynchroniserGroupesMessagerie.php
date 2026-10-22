<?php

/**
 * Passe complète des groupes automatiques de la messagerie (lot 5).
 *
 * Usage :
 *   php app/Console/SynchroniserGroupesMessagerie.php
 *   php app/Console/SynchroniserGroupesMessagerie.php --json
 *
 * Crée les groupes manquants (un par agence active, « Tout LBP »,
 * « Annonces Direction »), ajoute les comptes actifs absents, retire les
 * comptes désactivés, ceux qui ont changé d'agence et NOEL, remet chacun à
 * son rôle (chefs d'agence, Direction) et chaque groupe à son nom.
 *
 * Rejouable à volonté : une seconde passe ne change rien. La même passe part
 * du bouton « Resynchroniser les groupes automatiques » de l'écran
 * Administration > Espace de la messagerie, et chaque utilisateur est déjà
 * remis en ordre quand il ouvre sa liste de discussions : la nuit ne fait
 * que rattraper ceux qui ne l'ont pas ouverte.
 *
 * Recommandation cron, chaque nuit à 2 h 30 (voir
 * installer-groupes-messagerie-cron.sh) :
 *   30 2 * * * php /chemin/vers/app/Console/SynchroniserGroupesMessagerie.php >> ~/logs/lbp_groupes_messagerie.log 2>&1
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

use App\Models\Database;
use App\Services\Messagerie\GroupesAutomatiques;

$enJson = in_array('--json', $argv ?? [], true);
$debut = microtime(true);

try {
    $bilan = GroupesAutomatiques::parDefaut(Database::getConnection())->synchroniserTout();
} catch (Throwable $e) {
    $message = 'Synchronisation interrompue : ' . $e->getMessage();

    if ($enJson) {
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE), PHP_EOL;
    } else {
        fwrite(STDERR, '[LBP-GROUPES] ' . $message . PHP_EOL);
    }

    exit(1);
}

$duree = round((microtime(true) - $debut) * 1000);
$ok = $bilan['erreurs'] === 0;

if ($enJson) {
    echo json_encode(['ok' => $ok, 'bilan' => $bilan, 'duree_ms' => $duree], JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit($ok ? 0 : 1);
}

echo '[LBP-GROUPES] ' . date('Y-m-d H:i:s') . ' — passe terminée en ' . $duree . ' ms' . PHP_EOL;
echo '  Groupes créés            : ' . $bilan['groupes_crees'] . PHP_EOL;
echo '  Groupes remis en ordre   : ' . $bilan['groupes_renommes'] . PHP_EOL;
echo '  Membres ajoutés          : ' . $bilan['membres_ajoutes'] . PHP_EOL;
echo '  Membres retirés          : ' . $bilan['membres_retires'] . PHP_EOL;
echo '  Rôles changés            : ' . $bilan['roles_changes'] . PHP_EOL;
echo '  Erreurs                  : ' . $bilan['erreurs'] . ($ok ? '' : ' (détail dans le journal PHP)') . PHP_EOL;

exit($ok ? 0 : 1);
