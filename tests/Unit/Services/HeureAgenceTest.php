<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\HeureAgence;
use Tests\TestCase;

/**
 * Les heures de la base sont celles d'Abidjan (UTC+0 toute l'année) ; Paris
 * est à UTC+1 en hiver, UTC+2 en été. Les changements d'heure de 2026 :
 * 29 mars à 1 h UTC, 25 octobre à 1 h UTC.
 */
final class HeureAgenceTest extends TestCase
{
    public function test_paris_en_ete_a_deux_heures_d_avance(): void
    {
        self::assertSame('23/10/2026 14 h 05', HeureAgence::date('2026-10-23 12:05:00', 'Europe/Paris'));
    }

    public function test_paris_en_hiver_a_une_heure_d_avance(): void
    {
        self::assertSame('26/10/2026 13 h 05', HeureAgence::date('2026-10-26 12:05:00', 'Europe/Paris'));
        self::assertSame('15/01/2026 09 h 30', HeureAgence::date('2026-01-15 08:30:00', 'Europe/Paris'));
    }

    public function test_la_nuit_du_changement_d_heure(): void
    {
        // 25/10/2026 : à 1 h UTC, Paris passe de 3 h à 2 h.
        self::assertSame('25/10/2026 02 h 59', HeureAgence::date('2026-10-25 00:59:00', 'Europe/Paris'));
        self::assertSame('25/10/2026 02 h 00', HeureAgence::date('2026-10-25 01:00:00', 'Europe/Paris'));
        // 29/03/2026 : à 1 h UTC, Paris passe de 2 h à 3 h.
        self::assertSame('29/03/2026 03 h 00', HeureAgence::date('2026-03-29 01:00:00', 'Europe/Paris'));
    }

    public function test_les_deux_heures_quand_les_agences_different(): void
    {
        self::assertSame('23/10/2026 14 h 05 à Paris (12 h 05 à Abidjan)', HeureAgence::double('2026-10-23 12:05:00', 'Europe/Paris', 'Africa/Abidjan'));
        self::assertSame('23/10/2026 12 h 05 à Abidjan (14 h 05 à Paris)', HeureAgence::double('2026-10-23 12:05:00', 'Africa/Abidjan', 'Europe/Paris'));
        // Minuit passé à Paris, pas encore à Abidjan : la date de l'autre côté est rappelée.
        self::assertSame('24/10/2026 00 h 30 à Paris (23/10 22 h 30 à Abidjan)', HeureAgence::double('2026-10-23 22:30:00', 'Europe/Paris', 'Africa/Abidjan'));
    }

    public function test_une_seule_heure_entre_agences_du_meme_fuseau(): void
    {
        self::assertSame('23/10/2026 12 h 05', HeureAgence::double('2026-10-23 12:05:00', 'Africa/Abidjan', 'Africa/Abidjan'));
        // Dakar et Abidjan : même heure, toute l'année.
        self::assertSame('23/10/2026 12 h 05', HeureAgence::double('2026-10-23 12:05:00', 'Africa/Dakar', 'Africa/Abidjan'));
    }

    public function test_minuit_a_paris_en_heure_du_serveur(): void
    {
        self::assertSame('2026-10-22 22:00:00', HeureAgence::versServeur('2026-10-23 00:00:00', 'Europe/Paris'));
        self::assertSame('2026-12-31 23:00:00', HeureAgence::versServeur('2027-01-01 00:00:00', 'Europe/Paris'));
        self::assertSame('2026-10-23 00:00:00', HeureAgence::versServeur('2026-10-23 00:00:00', 'Africa/Abidjan'));
    }

    public function test_un_fuseau_abime_retombe_sur_abidjan(): void
    {
        self::assertSame('Africa/Abidjan', HeureAgence::fuseau('Mars/Olympus'));
        self::assertSame('Africa/Abidjan', HeureAgence::fuseau(null));
        self::assertSame('23/10/2026 12 h 05', HeureAgence::date('2026-10-23 12:05:00', 'n\'importe quoi'));
        self::assertSame('—', HeureAgence::date(null, 'Europe/Paris'));
        self::assertSame('—', HeureAgence::date('0000-00-00 00:00:00', 'Europe/Paris'));
        self::assertSame('Paris', HeureAgence::ville('Europe/Paris'));
    }
}
