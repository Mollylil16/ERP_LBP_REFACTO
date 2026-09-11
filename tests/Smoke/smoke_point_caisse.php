<?php

/**
 * Reconciliation des blocs du point de caisse.
 *
 * Le solde de caisse, la ventilation par type et le journal des encaissements
 * doivent annoncer le meme total. Ils ont longtemps compte trois choses
 * differentes : les paiements du jour, les paiements du jour sur les seules
 * factures du jour, et le cumul encaisse depuis l'emission. D'ou trois chiffres
 * inconciliables a l'ecran, et des caissieres persuadees, a juste titre, d'avoir
 * encaisse plus que le solde affiche.
 *
 * Le scenario couvre les quatre cas qui font diverger ces definitions, plus la
 * bascule de 15 h sur les colis.
 *
 * Usage :  php tests/Smoke/smoke_point_caisse.php
 *
 * Le script ecrit des lignes temporaires prefixees TMP- et les supprime a la
 * fin, y compris en cas d'erreur. A ne pas lancer sur la base de production.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'execute qu'en ligne de commande.
");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Models\Database;
use App\Repositories\Finance\EtatJournalierRepository;

$pdo = Database::getConnection();
$repo = new EtatJournalierRepository($pdo);

$agence = (int) $pdo->query('SELECT id FROM company_sites ORDER BY id LIMIT 1')->fetchColumn();
$caissiere = (int) $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
$agent = (int) $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 1")->fetchColumn();

$J = '2026-09-11';
$veille = '2026-09-10';
$lendemain = '2026-09-12';

$cree = ['clients' => [], 'colis' => [], 'factures' => [], 'paiements' => []];

function client(PDO $pdo, array &$cree, string $nom): int
{
    $st = $pdo->prepare("INSERT INTO lbp_clients (name, phone, type) VALUES (:n, '+2250700000000', 'standard')");
    $st->execute(['n' => $nom]);
    $id = (int) $pdo->lastInsertId();
    $cree['clients'][] = $id;
    return $id;
}

function colis(PDO $pdo, array &$cree, int $ag, int $exp, int $dest, string $ref, string $quand, int $par): int
{
    $st = $pdo->prepare("
        INSERT INTO lbp_colis (numero_tracking, expediteur_id, destinataire_id, poids_total, nombre_colis,
                               valeur_declaree, montant_total, devise, agence_depart_id, agence_arrivee_id,
                               statut, trajet, created_by, created_at)
        VALUES (:ref, :exp, :dest, 10, 1, 1000, 50000, 'XOF', :ag1, :ag2, 'EN_TRANSIT', 'LB-CI', :par, :quand)
    ");
    $st->execute(['ref' => $ref, 'exp' => $exp, 'dest' => $dest, 'ag1' => $ag, 'ag2' => $ag, 'par' => $par, 'quand' => $quand]);
    $id = (int) $pdo->lastInsertId();
    $cree['colis'][] = $id;
    return $id;
}

function facture(PDO $pdo, array &$cree, int $ag, int $colisId, int $client, string $num, float $montant, string $quand, int $par): int
{
    $st = $pdo->prepare("
        INSERT INTO lbp_factures (numero_facture, colis_id, client_id, caissiere_id, agence_id,
                                  montant_total, montant_encaisse, montant_restant, devise,
                                  statut, date_emission, created_by)
        VALUES (:num, :colis, :client, :par, :ag, :m, 0, :m2, 'XOF', 'emise', :quand, :par2)
    ");
    $st->execute(['num' => $num, 'colis' => $colisId, 'client' => $client, 'par' => $par,
                  'ag' => $ag, 'm' => $montant, 'm2' => $montant, 'quand' => $quand, 'par2' => $par]);
    $id = (int) $pdo->lastInsertId();
    $cree['factures'][] = $id;
    return $id;
}

function paiement(PDO $pdo, array &$cree, int $factureId, float $montant, string $quand, int $par): void
{
    $st = $pdo->prepare("
        INSERT INTO lbp_paiements (facture_id, caissiere_id, montant, devise, mode, type, date_paiement)
        VALUES (:f, :par, :m, 'XOF', 'especes', 'total', :quand)
    ");
    $st->execute(['f' => $factureId, 'par' => $par, 'm' => $montant, 'quand' => $quand]);
    $cree['paiements'][] = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE lbp_factures SET montant_encaisse = montant_encaisse + :m,
                   montant_restant = GREATEST(0, montant_restant - :m2) WHERE id = :f")
        ->execute(['m' => $montant, 'm2' => $montant, 'f' => $factureId]);
}

$rapport = [];

try {
    $cl = client($pdo, $cree, 'CLIENT VERIF TEMP');

    // A. Facture emise J, payee J, par la CAISSIERE -> dans les trois blocs
    $c1 = colis($pdo, $cree, $agence, $cl, $cl, 'TMP-A', $J . ' 09:00:00', $caissiere);
    $f1 = facture($pdo, $cree, $agence, $c1, $cl, 'TMP-FA-A', 20000, $J . ' 09:05:00', $caissiere);
    paiement($pdo, $cree, $f1, 20000, $J . ' 09:10:00', $caissiere);

    // B. Facture emise J, payee J, par l AGENT DE SAISIE -> doit s additionner
    $c2 = colis($pdo, $cree, $agence, $cl, $cl, 'TMP-B', $J . ' 10:00:00', $agent);
    $f2 = facture($pdo, $cree, $agence, $c2, $cl, 'TMP-FA-B', 15000, $J . ' 10:05:00', $agent);
    paiement($pdo, $cree, $f2, 15000, $J . ' 10:10:00', $agent);

    // C. Facture emise la VEILLE, payee J -> absente de l ancienne ventilation
    $c3 = colis($pdo, $cree, $agence, $cl, $cl, 'TMP-C', $veille . ' 09:00:00', $caissiere);
    $f3 = facture($pdo, $cree, $agence, $c3, $cl, 'TMP-FA-C', 30000, $veille . ' 09:05:00', $caissiere);
    paiement($pdo, $cree, $f3, 30000, $J . ' 11:00:00', $caissiere);

    // D. Facture emise J, payee le LENDEMAIN -> gonflait le journal
    $c4 = colis($pdo, $cree, $agence, $cl, $cl, 'TMP-D', $J . ' 12:00:00', $caissiere);
    $f4 = facture($pdo, $cree, $agence, $c4, $cl, 'TMP-FA-D', 64900, $J . ' 12:05:00', $caissiere);
    paiement($pdo, $cree, $f4, 64900, $lendemain . ' 08:00:00', $caissiere);

    // E. Colis saisi a 17h le jour J -> bascule au lendemain
    colis($pdo, $cree, $agence, $cl, $cl, 'TMP-E-APRES-15H', $J . ' 17:30:00', $agent);

    // F. Colis saisi a 17h la VEILLE -> compte pour le jour J
    colis($pdo, $cree, $agence, $cl, $cl, 'TMP-F-VEILLE-17H', $veille . ' 17:30:00', $agent);

    $t = $repo->computeTotalsForDay($agence, $J);

    $solde = (float) $t['total_encaisse_xof'];
    $ventilation = array_sum(array_map(static fn($b) => (float) $b['total_encaisse'], $t['breakdown_by_type']));
    $journalEnc = array_sum(array_map(static fn($e) => (float) $e['montant'], $t['encaissements_details']));
    $facturesJour = array_sum(array_map(static fn($i) => (float) $i['encaisse_ce_jour'], $t['invoices_details']));

    $rapport[] = sprintf('  Solde caisse du jour            %10s XOF', number_format($solde, 0, ',', ' '));
    $rapport[] = sprintf('  Ventilation par type (encaisse) %10s XOF', number_format($ventilation, 0, ',', ' '));
    $rapport[] = sprintf('  Journal des encaissements       %10s XOF', number_format($journalEnc, 0, ',', ' '));
    $rapport[] = sprintf('  Factures du jour, encaisse jour %10s XOF', number_format($facturesJour, 0, ',', ' '));
    $rapport[] = '';

    $attendu = 20000.0 + 15000.0 + 30000.0; // A + B + C ; D est paye le lendemain
    $controles = [
        'solde = A+B+C (65 000)' => abs($solde - $attendu) < 0.01,
        'ventilation = solde' => abs($ventilation - $solde) < 0.01,
        'journal encaissements = solde' => abs($journalEnc - $solde) < 0.01,
        'factures du jour = A+B seulement' => abs($facturesJour - 35000.0) < 0.01,
        // Fenetre J-1 15h00 -> J 15h00 : A(9h), B(10h), D(12h) et F(veille 17h30).
        // C (veille 9h) est avant la fenetre, E (J 17h30) est apres.
        'colis du jour = A + B + D + veille 17h (4)' => (int) $t['nb_colis'] === 4,
        'encaisseurs distincts traces' => count(array_unique(array_column($t['encaissements_details'], 'encaisse_par'))) === 2,
        'reglement d anteriorite signale' => count(array_filter($t['encaissements_details'], static fn($e) => (int) $e['regle_apres_coup'] === 1)) === 1,
    ];

    foreach ($controles as $nom => $ok) {
        $rapport[] = sprintf('  %-42s %s', $nom, $ok ? 'OK' : '** ECHEC **');
    }

    $bascules = $repo->colisBasculesAuLendemain($agence, $J);
    $refs = array_column($bascules, 'numero_tracking');
    $rapport[] = '';
    $rapport[] = sprintf('  %-42s %s', 'colis bascules au lendemain listes',
        (count($refs) === 1 && $refs[0] === 'TMP-E-APRES-15H') ? 'OK (TMP-E-APRES-15H)' : '** ECHEC ** ' . implode(',', $refs));
} finally {
    foreach (array_reverse($cree['paiements']) as $id) { $pdo->exec("DELETE FROM lbp_paiements WHERE id = {$id}"); }
    foreach (array_reverse($cree['factures']) as $id) { $pdo->exec("DELETE FROM lbp_factures WHERE id = {$id}"); }
    foreach (array_reverse($cree['colis']) as $id) { $pdo->exec("DELETE FROM lbp_colis WHERE id = {$id}"); }
    foreach (array_reverse($cree['clients']) as $id) { $pdo->exec("DELETE FROM lbp_clients WHERE id = {$id}"); }
}

echo PHP_EOL . 'POINT DE CAISSE - reconciliation des blocs' . PHP_EOL;
echo str_repeat('-', 62) . PHP_EOL;
echo implode(PHP_EOL, $rapport) . PHP_EOL;
echo str_repeat('-', 62) . PHP_EOL;

$reste = (int) $pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE numero_tracking LIKE 'TMP-%'")->fetchColumn();
echo 'Donnees temporaires retirees : ' . ($reste === 0 ? 'oui' : 'NON (' . $reste . ')') . PHP_EOL . PHP_EOL;

exit(str_contains(implode('', $rapport), 'ECHEC') ? 1 : 0);
