<?php

declare(strict_types=1);

namespace App\Controllers\Messagerie;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Models\Database;
use App\Repositories\Mobile\PushSubscriptionRepository;
use App\Services\Mobile\NotificationsPush;
use App\Services\Mobile\WebPushService;
use Throwable;

/**
 * Notifications de la messagerie (lot 4) : abonnement d'un navigateur, envoi
 * d'essai, et la coque qui les rend possibles (service worker, manifeste).
 *
 * Tout utilisateur actif s'abonne, sur autant d'appareils qu'il veut : le
 * socle est celui de l'application de la Direction (lbp_push_subscriptions,
 * clés VAPID de lbp_mobile_settings), mais sans appareil appairé (device_id
 * NULL) et sans toucher aux règles des routes /mobile/push/*.
 *
 * Le service worker et le manifeste sont servis par PHP, comme ceux de
 * /mobile : leur portée et leurs adresses dépendent du dossier où l'ERP est
 * installé. Ils ne demandent pas de session (le navigateur les charge sans
 * toujours envoyer le cookie) et ne contiennent rien de personnel.
 */
final class MessagerieNotificationsController extends BaseController
{
    /** Longueurs de lbp_push_subscriptions. */
    private const ENDPOINT_MAX = 600;
    private const CLE_MAX = 255;

    // =================================================================
    // Coque : service worker et manifeste
    // =================================================================

