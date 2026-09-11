<?php

declare(strict_types=1);

/**
 * Charge réellement les écrans, comme le ferait un navigateur.
 *
 * Les tests unitaires rendent les composants avec des données fabriquées ; ce
 * script fait passer de vraies requêtes par le routeur, sur la vraie base, sous
 * une vraie session. C'est le seul moyen de voir une erreur 500 avant que
 * l'utilisateur ne la trouve.
 *
 * Usage :
 *   php tests/Smoke/smoke_pages.php
 *   php tests/Smoke/smoke_pages.php --user=admin
 *
 * Sortie : une ligne par écran, et un code de sortie non nul si l'un d'eux
 * échoue, pour pouvoir enchaîner avec un commit conditionnel.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Admin\UserRepository;
use App\Router;

$identifiant = 'admin';
foreach ($argv ?? [] as $argument) {
    if (preg_match('/^--user=(.+)$/', $argument, $trouve)) {
        $identifiant = $trouve[1];
    }
}

$utilisateur = (new UserRepository(Database::getConnection()))->findByIdentifier($identifiant);
if (!$utilisateur) {
    fwrite(STDERR, "Compte « {$identifiant} » introuvable.\n");
    exit(1);
}

Session::set('auth_user_id', $utilisateur->id);

/*
 * Écrans à vérifier. On y met en priorité ce qui vient d'être touché, plus
 * quelques écrans très fréquentés qui servent de témoins : s'ils cassent, c'est
 * que la régression est ailleurs que dans les pages modifiées.
 */
$ecrans = [
    'Portail' => '/selection_portail',

    // Les six modules métier reconstruits
    'Entrepôts' => '/entrepots/dashboard',
    'Flotte / Transport' => '/flotte-transport/dashboard',
    'Transit Douane' => '/transit-douane/dashboard',
    'Portefeuille Clients' => '/portefeuille-clients/dashboard',
    'Agents & Correspondants' => '/agents-correspondants/dashboard',
    'Tracking Colis' => '/tracking-colis/dashboard',

    // Vues converties en composants
    'Guide de saisie' => '/colisage/guide',
    'Demandes de fonds' => '/finance/fonds',
    'Nouvelle demande de fonds' => '/finance/fonds/nouveau',

    // Écrans touchés indirectement (navigation, filtres, icônes)
    'Recherche facturation' => '/facturation/filtre',
    'Tableau de bord Finance' => '/finance/dashboard',
    'Points de caisse' => '/finance/clotures',
    'Factures clients' => '/finance/factures',
    'Logistique' => '/logistique/dashboard',
    'Colisage' => '/colisage/dashboard',
    'CRM' => '/crm/dashboard',
    'Pilotage DG' => '/pilotage-dg/dashboard',
    'RH' => '/rh/dashboard',
    'Admin' => '/admin/dashboard',
];

/*
 * Les fiches de detail n existent que s il y a une ligne a afficher. On prend la
 * premiere venue, en lecture seule : le script ne doit jamais ecrire dans la
 * base sur laquelle on le lance.
 */
foreach ([
    'Fiche demande de fonds' => ['lbp_demandes_fonds', '/finance/fonds/'],
    'Fiche colis' => ['lbp_colis', '/colisage/parcels/'],
] as $nom => [$table, $prefixe]) {
    try {
        $id = Database::getConnection()->query("SELECT id FROM {$table} ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (\Throwable) {
        $id = false;
    }

    if ($id !== false && $id !== null) {
        $ecrans[$nom] = $prefixe . (int) $id;
    }
}

$echecs = [];
$ligne = str_repeat('-', 78);

/*
 * Le rapport est accumule puis affiche a la fin. Ecrire au fil de l'eau ferait
 * croire a PHP que les en-tetes sont deja envoyes, et chaque redirection de
 * controle d'acces leverait un avertissement qui n'existe pas en vrai.
 */
$rapport = [
    '',
    'CHARGEMENT RÉEL DES ÉCRANS — session : ' . $utilisateur->fullName,
    $ligne,
];

foreach ($ecrans as $nom => $chemin) {
    [$statut, $detail] = charger($chemin);

    $rapport[] = sprintf('  %-28s %-34s %s', $nom, $chemin, $detail);

    if ($statut !== 'ok') {
        $echecs[] = $nom . ' (' . $chemin . ') : ' . $detail;
    }
}

$rapport[] = $ligne;
echo implode(PHP_EOL, $rapport) . PHP_EOL;

if ($echecs !== []) {
    echo count($echecs) . ' écran(s) en échec :' . PHP_EOL;
    foreach ($echecs as $echec) {
        echo '   - ' . $echec . PHP_EOL;
    }
    echo PHP_EOL;
    exit(1);
}

echo count($ecrans) . ' écran(s) chargé(s) sans erreur.' . PHP_EOL . PHP_EOL;
exit(0);

/**
 * Rejoue une requête complète et rend compte de ce qui s'est passé.
 *
 * @return array{0: string, 1: string}
 */
function charger(string $chemin): array
{
    $_SERVER['REQUEST_URI'] = $chemin;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];

    $requete = parse_url($chemin, PHP_URL_QUERY);
    if (is_string($requete)) {
        parse_str($requete, $_GET);
    }

    // Les avertissements comptent autant que les exceptions : une clé manquante
    // s'affiche en clair au milieu de la page pour l'utilisateur.
    $avertissements = [];
    set_error_handler(static function (int $niveau, string $message, string $fichier, int $numero) use (&$avertissements): bool {
        $avertissements[] = $message . ' (' . basename($fichier) . ':' . $numero . ')';

        return true;
    });

    ob_start();
    try {
        $routeur = new Router();
        $router = $routeur; // les fichiers de routes attendent $router
        require BASE_PATH . '/routes/web.php';
        require BASE_PATH . '/routes/api.php';
        $routeur->dispatch($chemin, 'GET');
        $html = (string) ob_get_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        restore_error_handler();

        return ['erreur', 'ERREUR : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'];
    }
    restore_error_handler();

    if ($avertissements !== []) {
        return ['erreur', 'AVERTISSEMENT : ' . $avertissements[0] . (count($avertissements) > 1 ? ' (+' . (count($avertissements) - 1) . ')' : '')];
    }

    // Un refus d'habilitation se traduit par une redirection : le controleur
    // pose un message en session puis sort. La page rendue, elle, ne dit rien.
    $refus = Session::get('error');
    if (is_string($refus) && str_contains($refus, 'habilitation')) {
        Session::forget('error');

        return ['ok', '        accès refusé (rôle)'];
    }

    if (strlen($html) < 500) {
        return ['erreur', 'PAGE TROP COURTE : ' . strlen($html) . ' octets'];
    }

    return ['ok', sprintf('%7d o', strlen($html))];
}
