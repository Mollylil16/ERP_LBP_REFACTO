<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Session;

/**
 * Mot de passe provisoire, à changer dès la première connexion.
 *
 * Jusqu'au 23/10/2026, « Réinitialiser le mot de passe » remettait tout
 * compte sur lbp2026 — le mot de passe écrit dans un document resté
 * téléchargeable par tout le monde jusqu'au 22/10. Désormais l'administrateur
 * reçoit un mot de passe tiré au hasard, propre à ce compte, qu'il transmet en
 * privé ; et tant que l'agent ne l'a pas remplacé par le sien, le logiciel ne
 * lui ouvre que l'écran qui sert à le faire.
 *
 * La consigne vit en base (users.doit_changer_mdp) et, une fois l'agent
 * connecté, dans sa session : le contrôle de chaque requête ne coûte rien.
 */
final class MotDePasseProvisoire
{
    public const COLONNE = 'doit_changer_mdp';
    public const CLE_SESSION = 'doit_changer_mdp';
    public const LONGUEUR_MIN = 10;

    /** Mots de passe connus de trop de monde pour être acceptés. */
    private const INTERDITS = ['lbp2026', 'admin', 'password', 'motdepasse', 'azerty123', '1234567890'];

    /** Sans 0/O, 1/l/I : il doit pouvoir se dicter au téléphone. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Fins de chemin ouvertes pendant que le changement est exigé. */
    private const CHEMINS_LIBRES = ['/compte/mot-de-passe', '/logout', '/login'];

    /** Trois groupes de quatre signes, par exemple « Kx7m-Qp4r-Tz9w ». */
    public static function generer(): string
    {
        $groupes = [];
        $max = strlen(self::ALPHABET) - 1;
        for ($g = 0; $g < 3; $g++) {
            $groupe = '';
            for ($i = 0; $i < 4; $i++) {
                $groupe .= self::ALPHABET[random_int(0, $max)];
            }
            $groupes[] = $groupe;
        }

        return implode('-', $groupes);
    }

    /**
     * Le message d'erreur à afficher, ou null si le nouveau mot de passe
     * convient.
     */
    public static function refus(string $nouveau, string $confirmation, string $actuel, string $email): ?string
    {
        if ($nouveau !== $confirmation) {
            return 'Le nouveau mot de passe et sa confirmation ne sont pas identiques.';
        }
        if (mb_strlen($nouveau) < self::LONGUEUR_MIN) {
            return 'Choisissez un mot de passe d’au moins ' . self::LONGUEUR_MIN . ' caractères.';
        }
        if ($nouveau === $actuel) {
            return 'Le nouveau mot de passe doit être différent du mot de passe provisoire.';
        }
        $minuscule = mb_strtolower($nouveau);
        if (in_array($minuscule, self::INTERDITS, true)) {
            return 'Ce mot de passe est trop connu. Choisissez-en un autre.';
        }
        $identifiant = mb_strtolower((string) strstr($email, '@', true));
        if ($identifiant !== '' && str_contains($minuscule, $identifiant)) {
            return 'Le mot de passe ne doit pas reprendre votre adresse e-mail.';
        }

        return null;
    }

    public static function estExige(): bool
    {
        return Session::has('auth_user_id') && (bool) Session::get(self::CLE_SESSION, false);
    }

    public static function exiger(bool $exige): void
    {
        if ($exige) {
            Session::set(self::CLE_SESSION, true);
        } else {
            Session::forget(self::CLE_SESSION);
        }
    }

    public static function estLibre(string $chemin): bool
    {
        $chemin = rtrim($chemin, '/');
        foreach (self::CHEMINS_LIBRES as $fin) {
            if (str_ends_with($chemin, $fin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Appelé une fois par requête depuis bootstrap/app.php : tant que le
     * changement est exigé, toute autre adresse renvoie vers l'écran prévu
     * (ou répond 403 en JSON pour les appels faits par les écrans).
     */
    public static function controler(string $chemin): void
    {
        if (!self::estExige() || self::estLibre($chemin)) {
            return;
        }

        if (str_contains($chemin, '/api/')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Changez d’abord votre mot de passe provisoire.']);
            exit;
        }

        $config = require BASE_PATH . '/config/app.php';
        header('Location: ' . rtrim((string) $config['url'], '/') . '/compte/mot-de-passe');
        exit;
    }
}