    /**
     * Le service worker du portail, de portée /messagerie/ : il ne contrôle
     * aucune page (il n'intercepte rien), il reçoit les notifications et
     * ouvre la bonne discussion. Il ne gêne pas celui de /mobile/, dont la
     * portée est distincte.
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . View::url('messagerie/'));
        header('X-Content-Type-Options: nosniff');

        echo strtr(self::SCRIPT_SERVICE_WORKER, [
            '__RACINE__' => self::js(View::url('messagerie')),
            '__MOBILE__' => self::js(View::url('mobile/')),
            '__ICONE__' => self::js(View::asset('images/mobile/icone-192.png')),
            '__BADGE__' => self::js(View::asset('images/mobile/icone-maskable-192.png')),
        ]);
    }

    /**
     * Rend le portail installable (écran d'accueil d'un téléphone) : sur
     * iPhone, c'est la condition pour recevoir des notifications (iOS 16.4+).
     */
    public function manifeste(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo json_encode([
            'id' => View::url('messagerie'),
            'name' => 'LBP Messagerie',
            'short_name' => 'LBP Messagerie',
            'description' => 'La messagerie interne et le portail de La Belle Porte Transit.',
            'start_url' => View::url('messagerie'),
            'scope' => View::url(''),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => '#0f766e',
            'lang' => 'fr',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => View::asset('images/mobile/icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => View::asset('images/mobile/icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => View::asset('images/mobile/icone-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => View::asset('images/mobile/icone-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // =================================================================
    // API (session active ; POST protégés par le jeton CSRF)
    // =================================================================

    public function clePublique(): void
    {
        $this->utilisateur();

        $cle = (new WebPushService(Database::getConnection()))->clePubliqueVapid();
        if ($cle === null) {
            $this->json(['ok' => false, 'message' => 'Les notifications ne sont pas disponibles sur le serveur pour le moment.'], 503);
        }

        $this->json(['ok' => true, 'cle' => $cle]);
    }

    /**
     * Ce navigateur est-il abonné, et pour qui ? Un POST plutôt qu'un GET :
     * l'adresse d'abonnement ne doit pas finir dans les journaux du serveur.
     */
    public function etat(): void
    {
        $userId = $this->utilisateur();
        $d = $this->corpsProtege();
        $endpoint = trim((string) ($d['endpoint'] ?? ''));
        $depot = new PushSubscriptionRepository(Database::getConnection());

        $ligne = $endpoint !== '' && strlen($endpoint) <= self::ENDPOINT_MAX ? $depot->parEndpoint($endpoint) : null;

        $this->json([
            'ok' => true,
            'abonne' => $ligne !== null && (int) $ligne['user_id'] === $userId,
            // Abonné pour un autre compte : un ordinateur partagé. L'écran
            // retire alors l'abonnement, pour ne pas afficher ici les
            // messages de quelqu'un d'autre.
            'autre' => $ligne !== null && (int) $ligne['user_id'] !== $userId,
            'appareils' => count($depot->pourUtilisateur($userId)),
        ]);
    }

    public function abonner(): void
    {
        $userId = $this->utilisateur();
        $d = $this->corpsProtege();

        $endpoint = trim((string) ($d['endpoint'] ?? ''));
        $p256dh = trim((string) ($d['p256dh'] ?? ''));
        $auth = trim((string) ($d['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === ''
            || strlen($endpoint) > self::ENDPOINT_MAX || strlen($p256dh) > self::CLE_MAX || strlen($auth) > self::CLE_MAX) {
            $this->json(['ok' => false, 'message' => 'Abonnement incomplet.'], 422);
        }

        if (!WebPushService::endpointAutorise($endpoint)) {
            $this->json(['ok' => false, 'message' => 'Service de notification non reconnu.'], 422);
        }

        try {
            (new PushSubscriptionRepository(Database::getConnection()))->enregistrer($userId, null, $endpoint, $p256dh, $auth);
        } catch (Throwable $e) {
            error_log('[Messagerie notifications] abonnement : ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Enregistrement impossible. Réessayez dans un instant.'], 500);
        }

        $this->json(['ok' => true]);
    }

    public function desabonner(): void
    {
        $userId = $this->utilisateur();
        $d = $this->corpsProtege();
        $endpoint = trim((string) ($d['endpoint'] ?? ''));

        if ($endpoint !== '' && strlen($endpoint) <= self::ENDPOINT_MAX) {
            (new PushSubscriptionRepository(Database::getConnection()))->supprimerPourUtilisateur($endpoint, $userId);
        }

        $this->json(['ok' => true]);
    }

    /** Une notification d'essai vers ce navigateur seulement. */
    public function test(): void
    {
        $userId = $this->utilisateur();
        $d = $this->corpsProtege();
        $endpoint = trim((string) ($d['endpoint'] ?? ''));
        $pdo = Database::getConnection();

        $abonnement = $endpoint !== '' && strlen($endpoint) <= self::ENDPOINT_MAX
            ? (new PushSubscriptionRepository($pdo))->parEndpoint($endpoint)
            : null;
        if ($abonnement === null || (int) $abonnement['user_id'] !== $userId) {
            $this->json(['ok' => false, 'message' => 'Cet appareil n’est pas abonné. Activez d’abord les notifications.'], 404);
        }

        $parti = NotificationsPush::creer($pdo)->envoyerVers($abonnement, [
            'type' => 'essai',
            'titre' => 'Messagerie LBP',
            'corps' => 'Les notifications fonctionnent sur cet appareil.',
            'url' => View::url('messagerie'),
            'tag' => 'msg-essai',
        ]);

        if (!$parti) {
            $this->json(['ok' => false, 'message' => 'Le service de notification du navigateur a refusé l’envoi. Désactivez puis réactivez les notifications.'], 502);
        }

        $this->json(['ok' => true]);
    }

    // =================================================================
    // Outils
    // =================================================================

    /** Session valide et compte actif, sinon 401 en JSON (même règle que la messagerie). */
    private function utilisateur(): int
    {
        $user = Auth::check() ? Auth::user() : null;

        if ($user === null || $user->status !== 'active' || $user->id === null) {
            if ($user !== null) {
                Session::forget('auth_user_id');
            }
            $this->json(['ok' => false, 'message' => 'Votre session a expiré. Reconnectez-vous.'], 401);
        }

        return (int) $user->id;
    }

    /**
     * Corps JSON d'un POST, après vérification du jeton CSRF (clé
     * _csrf_token du corps, ou en-tête X-CSRF-TOKEN).
     *
     * @return array<string, mixed>
     */
    private function corpsProtege(): array
    {
        $corps = json_decode((string) file_get_contents('php://input'), true);
        $corps = is_array($corps) ? $corps : [];

        $jeton = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $corps['_csrf_token'] ?? null;
        if (!Csrf::verify(is_string($jeton) ? $jeton : null)) {
            $this->json(['ok' => false, 'message' => 'La page a expiré. Rechargez-la puis recommencez.'], 419);
        }

        return $corps;
    }

    /** @param array<string, mixed> $donnees */
    private function json(array $donnees, int $statut = 200): never
    {
        header('Cache-Control: no-store');
        Response::json($donnees, $statut);
        exit;
    }

    private static function js(string $valeur): string
    {
        return (string) json_encode($valeur, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Le service worker. La charge des notifications est celle de
     * /mobile/sw.js (titre, corps, url, tag, urgent) ; type « messagerie »
     * dit qu'il faut se taire quand le portail est déjà sous les yeux de
     * l'utilisateur (une fenêtre visible et active : l'écran et son son s'en
     * chargent).
     */
    private const SCRIPT_SERVICE_WORKER = <<<'JS'
/* Service worker de la messagerie LBP (lot 4) : notifications seulement. */
const RACINE = __RACINE__;
const MOBILE = __MOBILE__;
const ICONE = __ICONE__;
const BADGE = __BADGE__;

/* Safari (Mac, iPhone) retire l'abonnement d'un site qui reçoit des
   notifications sans rien afficher : là, on affiche toujours, puis on
   retire aussitôt. Une application installée sur iPhone n'a pas toujours
   « Safari » dans son identifiant : on reconnaît le moteur. */
const SAFARI = /AppleWebKit\//.test(self.navigator.userAgent)
  && !/Chrome|Chromium|Android|Edg\//.test(self.navigator.userAgent);

self.addEventListener('install', () => { self.skipWaiting(); });
self.addEventListener('activate', (e) => { e.waitUntil(self.clients.claim()); });

/* Les fenêtres du portail, ouvertes ou non par ce service worker,
   sauf l'application de la Direction. */
function fenetresDuPortail() {
  return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((liste) => liste.filter((c) => {
    try { return new URL(c.url).pathname.indexOf(MOBILE) !== 0; } catch (err) { return false; }
  }));
}

self.addEventListener('push', (e) => {
  let d = { titre: 'Messagerie LBP', corps: '', url: RACINE };
  try {
    if (e.data) { d = Object.assign(d, e.data.json()); }
  } catch (err) {
    try { d.corps = e.data.text(); } catch (err2) { /* charge illisible : titre seul */ }
  }

  const tag = typeof d.tag === 'string' && d.tag !== '' ? d.tag : 'lbp-messagerie';
  const options = {
    body: typeof d.corps === 'string' ? d.corps : '',
    icon: ICONE,
    badge: BADGE,
    tag: tag,
    renotify: true,
    requireInteraction: d.urgent === true,
    timestamp: Date.now(),
    data: { url: typeof d.url === 'string' && d.url !== '' ? d.url : RACINE }
  };
  const titre = typeof d.titre === 'string' && d.titre !== '' ? d.titre : 'Messagerie LBP';

  const regarde = d.type === 'messagerie'
    ? fenetresDuPortail().then((liste) => liste.some((c) => c.focused && c.visibilityState === 'visible'))
    : Promise.resolve(false);

  e.waitUntil(regarde.then((oui) => {
    if (!oui) { return self.registration.showNotification(titre, options); }
    if (!SAFARI) { return undefined; }
    return self.registration.showNotification(titre, Object.assign({}, options, { silent: true, renotify: false }))
      .then(() => self.registration.getNotifications({ tag: tag }))
      .then((affichees) => { affichees.forEach((n) => n.close()); });
  }));
});

/* Un clic ouvre la discussion au bon message : dans l'onglet de la
   messagerie s'il y en a un (la page change d'ancre sans se recharger),
   sinon dans un nouvel onglet — une autre page du portail peut porter un
   formulaire en cours, on ne la quitte pas à la place de l'agent. */
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const brute = (e.notification.data && e.notification.data.url) || RACINE;
  let cible;
  try { cible = new URL(brute, self.location.origin); } catch (err) { cible = new URL(RACINE, self.location.origin); }
  if (cible.origin !== self.location.origin) { cible = new URL(RACINE, self.location.origin); }

  e.waitUntil(fenetresDuPortail().then((liste) => {
    const onglet = liste.find((c) => {
      try { return new URL(c.url).pathname.replace(/\/$/, '') === cible.pathname.replace(/\/$/, ''); } catch (err) { return false; }
    });
    if (!onglet) { return self.clients.openWindow(cible.href); }

    return onglet.focus().then((fenetre) => new Promise((resolu) => {
      const canal = new MessageChannel();
      const delai = setTimeout(() => { resolu(false); }, 800);
      canal.port1.onmessage = () => { clearTimeout(delai); resolu(true); };
      (fenetre || onglet).postMessage({ type: 'lbp-messagerie-ouvrir', url: cible.href }, [canal.port2]);
    })).then((compris) => (compris ? undefined : self.clients.openWindow(cible.href)))
      .catch(() => self.clients.openWindow(cible.href));
  }));
});
JS;
}
