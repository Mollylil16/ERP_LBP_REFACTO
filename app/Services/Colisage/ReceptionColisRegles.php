<?php

declare(strict_types=1);

namespace App\Services\Colisage;

/**
 * Les trois choix de l'agence d'arrivée pour chaque colis, validés en
 * octobre 2026 : « Reçu », « Reçu endommagé », « Non reçu ». Un colis jamais
 * pointé est « en attente » ; 24 heures après l'arrivée du départ, il devient
 * manquant (règle du 14/09/2026, inchangée).
 *
 * « Non reçu », dit explicitement par l'agent, rend le colis manquant tout de
 * suite, sans attendre les 24 heures : l'agence d'envoi n'a pas à attendre le
 * lendemain pour chercher un colis que Paris dit ne pas avoir.
 *
 * Toute erreur se corrige : on repasse un colis de « non reçu » à « reçu »,
 * ou à « en attente ». Chaque changement est tracé (état avant, état après,
 * commentaire, auteur, heure) dans lbp_colis_pointages.
 *
 * Le commentaire est facultatif, sauf pour « Reçu endommagé » : sans un mot
 * sur le dommage (« carton ouvert », « liquide renversé »), l'agence d'envoi
 * ne peut ni prévenir le client, ni se retourner contre le transporteur, et
 * le colis est déjà loin quand on veut savoir. Un dommage est rare : le
 * taper ne ralentit pas le pointage des neuf cent quatre-vingts autres.
 *
 * Cette classe ne lit ni n'écrit rien : elle décide.
 */
final class ReceptionColisRegles
{
    public const RECU = 'RECU';
    public const ENDOMMAGE = 'ENDOMMAGE';
    public const NON_RECU = 'NON_RECU';
    /** Jamais pointé, ou pointage défait. N'est jamais écrit en base : l'état y est NULL. */
    public const EN_ATTENTE = 'EN_ATTENTE';

    public const COMMENTAIRE_MAX = 500;
    public const COMMENTAIRE_DOMMAGE_MIN = 3;

    /** @var array<string, string> */
    public const LIBELLES = [
        self::RECU => 'Reçu',
        self::ENDOMMAGE => 'Reçu endommagé',
        self::NON_RECU => 'Non reçu',
        self::EN_ATTENTE => 'En attente',
    ];

    /** Action inscrite au journal pour chaque état d'arrivée. */
    private const ACTIONS = [
        self::RECU => 'RECU',
        self::ENDOMMAGE => 'ENDOMMAGE',
        self::NON_RECU => 'NON_RECU',
        self::EN_ATTENTE => 'ANNULE_RECU',
    ];

    public static function estConnu(string $etat): bool
    {
        return isset(self::LIBELLES[$etat]);
    }

    /** L'état reçu de la physique : le colis est en main, abîmé ou non. */
    public static function estEnMain(string $etat): bool
    {
        return $etat === self::RECU || $etat === self::ENDOMMAGE;
    }

    /**
     * L'état d'un colis à partir de ses colonnes. Un colis pointé avant les
     * trois choix (date_reception posée, état NULL) est « Reçu ».
     *
     * @param array<string, mixed> $colis etat_reception, date_reception
     */
    public static function etatDe(array $colis): string
    {
        $etat = strtoupper(trim((string) ($colis['etat_reception'] ?? '')));

        if (self::estConnu($etat) && $etat !== self::EN_ATTENTE) {
            return $etat;
        }

        return !empty($colis['date_reception']) ? self::RECU : self::EN_ATTENTE;
    }

    /** Le commentaire tel qu'il sera enregistré : nettoyé, borné, null s'il est vide. */
    public static function commentaire(mixed $texte): ?string
    {
        $texte = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($texte ?? ''))));

        if ($texte === '') {
            return null;
        }

        return mb_substr($texte, 0, self::COMMENTAIRE_MAX);
    }

    /**
     * Ce que devient un colis quand on lui donne un nouvel état.
     *
     * Le même état avec un autre commentaire est un changement (il est
     * tracé) ; le même état avec le même commentaire n'en est pas un.
     *
     * @return array{ok:bool, change:bool, message:string, action:string, avant:string, apres:string, commentaire:?string}
     */
    public static function transition(string $avant, string $voulu, mixed $commentaire, mixed $commentaireAvant = null): array
    {
        $avant = self::estConnu($avant) ? $avant : self::EN_ATTENTE;
        $voulu = strtoupper(trim($voulu));
        $commentaire = self::commentaire($commentaire);

        $resultat = [
            'ok' => false, 'change' => false, 'message' => '', 'action' => '',
            'avant' => $avant, 'apres' => $voulu, 'commentaire' => $commentaire,
        ];

        if (!self::estConnu($voulu)) {
            return ['message' => 'Choix inconnu : Reçu, Reçu endommagé ou Non reçu.'] + $resultat;
        }

        if ($voulu === self::ENDOMMAGE && mb_strlen((string) $commentaire) < self::COMMENTAIRE_DOMMAGE_MIN) {
            return ['message' => 'Décrivez le dommage en quelques mots : l’agence d’envoi en a besoin pour prévenir le client.'] + $resultat;
        }

        // Défaire un pointage efface son commentaire : il ne décrivait plus rien.
        if ($voulu === self::EN_ATTENTE) {
            $commentaire = null;
        }

        $resultat['commentaire'] = $commentaire;
        $resultat['action'] = self::ACTIONS[$voulu];
        $resultat['ok'] = true;
        $resultat['change'] = $avant !== $voulu || $commentaire !== self::commentaire($commentaireAvant);
        $resultat['message'] = $resultat['change'] ? '' : 'Rien à changer : le colis était déjà dans cet état.';

        return $resultat;
    }
}
