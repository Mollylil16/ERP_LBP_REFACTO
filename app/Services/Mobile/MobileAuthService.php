<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Helpers\Session;
use App\Repositories\Mobile\MobileDeviceRepository;

/**
 * Verrouillage de l'application de direction par code PIN.
 *
 * Le modèle de sécurité repose sur deux secrets distincts, dont aucun ne suffit seul :
 *  - un jeton d'appareil, déposé dans un cookie de longue durée et stocké haché ;
 *  - un code PIN à six chiffres, haché comme un mot de passe.
 *
 * Voler le téléphone déverrouillé ne donne donc pas accès à l'ERP sans le code, et
 * connaître le code ne sert à rien depuis un autre appareil. Le déverrouillage ouvre
 * une session PHP ordinaire : tous les contrôles d'habilitation existants s'appliquent
 * ensuite sans modification.
 */
final class MobileAuthService
{
    public const COOKIE_APPAREIL = 'lbp_dir_device';
    private const DUREE_COOKIE = 90 * 24 * 3600;

    /** Nombre d'essais avant blocage temporaire. */
    private const ESSAIS_AVANT_BLOCAGE = 5;

    /** Durée de session inactive au-delà de laquelle le PIN est redemandé. */
    private const INACTIVITE_MAX = 900;

    private const CLE_SESSION_APPAREIL = 'mobile_device_id';
    private const CLE_SESSION_ACTIVITE = 'mobile_last_activity';

    public function __construct(private MobileDeviceRepository $appareils) {}

    // -----------------------------------------------------------------
    // État
    // -----------------------------------------------------------------

    /**
     * Appareil reconnu par son cookie, ou null si ce téléphone n'est pas appairé.
     *
     * @return array<string, mixed>|null
     */
    public function appareilCourant(): ?array
    {
        $jeton = $_COOKIE[self::COOKIE_APPAREIL] ?? null;
        if (!is_string($jeton) || $jeton === '') {
            return null;
        }

        $appareil = $this->appareils->trouverParJeton($jeton);
        if ($appareil === null) {
            return null;
        }

        // Un compte désactivé ne doit plus pouvoir déverrouiller, même appairé.
        if (($appareil['status'] ?? 'active') !== 'active') {
            return null;
        }

        return $appareil;
    }

    /**
     * L'application est-elle déverrouillée pour la requête en cours ?
     * Une inactivité prolongée redemande le code, même si la session vit encore.
     */
    public function estDeverrouille(): bool
    {
        if (Session::get('auth_user_id') === null) {
            return false;
        }
        if (Session::get(self::CLE_SESSION_APPAREIL) === null) {
            return false;
        }

        $derniere = (int) (Session::get(self::CLE_SESSION_ACTIVITE) ?? 0);
        if ($derniere > 0 && (time() - $derniere) > self::INACTIVITE_MAX) {
            $this->verrouiller();
            return false;
        }

        Session::set(self::CLE_SESSION_ACTIVITE, time());

        return true;
    }

