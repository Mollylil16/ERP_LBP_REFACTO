<?php

/**
 * Résumé du soir de la réception des colis, dans la messagerie.
 *
 * Usage :
 *   php app/Console/ResumeReceptionsMessagerie.php --url=https://erp.exemple.ci
 *   php app/Console/ResumeReceptionsMessagerie.php --url=… --date=2026-10-23
 *   php app/Console/ResumeReceptionsMessagerie.php --essai          (n'envoie rien)
 *   php app/Console/ResumeReceptionsMessagerie.php --url=… --json
 *
 * Pour chaque départ qui a eu des pointages à l'arrivée dans la journée — de
 * 18 h la veille à 18 h, heure d'Abidjan —, ou dont le délai des manquants
 * vient d'échoir, NOEL publie un message dans le groupe automatique de
 * l'agence d'envoi (« auto:agence:ID ») : reçus, endommagés, non reçus, en
 * attente, taux de réception, et le lien du rapport. Les membres du groupe
 * sont prévenus sur leur téléphone comme pour tout message.
 *
 * Rejouable : un départ n'a qu'un résumé par jour (lbp_colis_resumes_reception).
 * Relancée, la commande ne renvoie rien ; --date rejoue une journée passée
 * avec les comptes d'aujourd'hui.
 *
 * --url est l'adresse publique de l'ERP, sans « / » final : en ligne de
 * commande, aucune requête ne la donne, et un lien relatif ne s'ouvrirait
 * pas depuis la messagerie. À défaut, LBP_URL puis APP_URL.
 *
 * Chaque soir à 18 h 00, heure d'Abidjan (voir installer-resume-receptions-cron.sh) :
 *   0 18 * * * php /chemin/vers/app/Console/ResumeReceptionsMessagerie.php --url=https://… >> ~/logs/lbp_resume_receptions.log 2>&1
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
use App\Repositories\Colisage\PointageColisRepository;
use App\Repositories\Messagerie\MessagerieRepository;
use App\Repositories\Messagerie\NotificationsMessagerieRepository;
use App\Services\Colisage\PointageColisService;
use App\Services\Colisage\ResumeReceptionService;
use App\Services\Messagerie\GroupesAutomatiques;
use App\Services\Messagerie\MessagerieService;
use App\Services\Messagerie\NotificationsMessagerie;
use App\Services\Mobile\NotificationsPush;
use App\Services\Noel\NoelCompte;

$options = getopt('', ['date:', 'url:', 'essai', 'json']);
$enJson = isset($options['json']);
$essai = isset($options['essai']);

$sortir = static function (bool $ok, string $message, array $donnees = []) use ($enJson): never {
    if ($enJson) {
        echo json_encode(['ok' => $ok, 'message' => $message] + $donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    } else {
        fwrite($ok ? STDOUT : STDERR, '[LBP-RESUME-RECEPTION] ' . $message . PHP_EOL);
    }
    exit($ok ? 0 : 1);
};

$jour = (string) ($options['date'] ?? date('Y-m-d'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour) !== 1 || strtotime($jour) === false) {
    $sortir(false, 'Date invalide : ' . $jour . ' (attendu AAAA-MM-JJ).');
}

$url = rtrim((string) ($options['url'] ?? getenv('LBP_URL') ?: ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '')), '/');
if (!$essai && preg_match('#^https?://[^/\s]+#', $url) !== 1) {
    $sortir(false, "Adresse de l'ERP manquante : relancez avec --url=https://votre-domaine (ou LBP_URL).");
}

$debut = microtime(true);

try {
    $pdo = Database::getConnection();
    $repo = new PointageColisRepository($pdo);
    $pointage = new PointageColisService($pdo, $repo);
    $depot = new MessagerieRepository($pdo);

    $publier = static function (): ?array {
        throw new LogicException('Un essai ne publie rien.');
    };

    if (!$essai) {
        $noel = NoelCompte::id($pdo);
        if ($noel === null) {
            $sortir(false, 'Compte de NOEL introuvable et impossible à créer : aucun résumé ne peut être signé.');
        }

        $notifications = new NotificationsMessagerie(
            new NotificationsMessagerieRepository($pdo),
            NotificationsPush::creer($pdo),
            $noel,
            $url . '/messagerie',
        );
        $messagerie = new MessagerieService(
            $depot, $noel, false, null, null, null,
            static fn (int $messageId): int => $notifications->surMessage($messageId),
        );

        $publier = static function (int $agenceId, string $texte) use ($depot, $messagerie): ?array {
            $conversationId = $depot->conversationDirecte(GroupesAutomatiques::PREFIXE_AGENCE . $agenceId);
            if ($conversationId === null) {
                return null;
            }

            return ['conversation_id' => $conversationId, 'message_id' => $messagerie->publierMessageSysteme($conversationId, $texte)];
        };
    }

    $bilan = (new ResumeReceptionService($repo, $pointage, $publier))->executer($jour, $url !== '' ? $url : 'https://ADRESSE-DE-L-ERP', $essai);
} catch (Throwable $e) {
    $sortir(false, 'Résumé interrompu : ' . $e->getMessage());
}

$duree = (int) round((microtime(true) - $debut) * 1000);
$ok = $bilan['erreurs'] === 0;

if ($enJson) {
    echo json_encode(['ok' => $ok, 'essai' => $essai, 'bilan' => $bilan, 'duree_ms' => $duree], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($ok ? 0 : 1);
}

echo '[LBP-RESUME-RECEPTION] ' . date('Y-m-d H:i:s') . ($essai ? ' — ESSAI, rien n’est envoyé' : '') . ' — journée du ' . $bilan['du'] . ' au ' . $bilan['au'] . ', ' . $duree . ' ms' . PHP_EOL;
echo '  Départs concernés        : ' . $bilan['departs'] . PHP_EOL;
echo '  Résumés envoyés          : ' . $bilan['envoyes'] . PHP_EOL;
echo '  Déjà envoyés ce jour     : ' . $bilan['deja_faits'] . PHP_EOL;
echo '  Agence sans groupe       : ' . $bilan['sans_groupe'] . ($bilan['sans_groupe'] > 0 ? ' (lancer SynchroniserGroupesMessagerie.php)' : '') . PHP_EOL;
echo '  Erreurs                  : ' . $bilan['erreurs'] . ($ok ? '' : ' (détail dans le journal PHP ; relancer réessaie)') . PHP_EOL;
foreach ($bilan['messages'] as $m) {
    echo '  - ' . $m['depart'] . ' [' . $m['statut'] . '] ' . $m['texte'] . PHP_EOL;
}

exit($ok ? 0 : 1);
