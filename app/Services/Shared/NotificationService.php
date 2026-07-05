<?php

namespace App\Services\Shared;

/**
 * Implémentation par défaut (Mock/Log) pour le service de notifications.
 */
class NotificationService implements NotificationServiceInterface
{
    /**
     * @inheritDoc
     */
    public function send(string $to, string $message, string $channel = 'sms'): bool
    {
        // Journalisation dans les logs système (Wamp/PHP) à des fins de dev et debug
        error_log(sprintf("[LBP-NOTIF][%s] Vers: %s | Message: %s", strtoupper($channel), $to, $message));
        return true;
    }
}
