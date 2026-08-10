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

        $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'SMS');
        $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'WHATSAPP');
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

        // Simuler l'expédition vers la passerelle SMS / WhatsApp
        $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'SMS');
        $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'WHATSAPP');
        $this->dispatchPushOrWebhook($colis, 'RETRAIT_CONFIRME', ['recup_nom' => $recupNom, 'frais' => $fraisGardiennage]);
    }

    /**
     * Envoie et enregistre la notification lors d'un changement de statut en transit ou étape GPS.
     *
     * @param array<string, mixed> $colis
     */
    public function notifyParcelStatusChange(array $colis, string $newStatut, ?string $etapeOrDetails = null): void
    {
        $tracking = $colis['numero_tracking'] ?? '';
        $destPhone = $colis['destinataire_phone'] ?? $colis['recup_telephone'] ?? null;
        $expPhone = $colis['expediteur_phone'] ?? null;
        $destEmail = $colis['destinataire_email'] ?? null;

        $statusLabels = [
            'EN_PRÉPARATION' => 'en cours de préparation pour expédition',
            'EN_TRANSIT' => 'en transit / en cours d\'acheminement',
            'ARRIVÉ' => 'arrivé à l\'agence de destination',
            'LIVRÉ' => 'livré au destinataire',
            'RETIRÉ' => 'retiré au comptoir',
        ];

        $statusLabel = $statusLabels[strtoupper($newStatut)] ?? $newStatut;
        $msg = "Suivi Colis N° " . $tracking . " : Votre colis est actuellement " . $statusLabel . ".";
        if ($etapeOrDetails) {
            $msg .= " Info jalon : " . $etapeOrDetails . ".";
        }
        $msg .= " Suivez votre colis en temps réel sur notre portail.";

        $this->repository->createNotification([
            'colis_id' => (int) ($colis['id'] ?? 0),
            'destinataire_telephone' => $destPhone,
            'destinataire_email' => $destEmail,
            'type_notification' => 'CHANGEMENT_STATUT_' . strtoupper($newStatut),
            'statut' => 'ENVOYÉ',
            'message' => $msg,
        ]);

        if ($destPhone) {
            $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'SMS');
            $this->dispatchSmsOrWhatsapp((string) $destPhone, $msg, 'WHATSAPP');
        }
        if ($expPhone && $expPhone !== $destPhone) {
            $this->dispatchSmsOrWhatsapp((string) $expPhone, $msg, 'SMS');
        }

        $this->dispatchPushOrWebhook($colis, 'CHANGEMENT_STATUT', [
            'statut' => $newStatut,
            'etape' => $etapeOrDetails,
        ]);
    }

    /**
     * Envoie et enregistre les notifications par e-mail lors de la création d'un colis (Prêt à l'envoi).
     * L'expéditeur et le destinataire reçoivent un mail contenant le lien de tracking en direct.
     *
     * @param array<string, mixed> $colis
     */
    public function notifyParcelCreation(array $colis): void
    {
        $tracking = $colis['numero_tracking'] ?? '';
        $expEmail = filter_var($colis['expediteur_email'] ?? '', FILTER_VALIDATE_EMAIL) ? (string) $colis['expediteur_email'] : null;
        $destEmail = filter_var($colis['destinataire_email'] ?? '', FILTER_VALIDATE_EMAIL) ? (string) $colis['destinataire_email'] : null;
        $expPhone = $colis['expediteur_phone'] ?? null;
        $destPhone = $colis['destinataire_phone'] ?? null;

        if (!$expEmail && !$destEmail) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $trackingPath = \App\Helpers\View::url('site/tracking?ref=' . urlencode($tracking));
        $trackingUrl = $scheme . '://' . $host . $trackingPath;

        $expName = (string) ($colis['expediteur_name'] ?? 'Expéditeur');
        $destName = (string) ($colis['destinataire_name'] ?? 'Destinataire');
        $nbreColis = (int) ($colis['nombre_colis'] ?? 1);
        $poidsTotal = (float) ($colis['poids_total'] ?? 0);
        $agenceDep = (string) ($colis['agence_depart_name'] ?? 'Agence de départ');
        $agenceArr = (string) ($colis['agence_arrivee_name'] ?? 'Agence de destination');

        // Notification Mail à l'Expéditeur
        if ($expEmail) {
            $subjectExp = "Prêt à l'envoi - Colis N° " . $tracking . " - LBP Logistics";
            $htmlBodyExp = $this->buildParcelCreationEmailHtml([
                'recipient_name' => $expName,
                'role_label' => 'Expéditeur',
                'tracking_number' => $tracking,
                'expediteur_name' => $expName,
                'destinataire_name' => $destName,
                'nombre_colis' => $nbreColis,
                'poids_total' => $poidsTotal,
                'agence_depart' => $agenceDep,
                'agence_arrivee' => $agenceArr,
                'tracking_url' => $trackingUrl,
                'intro_message' => "Votre colis est bien enregistré et prêt à l'envoi. Vous pouvez suivre son acheminement en temps réel dès maintenant.",
            ]);

            $this->sendEmail($expEmail, $subjectExp, $htmlBodyExp);

            $this->repository->createNotification([
                'colis_id' => (int) ($colis['id'] ?? 0),
                'destinataire_telephone' => $expPhone ? (string) $expPhone : null,
                'destinataire_email' => $expEmail,
                'type_notification' => 'COLIS_CREE_EXPEDITEUR',
                'statut' => 'ENVOYÉ',
                'message' => "Email de confirmation du colis N° " . $tracking . " prêt à l'envoi expédié à l'expéditeur (" . $expEmail . ").",
            ]);
        }

        // Notification Mail au Destinataire
        if ($destEmail) {
            $subjectDest = "Un colis N° " . $tracking . " vous est destiné - Prêt à l'envoi - LBP Logistics";
            $htmlBodyDest = $this->buildParcelCreationEmailHtml([
                'recipient_name' => $destName,
                'role_label' => 'Destinataire',
                'tracking_number' => $tracking,
                'expediteur_name' => $expName,
                'destinataire_name' => $destName,
                'nombre_colis' => $nbreColis,
                'poids_total' => $poidsTotal,
                'agence_depart' => $agenceDep,
                'agence_arrivee' => $agenceArr,
                'tracking_url' => $trackingUrl,
                'intro_message' => "Un colis expédié par " . $expName . " a été enregistré et est prêt à l'envoi à votre attention. Suivez sa livraison en direct.",
            ]);

            $this->sendEmail($destEmail, $subjectDest, $htmlBodyDest);

            $this->repository->createNotification([
                'colis_id' => (int) ($colis['id'] ?? 0),
                'destinataire_telephone' => $destPhone ? (string) $destPhone : null,
                'destinataire_email' => $destEmail,
                'type_notification' => 'COLIS_CREE_DESTINATAIRE',
                'statut' => 'ENVOYÉ',
                'message' => "Email de notification du colis N° " . $tracking . " prêt à l'envoi expédié au destinataire (" . $destEmail . ").",
            ]);
        }
    }

    /**
     * Envoie un e-mail au format HTML avec en-têtes sécurisés.
     */
    public function sendEmail(string $to, string $subject, string $htmlBody): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: LBP Logistics <no-reply@lbp-logistics.com>',
            'Reply-To: contact@lbp-logistics.com',
            'X-Mailer: PHP/' . phpversion(),
        ];

        $headersString = implode("\r\n", $headers);

        try {
            $sent = @mail($to, $subject, $htmlBody, $headersString);
            error_log('[NOTIF_EMAIL] Email envoyé à ' . $to . ' | Sujet : ' . $subject . ' | Statut : ' . ($sent ? 'SUCCÈS' : 'ÉCHEC/SIMULÉ'));
            return $sent;
        } catch (\Throwable $e) {
            error_log('[NOTIF_EMAIL_ERROR] Erreur envoi mail à ' . $to . ' : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère le template HTML responsive et élégant pour le mail de prise en charge du colis.
     *
     * @param array<string, mixed> $p
     */
    private function buildParcelCreationEmailHtml(array $p): string
    {
        $recipient = htmlspecialchars((string) ($p['recipient_name'] ?? 'Client'), ENT_QUOTES, 'UTF-8');
        $role = htmlspecialchars((string) ($p['role_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tracking = htmlspecialchars((string) ($p['tracking_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $expName = htmlspecialchars((string) ($p['expediteur_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $destName = htmlspecialchars((string) ($p['destinataire_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nbColis = (int) ($p['nombre_colis'] ?? 1);
        $poids = number_format((float) ($p['poids_total'] ?? 0), 2, ',', ' ');
        $agDep = htmlspecialchars((string) ($p['agence_depart'] ?? ''), ENT_QUOTES, 'UTF-8');
        $agArr = htmlspecialchars((string) ($p['agence_arrivee'] ?? ''), ENT_QUOTES, 'UTF-8');
        $trackingUrl = htmlspecialchars((string) ($p['tracking_url'] ?? '#'), ENT_QUOTES, 'UTF-8');
        $intro = htmlspecialchars((string) ($p['intro_message'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colis Prêt à l'envoi - LBP Logistics</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Arial, sans-serif; color: #334155;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1e3a5f; padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: 1px;">LBP LOGISTICS</h1>
                            <p style="margin: 5px 0 0 0; color: #cbd5e1; font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px;">Transport & Fret International</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 35px 40px;">
                            <h2 style="margin: 0 0 15px 0; color: #1e3a5f; font-size: 20px;">Bonjour {$recipient} <span style="font-size: 14px; color: #64748b; font-weight: normal;">({$role})</span>,</h2>
                            <p style="margin: 0 0 25px 0; color: #475569; font-size: 15px; line-height: 1.6;">
                                {$intro}
                            </p>

                            <!-- Tracking Card -->
                            <div style="background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 25px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 5px;">Code de Suivi de votre Colis</span>
                                <strong style="font-size: 26px; color: #1e3a5f; font-family: monospace; letter-spacing: 2px;">{$tracking}</strong>
                                <div style="margin-top: 10px;">
                                    <span style="display: inline-block; background-color: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px;">PRÊT À L'ENVOI</span>
                                </div>
                            </div>

                            <!-- Details Table -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px; width: 40%;">Expéditeur :</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 600;">{$expName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Destinataire :</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 600;">{$destName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Volume & Poids :</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 600;">{$nbColis} colis ({$poids} kg)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Trajet :</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 600;">{$agDep} &rarr; {$agArr}</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 30px 0 20px 0;">
                                <a href="{$trackingUrl}" target="_blank" style="background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 700; padding: 14px 28px; text-decoration: none; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                                    🔍 Suivre mon colis en direct sur le site
                                </a>
                            </div>
                            <p style="text-align: center; color: #94a3b8; font-size: 12px; margin: 0;">
                                Vous pouvez cliquer sur le bouton ci-dessus à tout moment pour consulter la localisation et l'avancement de votre envoi.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 40px; text-align: center; color: #64748b; font-size: 12px;">
                            <p style="margin: 0 0 5px 0;"><strong>LBP Logistics</strong> &mdash; Service Client : 0503467979 / 0503497979</p>
                            <p style="margin: 0;">Cet e-mail automatique a été envoyé suite à l'enregistrement de votre colis.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Simulation / Dispatch de notifications Push / Webhook client.
     *
     * @param array<string, mixed> $colis
     * @param array<string, mixed> $extraData
     */
    public function dispatchPushOrWebhook(array $colis, string $event, array $extraData = []): bool
    {
        $payload = [
            'event' => $event,
            'tracking_number' => $colis['numero_tracking'] ?? '',
            'colis_id' => $colis['id'] ?? 0,
            'statut' => $colis['statut'] ?? '',
            'timestamp' => date('c'),
            'details' => $extraData,
        ];

        error_log('[NOTIF_PUSH_WEBHOOK] Event ' . $event . ' pour ' . ($colis['numero_tracking'] ?? '') . ' : ' . json_encode($payload));
        return true;
    }

    /**
     * Passerelle d'expédition externe SMS / WhatsApp.
     */
    public function dispatchSmsOrWhatsapp(string $phone, string $message, string $channel = 'SMS'): bool
    {
        if (empty($phone)) {
            return false;
        }

        // Préparation du payload API SMS/WhatsApp (ex: Orange SMS / Twilio / WhatsApp Business API)
        $payload = [
            'to' => $phone,
            'body' => $message,
            'channel' => strtoupper($channel),
            'sent_at' => date('Y-m-d H:i:s'),
        ];

        // Pour la démonstration / staging, on journalise dans l'environnement système
        error_log('[NOTIF_' . strtoupper($channel) . '] Expédié à ' . $phone . ' : ' . $message);

        return true;
    }
}
