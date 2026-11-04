<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * L'enigme du matin se joue, elle ne se lit pas.
 *
 * Donner la reponse tout de suite, c'est tuer le seul moment de la journee ou
 * l'on cherche pour le plaisir. Demande de l'administrateur le 22/10/2026 : on
 * laisse repondre, on felicite si c'est juste, et au bout de trois essais on
 * donne la solution AVEC le pourquoi — parce qu'une reponse sans explication
 * laisse la personne aussi bete qu'avant, et un peu vexee en plus.
 *
 * Le compteur d'essais vit dans la session, pas en base : savoir qui a seche
 * sur l'enigme de mercredi ne regarde personne, et surement pas la direction.
 */
final class NoelEnigme
{
    public const JUSTE = 'juste';
    public const ENCORE = 'encore';
    public const DONNEE = 'donnee';

    public const ESSAIS_AUTORISES = 3;

    /**
     * Juger une reponse.
     *
     * @param array{reponse: string, pourquoi: string, acceptees: array<int, string>} $enigme
     *
     * @return array{verdict: string, message: string, essais: int, reponse: ?string, pourquoi: ?string}
     */
    public static function juger(string $proposition, array $enigme, int $essaisDeja): array
    {
        $essais = $essaisDeja + 1;

        /*
         * « Je ne sais pas » n'est pas une mauvaise reponse, c'est un abandon.
         * Le compter comme un essai rate (« Pas tout a fait ») fait croire a la
         * personne qu'elle a propose quelque chose : on lui donne la solution
         * tout de suite, avec son pourquoi. Constate le 04/11/2026.
         */
        if (self::abandonne($proposition) && !self::correspond($proposition, $enigme)) {
            return [
                'verdict' => self::DONNEE,
                'message' => "Pas de souci, je vous la donne : " . rtrim($enigme['reponse'], '.') . '.',
                'essais' => $essais,
                'reponse' => $enigme['reponse'],
                'pourquoi' => $enigme['pourquoi'],
            ];
        }

        if (self::correspond($proposition, $enigme)) {
            return [
                'verdict' => self::JUSTE,
                'message' => self::felicitation($essais),
                'essais' => $essais,
                'reponse' => $enigme['reponse'],
                'pourquoi' => $enigme['pourquoi'],
            ];
        }

        if ($essais >= self::ESSAIS_AUTORISES) {
            return [
                'verdict' => self::DONNEE,
                'message' => "C'était : " . rtrim($enigme['reponse'], '.') . '.',
                'essais' => $essais,
                'reponse' => $enigme['reponse'],
                'pourquoi' => $enigme['pourquoi'],
            ];
        }

        return [
            'verdict' => self::ENCORE,
            'message' => self::relance(self::ESSAIS_AUTORISES - $essais),
            'essais' => $essais,
            'reponse' => null,
            'pourquoi' => null,
        ];
    }

    /** La personne renonce-t-elle plutot que de proposer une reponse ? */
    public static function abandonne(string $proposition): bool
    {
        $texte = mb_strtolower(trim($proposition));
        $texte = strtr($texte, ['’' => "'", 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c']);
        $texte = (string) preg_replace('/[^a-z\' ]+/', ' ', $texte);
        $texte = trim((string) preg_replace('/\s+/', ' ', $texte));

        $abandons = [
            'je ne sais pas', 'je sais pas', 'sais pas', 'je ne sais', 'chais pas', 'jsp',
            "je n'en sais rien", "j'en sais rien", 'je ne sais rien', 'aucune idee', 'pas d\'idee', 'aucun idee',
            "j'abandonne", 'abandonne', 'je donne ma langue au chat', 'ma langue au chat',
            'donne la reponse', 'dis moi', 'dis-moi', 'la reponse', 'je seche', 'je trouve pas', 'je ne trouve pas',
            'je ne connais pas', 'je connais pas', 'aucune', 'rien', 'joker',
        ];

        foreach ($abandons as $abandon) {
            if ($texte === $abandon || str_starts_with($texte, $abandon . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * La reponse tapee vaut-elle la bonne ?
     *
     * On tape « trou », « un trou », « LE TROU », « un trous ». Exiger la
     * formulation exacte ferait perdre quelqu'un qui avait trouve — et c'est
     * la facon la plus sure de ne plus jamais le faire jouer.
     *
     * @param array{reponse: string, acceptees: array<int, string>} $enigme
     */
    public static function correspond(string $proposition, array $enigme): bool
    {
        $tapee = self::nettoyer($proposition);

        if ($tapee === '') {
            return false;
        }

        $valables = array_merge([$enigme['reponse']], $enigme['acceptees']);

        foreach ($valables as $valable) {
            $attendue = self::nettoyer($valable);

            if ($attendue === '') {
                continue;
            }

            if ($tapee === $attendue) {
                return true;
            }

            // Une faute de frappe sur un mot long ne doit pas coûter la
            // victoire : « tirroir caisse » vaut « tiroir caisse ».
            if (mb_strlen($attendue) >= 5 && levenshtein($tapee, $attendue) <= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retirer ce qui ne change pas le sens : accents, articles, pluriels.
     */
    private static function nettoyer(string $texte): string
    {
        $texte = NoelCarte::sansAccent($texte);
        $texte = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $texte);
        $texte = trim((string) preg_replace('/\s+/', ' ', $texte));

        // Les articles en tete : « un trou » et « trou » sont la meme reponse.
        $texte = (string) preg_replace('/^(le|la|les|un|une|des|l|du|de)\s+/', '', $texte);

        // Le pluriel de confort : « les secrets » vaut « le secret ».
        return (string) preg_replace('/s$/', '', $texte);
    }

    private static function felicitation(int $essais): string
    {
        if ($essais === 1) {
            return 'Du premier coup. Chapeau.';
        }

        if ($essais === 2) {
            return "C'est ça. Il a fallu deux essais, mais c'est trouvé.";
        }

        return "Juste au dernier essai. Le plus beau des moments.";
    }

    private static function relance(int $restants): string
    {
        if ($restants === 2) {
            return "Pas tout à fait. Il te reste deux essais.";
        }

        return "Pas encore. Un dernier essai, prends ton temps.";
    }
}
