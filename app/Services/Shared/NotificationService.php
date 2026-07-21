<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Repositories\Shared\NotificationRepository;

class NotificationService
{
    public function __construct(private NotificationRepository $repository) {}

    /**
     * Envoie et enregistre la notification lors de l'arrivée du colis en agence.
     *
     * @param array<string, mixed> $colis
     */
    public function notifyParcelArrival(array $colis, ?string $rayonNom = null): void
    {
        $tracking = $colis['numero_tracking'] ?? '';
        $destPhone = $colis['destinataire_phone'] ?? $colis['recup_telephone'] ?? null;
        $destEmail = $colis['destinataire_email'] ?? null;

        $msg = "Bonjour, votre colis N° " . $tracking . " est bien arrivé en agence.";
        if ($rayonNom) {
            $msg .= " Emplacement : " . $rayonNom . ".";
        }
        $msg .= " Vous disposez de votre délai gratuit pour le retirer.";

        $this->repository->createNotification([
            'colis_id' => (int) ($colis['id'] ?? 0),
            'destinataire_telephone' => $destPhone,
            'destinataire_email' => $destEmail,
            'type_notification' => 'ARRIVEE_AGENCE',
            'statut' => 'ENVOYÉ',
            'message' => $msg,
        ]);
    }

    /**
     * Envoie et enregistre la notification lors du retrait du colis au comptoir.
     *
     * @param array<string, mixed> $colis
     * @param array<string, mixed> $retraitData
     */
    public function notifyParcelWithdrawal(array $colis, array $retraitData, float $fraisGardiennage = 0.0): void
    {
        $tracking = $colis['numero_tracking'] ?? '';
        $destPhone = $retraitData['recup_telephone'] ?? $colis['destinataire_phone'] ?? null;
        $destEmail = $colis['destinataire_email'] ?? null;
        $recupNom = $retraitData['recup_nom'] ?? 'Client';

        $msg = "Bonjour, le retrait du colis N° " . $tracking . " par " . $recupNom . " a été confirmé au comptoir le " . date('d/m/Y H:i') . ".";
        if ($fraisGardiennage > 0) {
            $msg .= " Frais de gardiennage appliqués : " . number_format($fraisGardiennage, 0, ',', ' ') . " XOF.";
        }
        $msg .= " Merci de votre confiance !";

        $this->repository->createNotification([
            'colis_id' => (int) ($colis['id'] ?? 0),
            'destinataire_telephone' => $destPhone,
            'destinataire_email' => $destEmail,
            'type_notification' => 'RETRAIT_CONFIRME',
            'statut' => 'ENVOYÉ',
            'message' => $msg,
        ]);
    }
}
