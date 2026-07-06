<?php

namespace App\Services\Shared;

/**
 * Interface pour le service de notifications (SMS, WhatsApp, Email).
 */
interface NotificationServiceInterface
{
    /**
     * Envoie une notification au destinataire via le canal choisi.
     *
     * @param string $to Le numéro de téléphone ou l'email du destinataire
     * @param string $message Le corps du message
     * @param string $channel Le canal choisi ('sms', 'whatsapp', 'email')
     * @return bool True en cas de succès, false sinon
     */
    public function send(string $to, string $message, string $channel = 'sms'): bool;
}
