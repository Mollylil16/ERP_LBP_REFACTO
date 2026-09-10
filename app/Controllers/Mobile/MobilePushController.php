<?php

declare(strict_types=1);

namespace App\Controllers\Mobile;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Mobile\MobileDeviceRepository;
use App\Repositories\Mobile\PushSubscriptionRepository;
use App\Services\Mobile\MobileAuthService;
use App\Services\Mobile\WebPushService;
use Throwable;

/**
 * Abonnement du téléphone aux notifications et envoi d'essai.
 *
 * Les requêtes viennent du script de la page en JSON : le jeton anti-rejeu est
 * donc transmis dans le corps plutôt que par un formulaire.
 */
final class MobilePushController extends BaseController
{
    private MobileAuthService $acces;
    private PushSubscriptionRepository $abonnements;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->acces = new MobileAuthService(new MobileDeviceRepository($pdo));
        $this->abonnements = new PushSubscriptionRepository($pdo);
    }

    /**
     * Clé publique VAPID, nécessaire au navigateur pour créer un abonnement.
     */
    public function clePublique(): void
    {
        if (!$this->garde()) {
            return;
        }

        $cle = (new WebPushService(Database::getConnection()))->clePubliqueVapid();

        if ($cle === null) {
            Response::json([
                'ok' => false,
                'message' => 'Les clés de notification n\'ont pas pu être générées sur le serveur.',
            ], 500);
            return;
        }

        Response::json(['ok' => true, 'cle' => $cle]);
    }

    public function abonner(): void
    {
        if (!$this->garde()) {
            return;
        }

        $corps = $this->corpsJson();
        if (!$this->jetonValide($corps)) {
            return;
        }

        $endpoint = trim((string) ($corps['endpoint'] ?? ''));
        $p256dh = trim((string) ($corps['p256dh'] ?? ''));
        $auth = trim((string) ($corps['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            Response::json(['ok' => false, 'message' => 'Abonnement incomplet.'], 422);
            return;
        }

        // Seuls les services de push connus sont acceptés : un endpoint arbitraire
        // transformerait le serveur en relais vers une adresse choisie par un tiers.
        if (!self::endpointAutorise($endpoint)) {
            Response::json(['ok' => false, 'message' => 'Service de notification non reconnu.'], 422);
            return;
        }

        $appareil = $this->acces->appareilCourant();

        try {
            $this->abonnements->enregistrer(
                (int) Auth::id(),
                $appareil !== null ? (int) $appareil['id'] : null,
                $endpoint,
                $p256dh,
                $auth
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Enregistrement impossible.'], 500);
            return;
        }

        Response::json(['ok' => true]);
    }

    public function desabonner(): void
    {
        if (!$this->garde()) {
            return;
        }

        $corps = $this->corpsJson();
        if (!$this->jetonValide($corps)) {
            return;
        }

        $endpoint = trim((string) ($corps['endpoint'] ?? ''));
        if ($endpoint !== '') {
            $this->abonnements->supprimerParEndpoint($endpoint);
        }

        Response::json(['ok' => true]);
    }

    /**
     * Envoi d'essai vers tous les appareils de l'utilisateur, pour vérifier la chaîne.
     */
    public function test(): void
    {
        if (!$this->garde()) {
            return;
        }

        $corps = $this->corpsJson();
        if (!$this->jetonValide($corps)) {
            return;
        }

        $push = new WebPushService(Database::getConnection());
        $envoyes = 0;
        $echecs = 0;

        foreach ($this->abonnements->pourUtilisateur((int) Auth::id()) as $abonnement) {
            $resultat = $push->envoyer($abonnement, [
                'titre' => 'LBP Direction',
                'corps' => 'Les notifications fonctionnent sur cet appareil.',
                'tag' => 'test',
            ]);

            if ($resultat['ok']) {
                $this->abonnements->marquerSucces((int) $abonnement['id']);
                $envoyes++;
                continue;
            }

            $echecs++;
            if ($resultat['expire']) {
                $this->abonnements->supprimer((int) $abonnement['id']);
            } else {
                $this->abonnements->marquerEchec((int) $abonnement['id']);
            }
        }

        Response::json(['ok' => $envoyes > 0, 'envoyes' => $envoyes, 'echecs' => $echecs]);
    }

    // -----------------------------------------------------------------
    // Outils
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function corpsJson(): array
    {
        $brut = file_get_contents('php://input');
        if ($brut === false || $brut === '') {
            return [];
        }

        $decode = json_decode($brut, true);

        return is_array($decode) ? $decode : [];
    }

    /**
     * @param array<string, mixed> $corps
     */
    private function jetonValide(array $corps): bool
    {
        if (Csrf::verify((string) ($corps['_csrf_token'] ?? ''))) {
            return true;
        }

        Response::json(['ok' => false, 'message' => 'Session expirée.'], 419);

        return false;
    }

    private static function endpointAutorise(string $endpoint): bool
    {
        $hote = parse_url($endpoint, PHP_URL_HOST);
        $schema = parse_url($endpoint, PHP_URL_SCHEME);

        if ($schema !== 'https' || !is_string($hote)) {
            return false;
        }

        $suffixes = [
            'push.services.mozilla.com',
            'fcm.googleapis.com',
            'android.googleapis.com',
            'web.push.apple.com',
            'notify.windows.com',
        ];

        foreach ($suffixes as $suffixe) {
            if ($hote === $suffixe || str_ends_with($hote, '.' . $suffixe)) {
                return true;
            }
        }

        return false;
    }

    private function garde(): bool
    {
        if ($this->acces->appareilCourant() === null || !$this->acces->estDeverrouille()) {
            Response::json(['ok' => false, 'message' => 'Application verrouillée.'], 401);
            return false;
        }

        if (!Auth::isAdmin() && !Auth::isAssistantDg() && !Auth::hasAnyRole(['dg', 'assistant_dg', 'assistante_dg'])) {
            Response::json(['ok' => false, 'message' => 'Accès refusé.'], 403);
            return false;
        }

        return true;
    }
}