    /**
     * Secondes restantes avant expiration du blocage, 0 si l'appareil n'est pas bloqué.
     *
     * @param array<string, mixed> $appareil
     */
    public function secondesDeBlocage(array $appareil): int
    {
        $bloque = $appareil['locked_until'] ?? null;
        if ($bloque === null) {
            return 0;
        }

        $reste = strtotime((string) $bloque) - time();

        return $reste > 0 ? $reste : 0;
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    /**
     * Appaire le téléphone après une connexion classique par mot de passe.
     * Retourne le jeton, déposé aussitôt dans un cookie.
     */
    public function appairer(int $userId, string $pin, ?string $libelle): string
    {
        $jeton = $this->appareils->appairer(
            $userId,
            $pin,
            $libelle,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        $this->deposerCookie($jeton);

        return $jeton;
    }

    /**
     * Vérifie le code PIN et ouvre la session applicative.
     *
     * @param array<string, mixed> $appareil
     * @return array{ok: bool, message: string, bloque: int}
     */
    public function deverrouiller(array $appareil, string $pin): array
    {
        $reste = $this->secondesDeBlocage($appareil);
        if ($reste > 0) {
            return [
                'ok' => false,
                'bloque' => $reste,
                'message' => 'Trop de tentatives. Réessayez dans ' . ceil($reste / 60) . ' minute(s).',
            ];
        }

        if (!self::pinValide($pin) || !password_verify($pin, (string) $appareil['pin_hash'])) {
            $tentatives = ((int) $appareil['failed_attempts']) + 1;
            $bloqueJusqua = null;
            $secondes = 0;

            if ($tentatives >= self::ESSAIS_AVANT_BLOCAGE) {
                // Blocage progressif : 5 min, puis 15, puis 60 au-delà.
                $paliers = [5, 15, 60];
                $index = min((int) floor($tentatives / self::ESSAIS_AVANT_BLOCAGE) - 1, count($paliers) - 1);
                $secondes = $paliers[max(0, $index)] * 60;
                $bloqueJusqua = date('Y-m-d H:i:s', time() + $secondes);
            }

            $this->appareils->enregistrerEchec((int) $appareil['id'], $tentatives, $bloqueJusqua);

            $restants = self::ESSAIS_AVANT_BLOCAGE - ($tentatives % self::ESSAIS_AVANT_BLOCAGE);

            return [
                'ok' => false,
                'bloque' => $secondes,
                'message' => $bloqueJusqua !== null
                    ? 'Trop de tentatives. Application bloquée ' . ($secondes / 60) . ' minute(s).'
                    : 'Code incorrect. ' . $restants . ' essai(s) avant blocage.',
            ];
        }

        $this->appareils->enregistrerSucces((int) $appareil['id']);
        $this->ouvrirSession((int) $appareil['user_id'], (int) $appareil['id']);

        return ['ok' => true, 'bloque' => 0, 'message' => 'Déverrouillé.'];
    }

    /**
     * Referme l'application sans désappairer le téléphone : le code PIN suffira
     * à rouvrir, le mot de passe ne sera pas redemandé.
     */
    public function verrouiller(): void
    {
        Session::forget('auth_user_id');
        Session::forget(self::CLE_SESSION_APPAREIL);
        Session::forget(self::CLE_SESSION_ACTIVITE);
    }

    /**
     * Désappaire complètement ce téléphone : le cookie est effacé et l'appareil révoqué.
     */
    public function oublierAppareil(): void
    {
        $appareil = $this->appareilCourant();
        if ($appareil !== null) {
            $this->appareils->revoquer((int) $appareil['id']);
        }

        $this->verrouiller();
        $this->supprimerCookie();
    }

    public function changerPin(int $deviceId, string $pin): void
    {
        $this->appareils->changerPin($deviceId, $pin);
    }

    // -----------------------------------------------------------------
    // Outils
    // -----------------------------------------------------------------

    /**
     * Un PIN doit faire exactement six chiffres, sans suite triviale ni chiffre unique.
     */
    public static function pinValide(string $pin): bool
    {
        return (bool) preg_match('/^\d{6}$/', $pin);
    }

    /**
     * Codes refusés à la création : trop devinables si le téléphone est perdu.
     */
    public static function pinTropFaible(string $pin): bool
    {
        if (preg_match('/^(\d)\1{5}$/', $pin)) {
            return true;
        }

        $suites = ['012345', '123456', '234567', '345678', '456789', '567890'];
        if (in_array($pin, $suites, true)) {
            return true;
        }

        return in_array($pin, [strrev('012345'), strrev('123456'), strrev('234567'), strrev('345678'), strrev('456789'), strrev('567890')], true);
    }

    private function ouvrirSession(int $userId, int $deviceId): void
    {
        // Régénérer l'identifiant de session à l'ouverture évite qu'un identifiant
        // capté avant déverrouillage reste valable après. L'opération pose un cookie :
        // elle est impossible une fois les en-têtes envoyés, auquel cas la session
        // reste ouverte plutôt que d'émettre un avertissement au milieu de la page.
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        Session::set('auth_user_id', $userId);
        Session::set(self::CLE_SESSION_APPAREIL, $deviceId);
        Session::set(self::CLE_SESSION_ACTIVITE, time());
    }

    private function deposerCookie(string $jeton): void
    {
        setcookie(self::COOKIE_APPAREIL, $jeton, [
            'expires' => time() + self::DUREE_COOKIE,
            'path' => '/',
            'secure' => self::connexionSecurisee(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function supprimerCookie(): void
    {
        setcookie(self::COOKIE_APPAREIL, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::connexionSecurisee(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function connexionSecurisee(): bool
    {
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
            return true;
        }

        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
