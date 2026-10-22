<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use App\Repositories\Messagerie\MessagerieDepotInterface;

/**
 * La jauge d'espace de la messagerie, pour l'Administration.
 *
 * Elle compte ce que les fichiers occupent réellement sur le disque : après
 * dédoublonnage (un contenu envoyé dix fois n'est compté qu'une) et après
 * réduction des photos, miniatures comprises. Les photos de groupe (256 px,
 * quelques dizaines de Ko chacune) n'y figurent pas.
 *
 * Au-delà du seuil — 20 Go par défaut —, la jauge passe en alerte : il est
 * temps de regarder ce qui pèse, avant que l'hébergement ne refuse d'écrire.
 */
final class MessagerieEspace
{
    public const SEUIL_ALERTE_OCTETS = 20 * 1024 * 1024 * 1024;

    public function __construct(
        private MessagerieDepotInterface $depot,
        private int $seuilOctets = self::SEUIL_ALERTE_OCTETS,
    ) {
    }

    /** @return array<string, mixed> */
    public function etat(): array
    {
        $espace = $this->depot->espaceFichiers();
        $octets = (int) $espace['octets'];
        $sansDedoublonnage = max($octets, (int) $espace['octets_sans_dedoublonnage']);

        return [
            'octets' => $octets,
            'lisible' => self::lisible($octets),
            'fichiers' => (int) $espace['fichiers'],
            'pieces' => (int) $espace['pieces'],
            'economie_octets' => $sansDedoublonnage - $octets,
            'economie_lisible' => self::lisible($sansDedoublonnage - $octets),
            'seuil_octets' => $this->seuilOctets,
            'seuil_lisible' => self::lisible($this->seuilOctets),
            'pourcentage' => $this->seuilOctets > 0 ? (int) floor($octets * 100 / $this->seuilOctets) : 0,
            'alerte' => $octets >= $this->seuilOctets,
            'par_mois' => array_map(static fn(array $l): array => $l + ['lisible' => self::lisible((int) $l['octets'])], $espace['par_mois']),
            'par_categorie' => array_map(static fn(array $l): array => $l + ['lisible' => self::lisible((int) $l['octets'])], $espace['par_categorie']),
        ];
    }

    /** « 1,4 Go », « 820 Ko » : en unités de 1 024, comme l'explorateur Windows. */
    public static function lisible(int $octets): string
    {
        $unites = ['octets', 'Ko', 'Mo', 'Go', 'To'];
        $valeur = (float) max(0, $octets);
        $i = 0;
        while ($valeur >= 1024 && $i < count($unites) - 1) {
            $valeur /= 1024;
            $i++;
        }

        return $i === 0
            ? $octets . ' octets'
            : number_format($valeur, $valeur < 10 ? 1 : 0, ',', ' ') . ' ' . $unites[$i];
    }
}
