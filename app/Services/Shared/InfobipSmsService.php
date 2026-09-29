<?php

declare(strict_types=1);

namespace App\Services\Shared;

/**
 * Service d'intégration API Infobip pour l'envoi de SMS (LBP Logistics)
 */
class InfobipSmsService
{
    private string $baseUrl;
    private string $apiKey;
    private string $senderId;
    /** @var array<string> */
    private array $callCenterPhones;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $baseDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
            $configFile = $baseDir . '/config/infobip.php';
            $config = file_exists($configFile) ? require $configFile : [];
        }

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->apiKey = trim((string) ($config['api_key'] ?? ''));
        $this->senderId = trim((string) ($config['sender_id'] ?? 'LBP'));
        $this->callCenterPhones = (array) ($config['call_center_phones'] ?? [
            '05-03-48-6161',
            '05-84-43-03-48',
            '05-03-46-79-79',
        ]);
    }

    /**
     * Formate un numéro de téléphone au standard E.164 exigé par Infobip (ex: 2250503486161).
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Supprimer tous les espaces, tirets, parenthèses et points
        $clean = preg_replace('/[^\d+]/', '', $phone) ?? '';

        // Retirer le '+' initial
        $clean = ltrim($clean, '+');

        // Cas Côte d'Ivoire (+225 ou 00225)
        if (str_starts_with($clean, '00225')) {
            $clean = substr($clean, 2);
        }

        // Si c'est un numéro ivoirien local à 10 chiffres (commence par 01, 05, 07)
        if (preg_match('/^0[157]\d{8}$/', $clean)) {
            $clean = '225' . $clean;
        }

        return $clean;
    }

    /**
     * Retourne la signature textuelle des numéros du Call Center.
     */
    public function getCallCenterSignature(): string
    {
        return implode(' / ', $this->callCenterPhones);
    }

    /**
     * Envoie un SMS transactionnel via l'API REST Infobip.
     *
     * @return array{success: bool, message: string, response?: mixed}
     */
    public function sendSms(string $to, string $message): array
    {
        $formattedPhone = $this->formatPhoneNumber($to);

        if (empty($formattedPhone)) {
            return [
                'success' => false,
                'message' => 'Numéro de téléphone destinataire invalide ou vide.',
            ];
        }

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            error_log('[InfobipSmsService] Configuration manquante (baseUrl ou apiKey non renseignée).');
            return [
                'success' => false,
                'message' => 'Configuration Infobip incomplète (URL de base ou Clé API manquante).',
            ];
        }

        // L'API Infobip accepte la clé directement ou préfixée par 'App '
        $authHeader = str_starts_with($this->apiKey, 'App ') ? $this->apiKey : 'App ' . $this->apiKey;

        $url = $this->baseUrl . '/sms/2/text/advanced';

        $payload = [
            'messages' => [
                [
                    'destinations' => [
                        ['to' => $formattedPhone],
                    ],
                    'from' => $this->senderId,
                    'text' => $message,
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authHeader,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("[InfobipSmsService] Erreur cURL vers Infobip : {$curlError}");
            return [
                'success' => false,
                'message' => "Erreur de connexion vers Infobip : {$curlError}",
            ];
        }

        $decoded = json_decode((string) $response, true);

        // Infobip renvoie HTTP 200 avec le statut des messages
        if ($httpCode >= 200 && $httpCode < 300) {
            $statusGroup = $decoded['messages'][0]['status']['groupName'] ?? 'OK';
            $statusDesc = $decoded['messages'][0]['status']['description'] ?? 'Message envoyé';

            if ($statusGroup === 'REJECTED' || $statusGroup === 'UNDELIVERABLE') {
                error_log("[InfobipSmsService] Message rejeté par Infobip ({$formattedPhone}) : {$statusDesc}");
                return [
                    'success' => false,
                    'message' => "SMS rejeté par l'opérateur : {$statusDesc}",
                    'response' => $decoded,
                ];
            }

            error_log("[InfobipSmsService] SMS envoyé avec succès à {$formattedPhone}");
            return [
                'success' => true,
                'message' => 'SMS expédié avec succès.',
                'response' => $decoded,
            ];
        }

        // Gestion des erreurs HTTP (401 Unauthorized, 402 Solde insuffisant, etc.)
        $errorMsg = $decoded['requestError']['serviceException']['text'] 
            ?? $decoded['message'] 
            ?? "Erreur HTTP {$httpCode}";

        error_log("[InfobipSmsService] Échec envoi SMS HTTP {$httpCode} : {$errorMsg}");

        return [
            'success' => false,
            'message' => "Échec d'envoi Infobip (HTTP {$httpCode}) : {$errorMsg}",
            'response' => $decoded,
        ];
    }
}
