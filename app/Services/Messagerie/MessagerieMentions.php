<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

/**
 * Les mentions « @Kouakou » dans un message.
 *
 * Un membre est mentionné quand le texte contient « @ » suivi de son nom
 * complet, ou de l'un des mots de ce nom (le prénom, que l'on écrive
 * « Jean KOUAKOU » ou « KOUAKOU Jean »), sans tenir compte de la casse ni
 * des accents : « @eloise » mentionne Éloïse. Le « @ » doit ouvrir un mot
 * (une adresse électronique « x@kouakou.ci » ne mentionne personne) et le
 * nom doit s'y arrêter (« @Jeanne » ne mentionne pas Jean).
 *
 * Sert aux notifications (lot 4) : une discussion en sourdine reste
 * silencieuse, sauf pour celui qu'on y interpelle.
 */
final class MessagerieMentions
{
    /** Un mot plus court ne suffit pas à désigner quelqu'un (« @a »). */
    private const MOT_MIN = 2;

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
    ];

    public static function mentionne(string $texte, string $nom): bool
    {
        if (!str_contains($texte, '@')) {
            return false;
        }

        $texte = self::normaliser($texte);
        $nomComplet = self::normaliser($nom);
        if ($nomComplet === '') {
            return false;
        }

        $candidats = [$nomComplet];
        foreach (explode(' ', $nomComplet) as $mot) {
            if (mb_strlen($mot) >= self::MOT_MIN) {
                $candidats[] = $mot;
            }
        }

        foreach (array_unique($candidats) as $candidat) {
            $motif = '/(?<![\p{L}\p{N}_.])@' . preg_quote($candidat, '/') . '(?![\p{L}\p{N}])/u';
            if (preg_match($motif, $texte) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Minuscules, sans accents, espaces réduits : « Éloïse  KONÉ » => « eloise kone ». */
    public static function normaliser(string $texte): string
    {
        $texte = strtr(mb_strtolower($texte, 'UTF-8'), self::ACCENTS);

        return trim((string) preg_replace('/\s+/u', ' ', $texte));
    }
}
