<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * L'heure d'une agence, à partir d'une heure écrite par le serveur.
 *
 * Le serveur et la base sont à l'heure d'Abidjan (bootstrap/app.php), qui
 * est UTC+0 toute l'année : chaque date de la base — NOW(), created_at,
 * date_reception — est donc une heure d'Abidjan. L'agence de Paris, elle,
 * vit à UTC+1 en hiver et UTC+2 en été : un colis pointé à 14 h 05 à Paris
 * l'est à 12 h 05 ou 13 h 05 pour la base. company_sites.fuseau_horaire dit
 * où vit chaque agence ; les changements d'heure sont ceux de la base des
 * fuseaux de PHP, jamais un décalage écrit en dur.
 *
 * Pour l'affichage seulement : on n'écrit jamais en base une heure convertie.
 */
final class HeureAgence
{
    /** Le fuseau du serveur, de la base, et de toutes les agences de Côte d'Ivoire. */
    public const SERVEUR = 'Africa/Abidjan';

    /** Un fuseau connu, sinon celui du serveur : une valeur abîmée ne casse pas un écran. */
    public static function fuseau(mixed $fuseau): string
    {
        $fuseau = trim((string) ($fuseau ?? ''));

        return $fuseau !== '' && self::estValide($fuseau) ? $fuseau : self::SERVEUR;
    }

    public static function estValide(string $fuseau): bool
    {
        return in_array($fuseau, DateTimeZone::listIdentifiers(), true);
    }

    /** « Europe/Paris » => « Paris », « America/Port_of_Spain » => « Port of Spain ». */
    public static function ville(string $fuseau): string
    {
        $fuseau = self::fuseau($fuseau);
        $morceaux = explode('/', $fuseau);

        return str_replace('_', ' ', (string) end($morceaux));
    }

    /** Une heure du serveur, vue dans un fuseau. Null si la date est vide ou illisible. */
    public static function convertir(mixed $dateServeur, string $fuseau): ?DateTimeImmutable
    {
        $texte = trim((string) ($dateServeur ?? ''));
        if ($texte === '' || str_starts_with($texte, '0000-00-00')) {
            return null;
        }

        try {
            return (new DateTimeImmutable($texte, new DateTimeZone(self::SERVEUR)))
                ->setTimezone(new DateTimeZone(self::fuseau($fuseau)));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * L'inverse : une heure locale d'une agence (« minuit à Paris »), en
     * heure du serveur, pour borner une requête.
     */
    public static function versServeur(string $dateLocale, string $fuseau): string
    {
        return (new DateTimeImmutable($dateLocale, new DateTimeZone(self::fuseau($fuseau))))
            ->setTimezone(new DateTimeZone(self::SERVEUR))
            ->format('Y-m-d H:i:s');
    }

    /** « 23/10/2026 14 h 05 », dans le fuseau donné. « — » si la date manque. */
    public static function date(mixed $dateServeur, string $fuseau, bool $avecHeure = true): string
    {
        $date = self::convertir($dateServeur, $fuseau);
        if ($date === null) {
            return '—';
        }

        return $avecHeure ? $date->format('d/m/Y') . ' ' . self::heure($date) : $date->format('d/m/Y');
    }

    /**
     * L'heure dans le fuseau du lieu, et celle de l'autre agence entre
     * parenthèses quand elles diffèrent :
     * « 23/10/2026 14 h 05 à Paris (12 h 05 à Abidjan) ». La date de l'autre
     * côté n'est répétée que si elle change (00 h 30 à Paris, 22 h 30 la
     * veille à Abidjan).
     */
    public static function double(mixed $dateServeur, string $fuseauLieu, string $fuseauAutre): string
    {
        $fuseauLieu = self::fuseau($fuseauLieu);
        $fuseauAutre = self::fuseau($fuseauAutre);
        $ici = self::convertir($dateServeur, $fuseauLieu);
        if ($ici === null) {
            return '—';
        }

        $texte = $ici->format('d/m/Y') . ' ' . self::heure($ici);
        if ($fuseauLieu === $fuseauAutre || $ici->getOffset() === self::convertir($dateServeur, $fuseauAutre)?->getOffset()) {
            return $texte;
        }

        $autre = self::convertir($dateServeur, $fuseauAutre);
        if ($autre === null) {
            return $texte;
        }

        $jourAutre = $autre->format('Y-m-d') !== $ici->format('Y-m-d') ? $autre->format('d/m') . ' ' : '';

        return $texte . ' à ' . self::ville($fuseauLieu) . ' (' . $jourAutre . self::heure($autre) . ' à ' . self::ville($fuseauAutre) . ')';
    }

    private static function heure(DateTimeImmutable $date): string
    {
        return $date->format('H') . ' h ' . $date->format('i');
    }
}
