<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Controllers\Messagerie\MessagerieNotificationsController;
use App\Controllers\Mobile\MobileAppController;
use App\View\Components\Messagerie;
use Tests\TestCase;

/**
 * Notifications de la messagerie (lot 4) côté écran : le bandeau, les
 * réglages, la coque installable, le service worker et le JavaScript.
 */
final class MessagerieNotificationsVueTest extends TestCase
{
    public function test_le_bandeau_est_rendu_masque_dans_l_ecran_et_le_panneau(): void
    {
        foreach (['plein', 'panneau'] as $mode) {
            $html = Messagerie::application($mode);

            self::assertStringContainsString('data-msg-notif-bandeau hidden', $html, $mode);
            self::assertStringContainsString('data-msg-notif-action="activer"', $html, $mode);
            self::assertStringContainsString('Activer les notifications', $html, $mode);
            self::assertStringContainsString('data-msg-notif-action="plus-tard"', $html, $mode);
            self::assertStringContainsString('data-msg-notif-variante="ios" hidden', $html, $mode);
            self::assertStringContainsString('Sur l’écran d’accueil', $html, $mode);
        }
    }

    public function test_les_reglages_portent_l_interrupteur_l_essai_et_les_explications(): void
    {
        $html = Messagerie::ecran();

        self::assertStringContainsString('Notifications sur cet appareil', $html);
        self::assertStringContainsString('data-msg-notif-interrupteur disabled', $html);
        self::assertStringContainsString('data-msg-notif-action="essai" disabled', $html);
        self::assertStringContainsString('Envoyer une notification d’essai', $html);
        foreach (['active', 'refuse', 'ios', 'ios-ancien', 'non-supporte', 'non-securise'] as $etat) {
            self::assertStringContainsString('data-msg-notif-message="' . $etat . '" hidden', $html, $etat);
        }
        self::assertStringContainsString('cadenas à gauche de l’adresse', $html);
        // L'interrupteur n'est pas une préférence du serveur : le JavaScript
        // des préférences ne doit pas l'enregistrer.
        self::assertStringNotContainsString('data-msg-preference="notifications', $html);
    }

    public function test_aucun_emoji_dans_les_textes_des_notifications(): void
    {
        $html = Messagerie::ecran();

        self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }

    public function test_le_socle_donne_le_service_worker_et_sa_portee(): void
    {
        $html = Messagerie::socle([
            'api' => '/messagerie/api', 'csrf' => 'j', 'moi' => 1, 'moi_nom' => 'Awa',
            'connexion' => '/login', 'plein' => '/messagerie', 'direction' => false,
        ]);

        self::assertMatchesRegularExpression('#data-notif-sw="[^"]*/messagerie/sw\.js"#', $html);
        self::assertMatchesRegularExpression('#data-notif-portee="[^"]*/messagerie/"#', $html);
    }

    public function test_l_en_tete_rend_le_portail_installable(): void
    {
        $html = Messagerie::enTete();

        self::assertMatchesRegularExpression('#<link rel="manifest" href="[^"]*/messagerie/manifest\.webmanifest">#', $html);
        self::assertStringContainsString('<meta name="apple-mobile-web-app-capable" content="yes">', $html);
        self::assertStringContainsString('rel="apple-touch-icon"', $html);
    }

    public function test_les_gabarits_chargent_les_notifications_seulement_en_session(): void
    {
        foreach (['module.php', 'app.php'] as $gabarit) {
            $source = (string) file_get_contents(BASE_PATH . '/views/layouts/' . $gabarit);

            $tete = (int) strpos($source, 'Messagerie::enTete()');
            self::assertGreaterThan(0, $tete, $gabarit);
            $condition = strrpos(substr($source, 0, $tete), '$messagerie !== null');
            self::assertNotFalse($condition, $gabarit . ' : le manifeste n’est rendu qu’en session.');
            self::assertLessThan(strpos($source, '</head>'), $tete, $gabarit);

            self::assertLessThan(
                strpos($source, 'js/messagerie-notifications.js'),
                strpos($source, 'js/messagerie.js'),
                $gabarit . ' : les notifications après l’interface'
            );
        }
    }

    public function test_le_manifeste_ouvre_la_messagerie_en_plein_ecran(): void
    {
        ob_start();
        (new MessagerieNotificationsController())->manifeste();
        $manifeste = json_decode((string) ob_get_clean(), true);

        self::assertSame('standalone', $manifeste['display']);
        self::assertStringEndsWith('/messagerie', $manifeste['start_url']);
        self::assertSame(['192x192', '512x512'], array_values(array_unique(array_column($manifeste['icons'], 'sizes'))));
        foreach ($manifeste['icons'] as $icone) {
            $chemin = BASE_PATH . '/public/assets/' . preg_replace(['#^.*?/assets/#', '#\?.*$#'], '', $icone['src']);
            self::assertFileExists($chemin);
        }
    }

