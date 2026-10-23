<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use Tests\TestCase;

/**
 * Les heures de la messagerie : l'API les donne en « Y-m-d H:i:s » heure
 * d'Abidjan, c'est-à-dire UTC ; l'écran les montre dans l'heure du
 * navigateur (un agent de Paris lit l'heure de Paris). Vérifié en vrai avec
 * Node sous Europe/Paris et Africa/Abidjan le 23/10/2026 ; ce garde-fou
 * empêche seulement de revenir aux lectures UTC.
 */
final class MessagerieHeureLocaleTest extends TestCase
{
    public function test_les_dates_de_l_api_sont_lues_en_utc_et_affichees_en_heure_locale(): void
    {
        $js = (string) file_get_contents(BASE_PATH . '/public/assets/js/messagerie.js');

        self::assertStringContainsString('new Date(String(chaine).replace(" ", "T") + "Z")', $js, 'La chaîne du serveur est une heure UTC.');
        self::assertDoesNotMatchRegularExpression('/getUTC(Hours|Minutes|Date|Day|Month|FullYear)\(/', $js, "L'affichage doit suivre l'heure du navigateur.");
    }
}
