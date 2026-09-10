<?php

declare(strict_types=1);

namespace App\Controllers\Mobile;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Models\Database;
use App\Repositories\Mobile\MobileDeviceRepository;
use App\Repositories\PilotageDg\PilotageDgDashboardRepository;
use App\Repositories\Rh\RhValidationRepository;
use App\Services\Mobile\MobileAuthService;
use App\View\Components\MobileDirection;
use App\View\Components\MobileDirectionEcrans;
use RuntimeException;
use Throwable;

/**
 * Écrans de l'application de direction et coque PWA.
 *
 * Les données proviennent des dépôts existants : aucune règle métier n'est
 * réécrite ici, l'application mobile est une autre fenêtre sur le même ERP.
 */
final class MobileAppController extends BaseController
{
    private MobileAuthService $acces;
    private MobileDeviceRepository $appareils;

    public function __construct()
    {
        $this->appareils = new MobileDeviceRepository(Database::getConnection());
        $this->acces = new MobileAuthService($this->appareils);
    }

    // =================================================================
    // Coque PWA
    // =================================================================

    /**
     * Manifeste servi par PHP : l'URL de démarrage et la portée dépendent du
     * répertoire d'installation de l'ERP, inconnu à l'écriture d'un fichier statique.
     */
    public function manifeste(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo json_encode([
            'name' => 'LBP Direction',
            'short_name' => 'LBP Direction',
            'description' => 'Pilotage de La Belle Porte Transit : activité, validations, personnel et anomalies.',
            'start_url' => View::url('mobile/tableau-de-bord'),
            'scope' => View::url('mobile/'),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#1d2b57',
            'theme_color' => '#1d2b57',
            'lang' => 'fr',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => View::asset('images/mobile/icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => View::asset('images/mobile/icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => View::asset('images/mobile/icone-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => View::asset('images/mobile/icone-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Validations',
                    'url' => View::url('mobile/validations'),
                    'icons' => [['src' => View::asset('images/mobile/icone-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Anomalies',
                    'url' => View::url('mobile/anomalies'),
                    'icons' => [['src' => View::asset('images/mobile/icone-192.png'), 'sizes' => '192x192']],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Service worker : reste actif en arrière-plan pour recevoir les notifications
     * et servir une page de repli quand le réseau manque.
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        // Sans cet en-tête, un service worker servi depuis /mobile/ ne pourrait pas
        // contrôler cette portée sur certains serveurs.
        header('Service-Worker-Allowed: ' . View::url('mobile/'));

        $version = 'lbp-direction-v1';
        $horsLigne = View::url('mobile/hors-ligne');
        $icone = View::asset('images/mobile/icone-192.png');
        $racine = View::url('mobile/tableau-de-bord');

        echo <<<JS
const CACHE = '{$version}';
const HORS_LIGNE = '{$horsLigne}';

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([HORS_LIGNE, '{$icone}'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((noms) => Promise.all(noms.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

// Navigation : le réseau d'abord, la page de repli si la connexion manque.
// Les données de pilotage ne doivent jamais être servies périmées depuis un cache.
self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') { return; }
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match(HORS_LIGNE)));
    return;
  }
  if (e.request.destination === 'image' || e.request.destination === 'style') {
    e.respondWith(
      caches.match(e.request).then((r) => r || fetch(e.request).then((rep) => {
        const copie = rep.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copie));
        return rep;
      }).catch(() => r))
    );
  }
});

self.addEventListener('push', (e) => {
  let d = { titre: 'LBP Direction', corps: '', url: '{$racine}' };
  try { if (e.data) { d = Object.assign(d, e.data.json()); } } catch (err) {}

  e.waitUntil(self.registration.showNotification(d.titre, {
    body: d.corps,
    icon: '{$icone}',
    badge: '{$icone}',
    tag: d.tag || 'lbp-direction',
    renotify: true,
    requireInteraction: d.urgent === true,
    vibrate: [90, 50, 90],
    data: { url: d.url || '{$racine}' }
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const cible = (e.notification.data && e.notification.data.url) || '{$racine}';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((liste) => {
      for (const c of liste) {
        if ('focus' in c) { c.navigate(cible); return c.focus(); }
      }
      return self.clients.openWindow(cible);
    })
  );
});
JS;
    }

    public function horsLigne(): void
    {
        echo MobileDirectionEcrans::pageHorsLigne();
    }

    // =================================================================
    // Écrans
    // =================================================================

    public function tableauDeBord(): void
    {
        if (!$this->garde()) {
            return;
        }

        $depot = new PilotageDgDashboardRepository(Database::getConnection());
        $donnees = $depot->dashboard();
        $validations = $depot->pendingValidations();

        echo MobileDirectionEcrans::pageTableauDeBord($donnees, (int) ($validations['totalCount'] ?? 0));
    }

    public function validations(): void
    {
        if (!$this->garde()) {
            return;
        }

        $depot = new PilotageDgDashboardRepository(Database::getConnection());
        $attente = $depot->pendingValidations();

        echo MobileDirectionEcrans::pageValidations(
            $attente['workflows'],
            $attente['legalRequests'],
            $attente['paymentRequests'],
            Session::getFlash('success'),
            Session::getFlash('error')
        );
    }

    public function personnel(): void
    {
        if (!$this->garde()) {
            return;
        }

        $depot = new PilotageDgDashboardRepository(Database::getConnection());
        $supervision = $depot->personnelSupervision();

        echo MobileDirectionEcrans::pagePersonnel(
            $supervision['employees'],
            $supervision['alerts'],
            $supervision['topHonnetes'] ?? []
        );
    }

    public function anomalies(): void
    {
        if (!$this->garde()) {
            return;
        }

        $depot = new PilotageDgDashboardRepository(Database::getConnection());
        $anomalies = $depot->anomalies();

        echo MobileDirectionEcrans::pageAnomalies($anomalies['signalements'] ?? []);
    }

    public function reglages(): void
    {
        if (!$this->garde()) {
            return;
        }

        echo MobileDirectionEcrans::pageReglages(
            (string) (Auth::user()?->fullName ?? 'Direction'),
            $this->appareils->listerPourUtilisateur((int) Auth::id()),
            Session::getFlash('success')
        );
    }

    // =================================================================
    // Décisions
    // =================================================================

    public function deciderWorkflow(string $id): void
    {
        $this->decider(
            fn(RhValidationRepository $depot, int $recordId, string $decision) => $depot->decideWorkflow($recordId, $decision, (int) Auth::id()),
            $id,
            'Workflow mis à jour.'
        );
    }

    public function deciderDemande(string $id): void
    {
        $commentaire = trim((string) ($_POST['comment'] ?? ''));

        $this->decider(
            fn(RhValidationRepository $depot, int $recordId, string $decision) => $depot->decideEmployeeRequest($recordId, $decision, (int) Auth::id(), $commentaire !== '' ? $commentaire : null),
            $id,
            'Demande mise à jour.'
        );
    }

    private function decider(callable $action, string $id, string $succes): void
    {
        if (!$this->garde()) {
            return;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/mobile/validations');
            return;
        }

        $decision = (string) ($_POST['decision'] ?? '');
        if ($decision !== 'approve' && $decision !== 'reject') {
            Session::flash('error', 'Décision invalide.');
            $this->redirect('/mobile/validations');
            return;
        }

        try {
            $action(new RhValidationRepository(Database::getConnection()), (int) $id, $decision);
            Session::flash('success', $succes);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'La décision n\'a pas pu être enregistrée.');
        }

        $this->redirect('/mobile/validations');
    }

    // =================================================================
    // Garde d'accès
    // =================================================================

    /**
     * Vérifie que l'appareil est appairé, déverrouillé, et que le compte a bien
     * les habilitations de direction. Redirige sinon.
     */
    private function garde(): bool
    {
        if ($this->acces->appareilCourant() === null) {
            $this->redirect('/mobile/connexion');
            return false;
        }

        if (!$this->acces->estDeverrouille()) {
            $this->redirect('/mobile/verrouillage');
            return false;
        }

        if (!Auth::isAdmin() && !Auth::isAssistantDg() && !Auth::hasAnyRole(['dg', 'assistant_dg', 'assistante_dg'])) {
            $this->acces->verrouiller();
            Session::flash('error', 'Ce compte n\'a pas accès à l\'application de direction.');
            $this->redirect('/mobile/connexion');
            return false;
        }

        return true;
    }
}