    public function test_le_service_worker_recoit_les_push_et_ouvre_la_bonne_discussion(): void
    {
        ob_start();
        (new MessagerieNotificationsController())->serviceWorker();
        $script = (string) ob_get_clean();

        self::assertStringContainsString("addEventListener('push'", $script);
        self::assertStringContainsString("addEventListener('notificationclick'", $script);
        // Se taire quand le portail est déjà sous les yeux, et seulement pour un message.
        self::assertStringContainsString("d.type === 'messagerie'", $script);
        self::assertStringContainsString('includeUncontrolled: true', $script);
        self::assertStringContainsString('c.focused && c.visibilityState', $script);
        // Même charge que /mobile/sw.js.
        foreach (['d.titre', 'd.corps', 'd.url', 'd.tag', 'd.urgent'] as $champ) {
            self::assertStringContainsString($champ, $script, $champ);
        }
        self::assertStringContainsString("type: 'lbp-messagerie-ouvrir'", $script);
        self::assertStringContainsString('self.clients.openWindow(', $script);
        // Aucune interception des pages : il ne contrôle rien.
        self::assertStringNotContainsString("addEventListener('fetch'", $script);
        self::assertStringNotContainsString('__RACINE__', $script);
        self::assertStringNotContainsString('innerHTML', $script);
    }

    public function test_le_service_worker_de_la_direction_garde_son_comportement(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Mobile/MobileAppController.php');

        self::assertStringContainsString("self.addEventListener('fetch'", $source);
        self::assertStringContainsString("tag: d.tag || 'lbp-direction'", $source);
        self::assertStringContainsString("if (d.type !== 'messagerie') {\n    e.waitUntil(afficher());", str_replace("\r\n", "\n", $source));
        self::assertTrue(method_exists(MobileAppController::class, 'serviceWorker'));
    }

    public function test_les_routes_d_abonnement_sont_sous_la_messagerie_et_celles_du_mobile_inchangees(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/messagerie.php');
        foreach (['get(\'/notifications/cle-publique\'', 'post(\'/notifications/etat\'', 'post(\'/notifications/abonner\'',
            'post(\'/notifications/desabonner\'', 'post(\'/notifications/test\'', 'get(\'/sw.js\'', 'get(\'/manifest.webmanifest\''] as $route) {
            self::assertStringContainsString($route, $routes, $route);
        }

        // Les routes de la Direction gardent leur garde (appareil + rôle).
        $mobile = (string) file_get_contents(BASE_PATH . '/app/Controllers/Mobile/MobilePushController.php');
        self::assertStringContainsString("!Auth::isAdmin() && !Auth::isAssistantDg() && !Auth::hasAnyRole(['dg', 'assistant_dg', 'assistante_dg'])", $mobile);

        // Toute écriture de la messagerie passe par le jeton CSRF.
        $controleur = (string) file_get_contents(BASE_PATH . '/app/Controllers/Messagerie/MessagerieNotificationsController.php');
        self::assertSame(4, substr_count($controleur, '$this->corpsProtege();'));
        self::assertStringContainsString('->enregistrer($userId, null,', $controleur, 'Un navigateur n’a pas d’appareil appairé.');
    }

    public function test_le_javascript_demande_l_autorisation_dans_un_clic_et_n_injecte_pas_de_html(): void
    {
        $js = (string) file_get_contents(BASE_PATH . '/public/assets/js/messagerie-notifications.js');

        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringNotContainsString('insertAdjacentHTML', $js);
        self::assertSame(1, substr_count($js, 'Notification.requestPermission('));
        $activer = substr($js, (int) strpos($js, 'function activer()'), 900);
        self::assertStringContainsString('Notification.requestPermission(', $activer);
        self::assertStringContainsString('userVisibleOnly: true', $js);
        self::assertStringContainsString('"/notifications/abonner"', $js);
        self::assertStringContainsString('"/notifications/desabonner"', $js);
        self::assertStringContainsString('"/notifications/test"', $js);
        self::assertStringContainsString('"/notifications/etat"', $js);
        self::assertStringContainsString('_csrf_token: CFG.csrf', $js);
        // Pas de relevé à lui : seul messagerie-releve.js interroge le serveur en boucle.
        self::assertStringNotContainsString('setInterval', $js);
        self::assertStringNotContainsString('/nouveautes', $js);
    }
}
