<?php

/**
 * Secret partagé des points d'entrée d'API.
 *
 * Usage :
 *   php app/Console/SecretWebhook.php              affiche l'état actuel
 *   php app/Console/SecretWebhook.php --generer    crée un secret et l'enregistre
 *   php app/Console/SecretWebhook.php --tester     vérifie la chaîne de signature
 *
 * Le callback de paiement et le webhook de suivi colis refusent tout appel tant
 * qu'aucun secret n'est configuré. C'est volontaire : un point d'entrée capable de
 * solder une facture ne doit pas être ouvert par défaut.
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

use App\Security\WebhookSignature;

$arguments = $argv ?? [];
$trait = str_repeat('-', 76);

// ---------------------------------------------------------------------------
// Essai de la chaîne de signature
// ---------------------------------------------------------------------------
if (in_array('--tester', $arguments, true)) {
    $pdo = \App\Models\Database::getConnection();
    $secret = WebhookSignature::secret($pdo);

    if ($secret === null) {
        fwrite(STDERR, "Aucun secret configuré : lancez d'abord --generer." . PHP_EOL);
        exit(1);
    }

    $corps = '{"facture_id":1,"transaction_reference":"ESSAI","montant":1000,"statut":"success"}';
    $signe = WebhookSignature::signer($corps, $secret);

    // On rejoue la vérification comme si l'appel arrivait
    $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = $signe['signature'];
    $_SERVER[WebhookSignature::ENTETE_HORODATAGE] = $signe['timestamp'];

    $bon = WebhookSignature::verifier($corps, $pdo);

    $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = 'sha256=' . str_repeat('0', 64);
    $mauvais = WebhookSignature::verifier($corps, $pdo);

    $_SERVER[WebhookSignature::ENTETE_SIGNATURE] = $signe['signature'];
    $_SERVER[WebhookSignature::ENTETE_HORODATAGE] = (string) (time() - 3600);
    $vieux = WebhookSignature::verifier($corps, $pdo);

    echo PHP_EOL . "ESSAI DE LA CHAÎNE DE SIGNATURE" . PHP_EOL . $trait . PHP_EOL;
    printf("  Signature correcte      : %s" . PHP_EOL, $bon['ok'] ? 'ACCEPTÉE' : 'REFUSÉE — ' . $bon['motif']);
    printf("  Signature falsifiée     : %s" . PHP_EOL, $mauvais['ok'] ? 'ACCEPTÉE (ANOMALIE)' : 'refusée — ' . $mauvais['motif']);
    printf("  Appel rejoué (1 h tard) : %s" . PHP_EOL, $vieux['ok'] ? 'ACCEPTÉE (ANOMALIE)' : 'refusée — ' . $vieux['motif']);
    echo PHP_EOL;

    exit($bon['ok'] && !$mauvais['ok'] && !$vieux['ok'] ? 0 : 1);
}

// ---------------------------------------------------------------------------
// Génération
// ---------------------------------------------------------------------------
if (in_array('--generer', $arguments, true)) {
    $existant = WebhookSignature::secret(\App\Models\Database::getConnection());

    if ($existant !== null && !in_array('--forcer', $arguments, true)) {
        echo PHP_EOL . "Un secret est déjà configuré." . PHP_EOL;
        echo "Le remplacer coupera l'opérateur qui utilise l'actuel." . PHP_EOL;
        echo "Pour le faire malgré tout : --generer --forcer" . PHP_EOL . PHP_EOL;
        exit(1);
    }

    $secret = WebhookSignature::genererSecret();

    try {
        WebhookSignature::definirSecret($secret);
    } catch (Throwable $e) {
        fwrite(STDERR, "Enregistrement impossible : " . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, "Placez-le alors dans l'environnement : LBP_WEBHOOK_SECRET=" . $secret . PHP_EOL);
        exit(1);
    }

    echo PHP_EOL . "SECRET GÉNÉRÉ" . PHP_EOL . $trait . PHP_EOL;
    echo $secret . PHP_EOL;
    echo $trait . PHP_EOL;
    echo "Transmettez-le à votre opérateur de paiement par un canal sûr." . PHP_EOL;
    echo "Il n'est plus réaffiché ensuite." . PHP_EOL . PHP_EOL;
    echo "Pour le placer plutôt dans l'environnement du serveur, ce qui est préférable :" . PHP_EOL;
    echo "   LBP_WEBHOOK_SECRET=" . $secret . PHP_EOL . PHP_EOL;
    exit(0);
}

// ---------------------------------------------------------------------------
// État
// ---------------------------------------------------------------------------
$secret = WebhookSignature::secret(\App\Models\Database::getConnection());

echo PHP_EOL . "POINTS D'ENTRÉE D'API" . PHP_EOL . $trait . PHP_EOL;
echo "  POST /api/paiements/callback       marque une facture payée" . PHP_EOL;
echo "  POST /api/webhooks/tracking-update change l'état d'un colis" . PHP_EOL . PHP_EOL;

if ($secret === null) {
    echo "  État : AUCUN SECRET CONFIGURÉ — les deux points d'entrée refusent tout appel." . PHP_EOL . PHP_EOL;
    echo "  Pour les activer :" . PHP_EOL;
    echo "     php app/Console/SecretWebhook.php --generer" . PHP_EOL . PHP_EOL;
    exit(0);
}

$source = (getenv('LBP_WEBHOOK_SECRET') ?: '') !== '' ? "variable d'environnement" : 'base de données';

echo "  État  : secret configuré (" . $source . ")" . PHP_EOL;
echo "  Forme : " . substr($secret, 0, 6) . str_repeat('.', 12) . substr($secret, -4) . PHP_EOL . PHP_EOL;

echo "SCHÉMA À TRANSMETTRE À L'OPÉRATEUR" . PHP_EOL . $trait . PHP_EOL;
echo "  Deux en-têtes à joindre à chaque appel :" . PHP_EOL . PHP_EOL;
echo "     X-LBP-Timestamp : horodatage Unix de l'envoi" . PHP_EOL;
echo "     X-LBP-Signature : sha256=HMAC_SHA256(timestamp + \".\" + corps, secret)" . PHP_EOL . PHP_EOL;
echo "  L'horodatage est signé avec le corps et n'est accepté qu'à 5 minutes près :" . PHP_EOL;
echo "  un appel intercepté ne peut donc pas être rejoué." . PHP_EOL . PHP_EOL;

$corps = '{"facture_id":1,"montant":1000,"statut":"success"}';
$signe = WebhookSignature::signer($corps, $secret);

echo "  Exemple :" . PHP_EOL . PHP_EOL;
echo "     corps      " . $corps . PHP_EOL;
echo "     timestamp  " . $signe['timestamp'] . PHP_EOL;
echo "     signature  " . $signe['signature'] . PHP_EOL . PHP_EOL;
echo "  Restreindre en plus par adresse, si l'opérateur en annonce :" . PHP_EOL;
echo "     LBP_WEBHOOK_IPS=203.0.113.4,203.0.113.5" . PHP_EOL . PHP_EOL;
