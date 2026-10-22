<?php

declare(strict_types=1);

/*
 * Démarre la session PHP avec un cookie protégé.
 *
 * En production le site répond aussi sur http:// ; le cookie était alors posé
 * sans l'attribut « secure » et le mot de passe circulait en clair. Le
 * .htaccess de production renvoie désormais tout vers https://, et le cookie
 * n'est plus envoyé qu'en https dès que la page y est servie. En local (WAMP,
 * http) le cookie reste utilisable : « secure » n'y est pas posé.
 */
if (session_status() === PHP_SESSION_NONE) {
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    if ($https) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}
