<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Session;

/**
 * Combien de temps une connexion reste ouverte.
 *
 * Règle validée le 23/10/2026 : la session se ferme après 2 heures sans
 * aucune activité réelle, et au bout de 12 heures au plus. Avant, seule la
 * durée de vie des fichiers de session PHP (24 minutes) en décidait, et la
 * messagerie, qui interroge le serveur toutes les 3 secondes, l'aurait rendue
 * éternelle : un portail oublié sur un poste d'agence partagé serait resté
 * connecté toute la nuit.
 *
 * Les relevés automatiques (RELEVES_PASSIFS) sont servis normalement mais ne
 * comptent pas comme une activité. Le contrôle tourne une fois par requête,
 * depuis bootstrap/app.php, avant tout contrôleur : tous les écrans voient la
 * même session, qu'ils passent par AuthMiddleware ou par Auth::check().
 */
final class DureeSession
{
    public const INACTIVITE_MAX = 2 * 3600;
    public const DUREE_MAX = 12 * 3600;

    /** Fins de chemin des appels que le navigateur fait seul. */
    public const RELEVES_PASSIFS = [
        '/messagerie/api/nouveautes',
    ];

    private const CLE_OUVERTURE = 'session_ouverte_at';
    private const CLE_ACTIVITE = 'session_activite_at';

    /**
     * À appeler au moment où un utilisateur entre (mot de passe ou code PIN).
     * Le nouvel identifiant de session empêche qu'un identifiant connu avant la
     * connexion serve après elle.
     */
    public static function ouvrir(bool $regenerer = true, ?int $maintenant = null): void
    {
        if ($regenerer && session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $maintenant ??= time();
        Session::set(self::CLE_OUVERTURE, $maintenant);
        Session::set(self::CLE_ACTIVITE, $maintenant);
    }

    /**
     * Ferme la connexion si l'une des deux limites est dépassée, sinon note
     * l'activité (sauf pour un relevé automatique).
     *
     * @return bool false quand la connexion vient d'être fermée
     */
    public static function controler(string $chemin, ?int $maintenant = null): bool
    {
        if (!Session::has('auth_user_id')) {
            return true;
        }

        $maintenant ??= time();

        // Une session ouverte avant la mise en place de la règle n'a pas ces
        // dates : on la fait partir de maintenant plutôt que de la couper.
        $ouverture = (int) (Session::get(self::CLE_OUVERTURE) ?? 0);
        $activite = (int) (Session::get(self::CLE_ACTIVITE) ?? 0);
        if ($ouverture <= 0 || $activite <= 0) {
            self::ouvrir(false, $maintenant);
            return true;
        }

        if ($maintenant - $activite > self::INACTIVITE_MAX) {
            self::fermer('Vous avez été déconnecté après 2 heures sans activité. Reconnectez-vous.');
            return false;
        }

        if ($maintenant - $ouverture > self::DUREE_MAX) {
            self::fermer('Votre connexion a dépassé 12 heures. Reconnectez-vous.');
            return false;
        }

        if (!self::estReleve($chemin)) {
            Session::set(self::CLE_ACTIVITE, $maintenant);
        }

        return true;
    }

    public static function estReleve(string $chemin): bool
    {
        $chemin = rtrim($chemin, '/');
        foreach (self::RELEVES_PASSIFS as $fin) {
            if (str_ends_with($chemin, $fin)) {
                return true;
            }
        }

        return false;
    }

    private static function fermer(string $message): void
    {
        Session::forget('auth_user_id');
        Session::forget(self::CLE_OUVERTURE);
        Session::forget(self::CLE_ACTIVITE);
        Session::flash('error', $message);
    }
}
