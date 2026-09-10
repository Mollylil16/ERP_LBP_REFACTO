<?php

declare(strict_types=1);

namespace App\Controllers\Mobile;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Mobile\MobileDeviceRepository;
use App\Services\Auth\AuthService;
use App\Services\Mobile\MobileAuthService;
use App\View\Components\MobileDirection;

/**
 * Accès à l'application de direction : première connexion, création du code,
 * déverrouillage quotidien et appairage des appareils.
 *
 * Le directeur utilise plusieurs appareils (téléphone iOS, téléphone Android,
 * tablette) : chacun est appairé séparément, avec son propre jeton et son propre
 * code. Perdre l'un n'oblige donc pas à réappairer les autres.
 */
final class MobileAccesController extends BaseController
{
    private MobileAuthService $acces;

    public function __construct()
    {
        $this->acces = new MobileAuthService(new MobileDeviceRepository(Database::getConnection()));
    }

    /**
     * Point d'entrée unique : oriente selon l'état de l'appareil.
     */
    public function entree(): void
    {
        $appareil = $this->acces->appareilCourant();

        if ($appareil === null) {
            $this->redirect('/mobile/connexion');
            return;
        }

        if ($this->acces->estDeverrouille()) {
            $this->redirect('/mobile/tableau-de-bord');
            return;
        }

        $this->redirect('/mobile/verrouillage');
    }

    // -----------------------------------------------------------------
    // Première connexion
    // -----------------------------------------------------------------

    public function connexion(): void
    {
        // Un appareil déjà appairé n'a plus à saisir de mot de passe.
        if ($this->acces->appareilCourant() !== null) {
            $this->redirect('/mobile/verrouillage');
            return;
        }

        $this->rendre(MobileDirection::pageConnexion(Session::getFlash('error')));
    }

    public function connexionValider(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/mobile/connexion');
            return;
        }

        $resultat = (new AuthService(new UserRepository(Database::getConnection())))->login($_POST);

        if (!$resultat['success']) {
            Session::flash('error', (string) $resultat['message']);
            $this->redirect('/mobile/connexion');
            return;
        }

        $utilisateur = $resultat['user'];

        // L'application est réservée à la direction : inutile d'appairer un compte
        // qui n'y aurait accès à aucun écran.
        Session::set('auth_user_id', $utilisateur->id);
        if (!Auth::isAdmin() && !Auth::hasAnyRole(['dg', 'assistant_dg', 'assistante_dg'])) {
            Session::forget('auth_user_id');
            Session::flash('error', 'Ce compte n\'a pas accès à l\'application de direction.');
            $this->redirect('/mobile/connexion');
            return;
        }

        // Le compte est validé mais l'appareil n'est pas encore appairé : on referme
        // la session le temps que le code soit choisi.
        Session::forget('auth_user_id');
        Session::set('mobile_pairing_user', $utilisateur->id);
        $this->redirect('/mobile/creer-code');
    }

    // -----------------------------------------------------------------
    // Création du code
    // -----------------------------------------------------------------

    public function creerCode(): void
    {
        $userId = Session::get('mobile_pairing_user');
        if ($userId === null) {
            $this->redirect('/mobile/connexion');
            return;
        }

        $this->rendre(MobileDirection::pageCreerCode(Session::getFlash('error')));
    }

    public function creerCodeValider(): void
    {
        $userId = Session::get('mobile_pairing_user');
        if ($userId === null) {
            $this->redirect('/mobile/connexion');
            return;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/mobile/creer-code');
            return;
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

        if (!MobileAuthService::pinValide($code)) {
            Session::flash('error', 'Le code doit comporter exactement six chiffres.');
            $this->redirect('/mobile/creer-code');
            return;
        }

        if (!hash_equals($code, $confirmation)) {
            Session::flash('error', 'Les deux codes saisis ne correspondent pas.');
            $this->redirect('/mobile/creer-code');
            return;
        }

        if (MobileAuthService::pinTropFaible($code)) {
            Session::flash('error', 'Ce code est trop facile à deviner. Évitez les suites et les chiffres répétés.');
            $this->redirect('/mobile/creer-code');
            return;
        }

        $this->acces->appairer((int) $userId, $code, $this->deviner());
        Session::forget('mobile_pairing_user');

        // L'appareil est appairé : on ouvre directement la session, le code vient
        // d'être saisi deux fois, le redemander n'apporterait rien.
        $appareil = $this->acces->appareilCourant();
        if ($appareil !== null) {
            $this->acces->deverrouiller($appareil, $code);
        }

        $this->redirect('/mobile/installation');
    }

    // -----------------------------------------------------------------
    // Déverrouillage quotidien
    // -----------------------------------------------------------------

    public function verrouillage(): void
    {
        $appareil = $this->acces->appareilCourant();
        if ($appareil === null) {
            $this->redirect('/mobile/connexion');
            return;
        }

        if ($this->acces->estDeverrouille()) {
            $this->redirect('/mobile/tableau-de-bord');
            return;
        }

        $this->rendre(MobileDirection::pageVerrouillage(
            (string) ($appareil['full_name'] ?? 'Direction'),
            $this->acces->secondesDeBlocage($appareil),
            Session::getFlash('error')
        ));
    }

    public function deverrouiller(): void
    {
        $appareil = $this->acces->appareilCourant();
        if ($appareil === null) {
            $this->redirect('/mobile/connexion');
            return;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/mobile/verrouillage');
            return;
        }

        $resultat = $this->acces->deverrouiller($appareil, trim((string) ($_POST['code'] ?? '')));

        if (!$resultat['ok']) {
            Session::flash('error', $resultat['message']);
            $this->redirect('/mobile/verrouillage');
            return;
        }

        $this->redirect('/mobile/tableau-de-bord');
    }

    public function verrouiller(): void
    {
        if (Csrf::verify($_POST['_csrf_token'] ?? null)) {
            $this->acces->verrouiller();
        }

        $this->redirect('/mobile/verrouillage');
    }

    public function oublierAppareil(): void
    {
        if (Csrf::verify($_POST['_csrf_token'] ?? null)) {
            $this->acces->oublierAppareil();
        }

        $this->redirect('/mobile/connexion');
    }

    // -----------------------------------------------------------------
    // Installation sur l'écran d'accueil
    // -----------------------------------------------------------------

    public function installation(): void
    {
        if (!$this->acces->estDeverrouille()) {
            $this->redirect('/mobile');
            return;
        }

        $this->rendre(MobileDirection::pageInstallation($this->deviner()));
    }

    // -----------------------------------------------------------------
    // Outils
    // -----------------------------------------------------------------

    /**
     * Devine la plateforme depuis l'agent utilisateur, pour n'afficher que les
     * instructions d'installation qui concernent réellement l'appareil en main.
     */
    private function deviner(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (preg_match('/iPad/i', $ua) || (preg_match('/Macintosh/i', $ua) && preg_match('/Mobile|Touch/i', $ua))) {
            return 'ipad';
        }
        if (preg_match('/iPhone|iPod/i', $ua)) {
            return 'iphone';
        }
        if (preg_match('/Android/i', $ua)) {
            return preg_match('/Mobile/i', $ua) ? 'android' : 'android-tablette';
        }

        return 'autre';
    }

    private function rendre(string $contenu): void
    {
        echo $contenu;
    }
}
