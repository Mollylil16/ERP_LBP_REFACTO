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
 *   php tests/Smoke/smoke_pages.php --user=grace.gboko@labelleporte.ci
 *
 * Sortie : une ligne par écran, et un code de sortie non nul si l'un d'eux
 * échoue, pour pouvoir enchaîner avec un commit conditionnel.
 *
 * Un contrôleur qui refuse l'accès appelle redirect(), lequel se termine par
 * exit() sans exclure la ligne de commande : le script s'arrête alors net, et
 * jusqu'ici sans rien afficher. Il reprend donc la suite dans un processus
 * enfant, à partir de l'écran suivant. Les options --depuis et --enfant servent
 * à cela et ne s'utilisent pas à la main.
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

$arguments = $argv ?? [];
$identifiant = 'admin';
$depuis = 0;
$estEnfant = in_array('--enfant', $arguments, true);

foreach ($arguments as $argument) {
    if (preg_match('/^--user=(.+)$/', $argument, $trouve)) {
        $identifiant = $trouve[1];
    }
    if (preg_match('/^--depuis=(\d+)$/', $argument, $trouve)) {
        $depuis = (int) $trouve[1];
    }
}

$utilisateur = (new UserRepository(Database::getConnection()))->findByIdentifier($identifiant);
if (!$utilisateur) {
    fwrite(STDERR, "Compte « {$identifiant} » introuvable.\n");
    exit(2);
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
    'Prise en compte caisse' => '/finance/fonds/prise-en-compte',
    'Imputation (a justifier)' => '/finance/fonds/imputation?statut=decaissee',
    'Imputation (cloturees)' => '/finance/fonds/imputation?statut=imputee',
    'Call Center' => '/call-center/dashboard',
    'Call Center — rayons' => '/call-center/rayons',
    'Call Center — appels' => '/call-center/appels',
    'Call Center — litiges' => '/call-center/litiges',
    'Call Center — suivi' => '/call-center/suivi',
    'Call Center — departs' => '/call-center/suivi-departs',
    'Rapport journalier' => '/colisage/rapports',
    'Rapport mensuel' => '/colisage/rapports/mensuel',
    'Scan express' => '/colisage/scan-express',

    // Écrans témoins, non modifiés
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
 * Les fiches de détail n'existent que s'il y a une ligne à afficher. On prend la
 * première venue, en lecture seule : le script ne doit jamais écrire dans la
 * base sur laquelle on le lance.
 */
foreach ([
    'Fiche demande de fonds' => ['lbp_demandes_fonds', '/finance/fonds/', ''],
    'Fiche colis' => ['lbp_colis', '/colisage/parcels/', ''],
    'Facture client' => ['lbp_colis', '/colisage/parcels/', '/facture'],
] as $nom => [$table, $prefixe, $suffixe]) {
    try {
        $id = Database::getConnection()->query("SELECT id FROM {$table} ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (\Throwable) {
        $id = false;
    }

    if ($id !== false && $id !== null) {
        $ecrans[$nom] = $prefixe . (int) $id . $suffixe;
    }
}

$noms = array_keys($ecrans);
$chemins = array_values($ecrans);
$ligne = str_repeat('-', 78);

$etat = [
    'lignes' => [],
    'echecs' => [],
    'encours' => null,
];

register_shutdown_function(static function () use (&$etat, $noms, $chemins, $estEnfant, $identifiant, $ligne): void {
    $reprise = null;

    if ($etat['encours'] !== null) {
        [$index, $nom, $chemin] = $etat['encours'];
        $etat['lignes'][] = sprintf('  %-28s %-34s %s', $nom, $chemin, '        accès refusé (redirection)');
        $reprise = $index + 1;
    }

    // L'écran a terminé le processus : la suite est reprise dans un enfant.
    if ($reprise !== null && $reprise < count($chemins)) {
        $commande = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
            . ' --user=' . escapeshellarg($identifiant)
            . ' --depuis=' . $reprise . ' --enfant';

        $sortie = [];
        exec($commande . ' 2>&1', $sortie);

        foreach ($sortie as $l) {
            if (str_starts_with($l, 'ECHEC:')) {
                $etat['echecs'][] = substr($l, 6);
                continue;
            }
            if (trim($l) !== '') {
                $etat['lignes'][] = $l;
            }
        }
    }

    if ($estEnfant) {
        // Format brut : le parent recolle les morceaux.
        foreach ($etat['lignes'] as $l) {
            echo $l . PHP_EOL;
        }
        foreach ($etat['echecs'] as $e) {
            echo 'ECHEC:' . $e . PHP_EOL;
        }

        return;
    }

    echo PHP_EOL . 'CHARGEMENT RÉEL DES ÉCRANS — session : ' . $identifiant . PHP_EOL;
    echo $ligne . PHP_EOL;
    echo implode(PHP_EOL, $etat['lignes']) . PHP_EOL;
    echo $ligne . PHP_EOL;

    if ($etat['echecs'] !== []) {
        echo count($etat['echecs']) . ' écran(s) en échec :' . PHP_EOL;
        foreach ($etat['echecs'] as $echec) {
            echo '   - ' . $echec . PHP_EOL;
        }
        echo PHP_EOL;
        exit(1);
    }

    echo count($chemins) . ' écran(s) chargé(s), aucune erreur.' . PHP_EOL . PHP_EOL;
});

for ($i = $depuis; $i < count($chemins); $i++) {
    $etat['encours'] = [$i, $noms[$i], $chemins[$i]];

    [$statut, $detail] = charger($chemins[$i]);

    $etat['encours'] = null;
    $etat['lignes'][] = sprintf('  %-28s %-34s %s', $noms[$i], $chemins[$i], $detail);

    if ($statut !== 'ok') {
        $etat['echecs'][] = $noms[$i] . ' (' . $chemins[$i] . ') : ' . $detail;
    }
}

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
        return ['erreur', 'AVERTISSEMENT : ' . $avertissements[0]
            . (count($avertissements) > 1 ? ' (+' . (count($avertissements) - 1) . ')' : '')];
    }

    // Un refus passé par RoleMiddleware pose un message en session sans
    // interrompre la ligne de commande.
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
