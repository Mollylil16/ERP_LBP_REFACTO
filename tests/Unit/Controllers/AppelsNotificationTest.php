<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Services\Shared\NotificationService;
use Tests\TestCase;

/**
 * Les controleurs n'appellent que des methodes qui existent.
 *
 * Jusqu'au 14/09/2026, le bouton « Relancer » d'une facture appelait
 * NotificationService::send(), qui n'a jamais existe, et la relance groupee
 * passait un texte a dispatchPushOrWebhook(), qui attend un tableau. Les deux
 * boutons renvoyaient une erreur 500 a l'agent, sans qu'aucun test ne le voie :
 * PHP ne verifie l'existence d'une methode qu'au moment de l'appeler.
 */
final class AppelsNotificationTest extends TestCase
{
    public function test_chaque_methode_appelee_sur_le_service_de_notification_existe(): void
    {
        $manquantes = [];

        foreach ($this->fichiersControleurs() as $chemin) {
            $source = (string) file_get_contents($chemin);

            preg_match_all('/\$(?:this->)?notif(?:ication)?Service->(\w+)\(/', $source, $appels);

            foreach (array_unique($appels[1]) as $methode) {
                if (!method_exists(NotificationService::class, $methode)) {
                    $manquantes[] = str_replace(BASE_PATH . '/', '', $chemin) . ' appelle ' . $methode . '()';
                }
            }
        }

        self::assertSame([], $manquantes, "Methode inexistante sur NotificationService :\n" . implode("\n", $manquantes));
    }

    /**
     * Le premier argument de dispatchPushOrWebhook() est le colis, sous forme de
     * tableau. Lui passer un nom d'evenement leve une TypeError, donc une 500.
     */
    public function test_dispatch_push_ne_recoit_jamais_un_texte_en_premier_argument(): void
    {
        foreach ($this->fichiersControleurs() as $chemin) {
            self::assertDoesNotMatchRegularExpression(
                "/dispatchPushOrWebhook\(\s*['\"]/",
                (string) file_get_contents($chemin),
                str_replace(BASE_PATH . '/', '', $chemin) . ' passe un texte a dispatchPushOrWebhook().'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function fichiersControleurs(): array
    {
        $fichiers = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/app/Controllers'));

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && $fichier->getExtension() === 'php') {
                $fichiers[] = str_replace('\\', '/', $fichier->getPathname());
            }
        }

        return $fichiers;
    }
}
