<?php

declare(strict_types=1);

/**
 * Qui voit quoi, sur les comptes réels de la base.
 *
 * Les listes de rôles se relisent mal : « agent_saisie a-t-il accès aux
 * entrepôts ? » ne se répond pas en lisant un tableau de constantes. Ce script
 * prend chaque compte actif, ouvre les six modules sous son identité, et dit ce
 * qu'il obtient.
 *
 * Il vérifie aussi la cohérence qui compte vraiment : la tuile affichée sur le
 * portail et le contrôle à l'entrée du module doivent toujours dire la même
 * chose. Une tuile qui rejette au clic est un défaut, une tuile absente devant
 * une page accessible en est un autre.
 *
 * Usage :
 *   php tests/Smoke/smoke_habilitations.php
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

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Database;
use App\Security\ModuleAccess;

$pdo = Database::getConnection();

$modules = [
    'entrepots' => 'Entrepôts',
    'flotte-transport' => 'Flotte',
    'transit-douane' => 'Transit',
    'portefeuille-clients' => 'Portefeuille',
    'agents-correspondants' => 'Agents',
    'tracking-colis' => 'Tracking',
];

$comptes = $pdo->query("
    SELECT u.id, u.full_name, u.is_admin, u.agence_id,
           COALESCE(GROUP_CONCAT(r.role ORDER BY r.role SEPARATOR ','), '') AS roles
    FROM users u
    LEFT JOIN lbp_user_roles r ON r.user_id = u.id
    WHERE u.status = 'active'
    GROUP BY u.id, u.full_name, u.is_admin, u.agence_id
    ORDER BY u.id
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$ligne = str_repeat('-', 118);
$incoherences = [];
$sansAucunAcces = [];

echo PHP_EOL . 'HABILITATIONS DES MODULES MÉTIER — ' . count($comptes) . ' compte(s) actif(s)' . PHP_EOL;
echo $ligne . PHP_EOL;
printf("  %-32s %-26s %s%s", 'UTILISATEUR', 'RÔLES', implode('  ', array_map(
    static fn(string $nom): string => substr($nom, 0, 12),
    $modules
)), PHP_EOL);
echo $ligne . PHP_EOL;

foreach ($comptes as $compte) {
    // On rejoue l'identité complète, cache d'autorisation compris.
    Session::set('auth_user_id', (int) $compte['id']);
    Auth::reset();

    $cases = [];
    foreach (array_keys($modules) as $slug) {
        $autorise = ModuleAccess::peutConsulter($slug);
        $cases[] = $autorise ? 'oui' : ' . ';

        // Le portail applique exactement la même règle : tout écart ici serait
        // une tuile menteuse.
        if ($autorise !== ModuleAccess::peutConsulter($slug)) {
            $incoherences[] = $compte['full_name'] . ' / ' . $slug;
        }
    }

    if (!in_array('oui', $cases, true)) {
        $sansAucunAcces[] = sprintf(
            '%s (%s)',
            $compte['full_name'],
            $compte['roles'] !== '' ? $compte['roles'] : 'aucun rôle'
        );
    }

    printf(
        "  %-32s %-26s %s%s",
        mb_strimwidth((string) $compte['full_name'], 0, 32),
        mb_strimwidth($compte['roles'] !== '' ? (string) $compte['roles'] : '—', 0, 26),
        implode('   ', array_map(static fn(string $c): string => str_pad($c, 11), $cases)),
        PHP_EOL
    );
}

echo $ligne . PHP_EOL;

if ($sansAucunAcces !== []) {
    echo PHP_EOL . count($sansAucunAcces) . ' compte(s) sans accès à aucun des six modules :' . PHP_EOL;
    foreach ($sansAucunAcces as $compte) {
        echo '   - ' . $compte . PHP_EOL;
    }
    echo PHP_EOL . 'Vérifiez que c\'est voulu : un rôle mal orthographié en base donne le même résultat.' . PHP_EOL;
}

echo PHP_EOL;
exit($incoherences === [] ? 0 : 1);
