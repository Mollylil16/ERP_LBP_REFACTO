<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTime;
use DateTimeZone;

class Accounting
{
    /**
     * Retourne la date et l'heure comptable pour l'enregistrement des opérations.
     * Si l'heure locale (Afrique/Abidjan / GMT) est >= 15h00, la date est reportée
     * au lendemain matin à 08h00.
     */
    public static function getAccountingDateTime(?string $customTime = null): string
    {
        $timezone = new DateTimeZone('Africa/Abidjan');
        
        if ($customTime !== null) {
            $now = new DateTime($customTime, $timezone);
        } else {
            $now = new DateTime('now', $timezone);
        }

        $hour = (int)$now->format('H');

        if ($hour >= 15) {
            $now->modify('+1 day');
            $now->setTime(8, 0, 0);
        }

        return $now->format('Y-m-d H:i:s');
    }
}
