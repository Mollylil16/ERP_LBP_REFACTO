<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use RuntimeException;

/**
 * Refus métier de la messagerie, lisible tel quel par l'utilisateur.
 *
 * Le code porte le statut HTTP que le contrôleur renverra : 404 pour une
 * discussion qu'on ne peut pas lire (on ne confirme pas qu'elle existe),
 * 403 pour une action interdite, 422 pour une saisie invalide, 413 pour un
 * fichier trop lourd, 429 pour l'anti-abus, 500 quand le serveur n'a pas pu
 * ranger un fichier.
 */
final class MessagerieException extends RuntimeException
{
    public static function introuvable(string $message = 'Discussion introuvable.'): self
    {
        return new self($message, 404);
    }

    public static function interdit(string $message): self
    {
        return new self($message, 403);
    }

    public static function invalide(string $message): self
    {
        return new self($message, 422);
    }

    public static function tropDEnvois(string $message): self
    {
        return new self($message, 429);
    }

    /** Fichier au-delà de la taille permise (25 Mo). */
    public static function tropLourd(string $message): self
    {
        return new self($message, 413);
    }

    /** Le serveur n'a pas pu faire sa part (dossier, disque, extension PHP). */
    public static function serveur(string $message): self
    {
        return new self($message, 500);
    }
}
