<?php

declare(strict_types=1);

namespace App\View\Pages\Error;

final class ErrorPage
{
    /**
     * @param array<int,array{label:string,href:string,variant:string}> $actions
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $eyebrow,
        public readonly string $title,
        public readonly string $message,
        public readonly string $detail,
        public readonly string $explanation,
        public readonly array $suggestions,
        public readonly string $tone,
        public readonly string $symbol,
        public readonly array $actions,
    ) {
    }

    public static function notFound(string $requestedPath = ''): self
    {
        $detail = $requestedPath !== '' && $requestedPath !== '/'
            ? 'Adresse demandée : ' . $requestedPath
            : 'Vérifiez l’adresse ou revenez vers un espace connu.';

        return new self(
            404,
            'Navigation interrompue',
            'Cette page reste introuvable.',
            'Elle a peut-être été déplacée, renommée ou n’est plus accessible depuis cette adresse.',
            $detail,
            'Le serveur fonctionne correctement, mais aucune page ne correspond à l’adresse demandée.',
            [
                'Vérifiez l’orthographe de l’adresse.',
                'Revenez au portail puis ouvrez à nouveau le module.',
            ],
            'not-found',
            'compass',
            [
                ['label' => 'Retour à l’accueil', 'href' => '/', 'variant' => 'primary'],
                ['label' => 'Choisir un module', 'href' => 'selection_portail', 'variant' => 'secondary'],
            ],
        );
    }

    public static function maintenance(string $moduleSlug, string $reason = ''): self
    {
        $module = self::moduleLabel($moduleSlug);
        $reason = trim($reason);

        return new self(
            503,
            'Maintenance temporaire',
            $module . ' fait une courte pause.',
            $reason !== '' ? $reason : 'Une intervention technique est en cours sur ce module.',
            'Les autres espaces restent disponibles. Vous pourrez réessayer dès la fin de l’intervention.',
            'L’accès est volontairement suspendu afin d’éviter toute opération pendant l’intervention.',
            [
                'Choisissez un autre module depuis le portail.',
                'Réessayez un peu plus tard : aucune action de votre part n’est nécessaire.',
            ],
            'maintenance',
            'tools',
            [
                ['label' => 'Choisir un autre module', 'href' => 'selection_portail', 'variant' => 'primary'],
                ['label' => 'Retour à l’accueil', 'href' => '/', 'variant' => 'secondary'],
            ],
        );
    }

    public static function forStatus(int $statusCode, string $detail = ''): self
    {
        $definitions = [
            400 => ['Requête incorrecte', 'La demande envoyée ne peut pas être comprise.', 'Certaines informations transmises sont absentes ou mal formées.', 'request', 'document'],
            401 => ['Connexion nécessaire', 'Votre identité doit être vérifiée.', 'Cette page est réservée aux utilisateurs connectés.', 'auth', 'lock'],
            403 => ['Accès non autorisé', 'Vous n’avez pas les droits nécessaires.', 'Votre compte est reconnu, mais il ne possède pas l’autorisation requise pour cette action.', 'forbidden', 'shield'],
            408 => ['Délai dépassé', 'La demande a pris trop de temps.', 'Le serveur a cessé d’attendre la fin de la requête.', 'timeout', 'clock'],
            419 => ['Session expirée', 'Votre session de sécurité n’est plus valide.', 'Le formulaire est resté ouvert trop longtemps ou son jeton de sécurité a expiré.', 'auth', 'clock'],
            422 => ['Informations à corriger', 'La demande contient des données non valides.', 'Le serveur a compris la demande, mais certaines valeurs doivent être corrigées.', 'request', 'document'],
            429 => ['Trop de demandes', 'Le service vous demande de patienter.', 'Plusieurs requêtes ont été envoyées dans un court intervalle afin de protéger l’application.', 'timeout', 'clock'],
            500 => ['Incident interne', 'Une difficulté inattendue est survenue.', 'Le problème vient de l’application et non de votre saisie. L’équipe technique peut intervenir.', 'server', 'server'],
            502 => ['Service intermédiaire indisponible', 'Un service nécessaire ne répond pas correctement.', 'L’application attend une réponse d’un autre service qui est temporairement indisponible.', 'server', 'server'],
            503 => ['Service temporairement indisponible', 'L’application ne peut pas répondre pour le moment.', 'Une maintenance ou une surcharge temporaire empêche l’accès au service.', 'maintenance', 'tools'],
            504 => ['Réponse trop lente', 'Un service distant n’a pas répondu à temps.', 'L’application fonctionne, mais une dépendance externe tarde à répondre.', 'timeout', 'clock'],
        ];

        if ($statusCode === 404) {
            return self::notFound($detail);
        }

        [$title, $message, $explanation, $tone, $symbol] = $definitions[$statusCode] ?? [
            'Un imprévu est survenu',
            'La demande n’a pas pu être terminée normalement.',
            'Le code retourné permet à l’équipe technique d’identifier la nature du problème.',
            'server',
            'warning',
        ];

        return new self(
            $statusCode,
            'Erreur ' . $statusCode,
            $title,
            $message,
            $detail !== '' ? $detail : 'Vous pouvez revenir à un espace sûr et réessayer.',
            $explanation,
            self::suggestionsFor($statusCode),
            $tone,
            $symbol,
            [
                ['label' => $statusCode === 401 || $statusCode === 419 ? 'Se reconnecter' : 'Retour à l’accueil', 'href' => $statusCode === 401 || $statusCode === 419 ? 'login' : '/', 'variant' => 'primary'],
                ['label' => 'Choisir un module', 'href' => 'selection_portail', 'variant' => 'secondary'],
            ],
        );
    }

    /** @return array<int,string> */
    private static function suggestionsFor(int $statusCode): array
    {
        return match ($statusCode) {
            401, 419 => ['Reconnectez-vous puis reprenez votre action.', 'Évitez de renvoyer directement un ancien formulaire.'],
            403 => ['Vérifiez que vous utilisez le bon compte.', 'Contactez un administrateur si cet accès est nécessaire.'],
            408, 429, 502, 503, 504 => ['Patientez quelques instants avant de réessayer.', 'Si le problème persiste, signalez le code affiché à l’assistance.'],
            400, 422 => ['Revenez au formulaire et vérifiez les champs.', 'Ne rechargez pas plusieurs fois la même opération.'],
            default => ['Réessayez une seule fois depuis le portail.', 'Si le problème persiste, transmettez le code affiché à l’équipe technique.'],
        };
    }

    private static function moduleLabel(string $slug): string
    {
        $labels = [
            'rh' => 'Ressources humaines',
            'admin' => 'Administration',
            'employee' => 'Espace employé',
            'espace-employe' => 'Espace employé',
            'site-admin' => 'Site Internet',
            'site' => 'Site Internet',
        ];

        return $labels[$slug] ?? ucfirst(str_replace(['-', '_'], ' ', $slug));
    }
}
