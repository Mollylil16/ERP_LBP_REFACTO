<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use App\Repositories\Messagerie\MessagerieDepotInterface;
use Closure;
use Throwable;

/**
 * Ce qui suit l'envoi d'un message, une fois la réponse rendue à l'agent.
 *
 * NOEL peut mettre plusieurs secondes à répondre, et prévenir vingt
 * téléphones en prend une ou deux : l'agent qui écrit n'attend ni l'un ni
 * l'autre. Une seule fonction d'arrêt par requête ferme la session (sans
 * quoi le relevé de l'agent resterait bloqué derrière), rend la réponse HTTP
 * (litespeed_finish_request en production, fastcgi_finish_request
 * ailleurs), puis exécute les travaux dans l'ordre où ils ont été confiés.
 *
 * Un travail qui échoue n'empêche pas les suivants : le message est déjà
 * enregistré, et il est parti.
 */
final class SuitesEnvoi
{
    /** Le temps laissé aux suites (NOEL puis les notifications), réponse déjà rendue. */
    public const TEMPS_MAX_SECONDES = 45;

    /** @var array<int, Closure> */
    private static array $travaux = [];

    private static bool $enregistree = false;

    public static function planifier(Closure $travail): void
    {
        self::$travaux[] = $travail;

        if (!self::$enregistree) {
            self::$enregistree = true;
            register_shutdown_function([self::class, 'executer']);
        }
    }

    /** Appelée par PHP à l'arrêt de la requête. */
    public static function executer(): void
    {
        if (self::$travaux === []) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::TEMPS_MAX_SECONDES);
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        while (($travail = array_shift(self::$travaux)) !== null) {
            try {
                $travail();
            } catch (Throwable $e) {
                error_log('[Messagerie] suite d’envoi : ' . $e->getMessage());
            }
        }
    }

    /**
     * Les suites d'un message : NOEL d'abord (s'il y a un texte et que c'est
     * à lui qu'on écrit), puis les notifications du message, puis celles de
     * la réponse de NOEL, qui passe par ajouterMessage sans repasser par le
     * service.
     *
     * @param Closure(int, int, string, MessagerieDepotInterface): ?int $noel rend l'identifiant de sa réponse
     * @param Closure(int): mixed $notifier
     */
    public static function enchainer(
        int $messageId,
        int $conversationId,
        int $auteurId,
        ?string $texte,
        MessagerieDepotInterface $depot,
        Closure $noel,
        Closure $notifier,
    ): void {
        $reponse = null;
        if ($texte !== null) {
            try {
                $reponse = $noel($conversationId, $auteurId, $texte, $depot);
            } catch (Throwable $e) {
                error_log('[Messagerie] NOEL après envoi : ' . $e->getMessage());
            }
        }

        foreach ([$messageId, $reponse] as $id) {
            if (is_int($id) && $id > 0) {
                try {
                    $notifier($id);
                } catch (Throwable $e) {
                    error_log('[Messagerie] notifications du message ' . $id . ' : ' . $e->getMessage());
                }
            }
        }
    }

    /** Pour les tests : oublier les travaux en attente. */
    public static function oublier(): void
    {
        self::$travaux = [];
    }
}
