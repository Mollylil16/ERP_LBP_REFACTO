<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Mobile\AbonnementsPushInterface;
use App\Services\Mobile\EnvoiPushInterface;
use App\Services\Mobile\NotificationDirectionService;
use PDO;
use Tests\TestCase;

/**
 * Diffusion des alertes vers le téléphone de la direction.
 *
 * L'enjeu principal est de ne jamais harceler : une même situation ne doit réveiller
 * un destinataire qu'une seule fois, même si le balayage tourne toutes les heures.
 */
final class NotificationDirectionServiceTest extends TestCase
{
    public function test_un_ecart_sous_le_seuil_ne_reveille_personne(): void
    {
        [$service, $push] = $this->service();

        self::assertFalse($service->ecartDeCaisse(1, 'Abobo', '2026-09-10', -331.0, 'monnaie'));
        self::assertSame([], $push->envois);
    }

    public function test_un_ecart_important_part_vers_tous_les_appareils(): void
    {
        [$service, $push] = $this->service();

        self::assertTrue($service->ecartDeCaisse(12, 'Agence Abobo Dokui', '2026-09-10', -62900.0, null));

        self::assertCount(3, $push->envois, 'Le DG a deux appareils, l\'assistante un.');
        self::assertSame('Écart de caisse important', $push->envois[0]['charge']['titre']);
        self::assertStringContainsString('-62 900 XOF', $push->envois[0]['charge']['corps']);
        self::assertTrue($push->envois[0]['charge']['urgent']);
    }

    public function test_l_absence_de_justification_est_dite_explicitement(): void
    {
        [$service, $push] = $this->service();
        $service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null);

        self::assertStringContainsString('Aucune justification', $push->envois[0]['charge']['corps']);
    }

    public function test_le_motif_est_repris_quand_il_existe(): void
    {
        [$service, $push] = $this->service();
        $service->ecartDeCaisse(12, 'Abobo', '2026-09-10', 80000.0, 'Recette non tracée');

        self::assertStringContainsString('+80 000 XOF', $push->envois[0]['charge']['corps']);
        self::assertStringContainsString('Recette non tracée', $push->envois[0]['charge']['corps']);
    }

    public function test_le_meme_evenement_ne_repart_jamais_deux_fois(): void
    {
        [$service, $push] = $this->service();

        self::assertTrue($service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null));
        $apresPremier = count($push->envois);

        self::assertFalse($service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null));
        self::assertCount($apresPremier, $push->envois, 'Le balayage horaire ne doit pas rappeler la même alerte.');
    }

    public function test_un_evenement_distinct_declenche_bien_une_alerte(): void
    {
        [$service, $push] = $this->service();

        $service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null);
        self::assertTrue($service->ecartDeCaisse(13, 'Yopougon', '2026-09-10', -70000.0, null));

        self::assertCount(6, $push->envois);
    }

    public function test_un_abonnement_expire_est_supprime(): void
    {
        [$service, $push, $abonnements] = $this->service();
        $push->expires[] = 'https://fcm.googleapis.com/assistante';

        $service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null);

        self::assertContains(3, $abonnements->supprimes);
        self::assertContains(1, $abonnements->succes);
        self::assertContains(2, $abonnements->succes);
    }

    public function test_aucun_abonne_ne_provoque_pas_d_erreur(): void
    {
        $service = new NotificationDirectionService(
            $this->baseNotifications(),
            new class implements EnvoiPushInterface {
                public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array
                {
                    return ['ok' => true, 'status' => 201, 'expire' => false, 'message' => 'ok'];
                }
            },
            $this->abonnements([])
        );

        self::assertFalse($service->ecartDeCaisse(1, 'Abobo', '2026-09-10', -99000.0, null));
    }

    public function test_le_journal_contient_une_entree_par_destinataire(): void
    {
        [$service, , , $pdo] = $this->service();

        $service->ecartDeCaisse(12, 'Abobo', '2026-09-10', -62900.0, null);
        $service->ecartDeCaisse(13, 'Yopougon', '2026-09-10', -70000.0, null);

        $total = (int) $pdo->query('SELECT COUNT(*) FROM lbp_mobile_notifications')->fetchColumn();
        self::assertSame(4, $total, 'Deux alertes vers deux destinataires.');

        $doublons = (int) $pdo->query(
            'SELECT COUNT(*) FROM (SELECT user_id, event_key FROM lbp_mobile_notifications
             GROUP BY user_id, event_key HAVING COUNT(*) > 1)'
        )->fetchColumn();
        self::assertSame(0, $doublons);
    }

    // -----------------------------------------------------------------

    /**
     * @return array{0: NotificationDirectionService, 1: object, 2: object, 3: PDO}
     */
    private function service(): array
    {
        $pdo = $this->baseNotifications();

        $push = new class implements EnvoiPushInterface {
            /** @var array<int, array<string, mixed>> */
            public array $envois = [];
            /** @var array<int, string> */
            public array $expires = [];

            public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array
            {
                $this->envois[] = ['endpoint' => $abonnement['endpoint'], 'charge' => $charge];

                if (in_array($abonnement['endpoint'], $this->expires, true)) {
                    return ['ok' => false, 'status' => 410, 'expire' => true, 'message' => 'expiré'];
                }

                return ['ok' => true, 'status' => 201, 'expire' => false, 'message' => 'ok'];
            }
        };

        $abonnements = $this->abonnements([
            ['id' => 1, 'user_id' => 7, 'endpoint' => 'https://web.push.apple.com/iphone-dg', 'p256dh' => 'x', 'auth' => 'y'],
            ['id' => 2, 'user_id' => 7, 'endpoint' => 'https://fcm.googleapis.com/tablette-dg', 'p256dh' => 'x', 'auth' => 'y'],
            ['id' => 3, 'user_id' => 9, 'endpoint' => 'https://fcm.googleapis.com/assistante', 'p256dh' => 'x', 'auth' => 'y'],
        ]);

        return [new NotificationDirectionService($pdo, $push, $abonnements), $push, $abonnements, $pdo];
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     */
    private function abonnements(array $lignes): object
    {
        return new class ($lignes) implements AbonnementsPushInterface {
            /** @var array<int, int> */
            public array $supprimes = [];
            /** @var array<int, int> */
            public array $succes = [];

            /** @param array<int, array<string, mixed>> $lignes */
            public function __construct(private array $lignes) {}

            public function pourDirection(): array
            {
                return $this->lignes;
            }

            public function supprimer(int $id): void
            {
                $this->supprimes[] = $id;
                $this->lignes = array_values(array_filter($this->lignes, static fn(array $l): bool => (int) $l['id'] !== $id));
            }

            public function marquerSucces(int $id): void
            {
                $this->succes[] = $id;
            }

            public function marquerEchec(int $id): void {}
        };
    }

    private function baseNotifications(): PDO
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                return parent::prepare(str_replace('NOW()', "datetime('now')", $query), $options);
            }
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE lbp_mobile_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, event_key TEXT, categorie TEXT,
            titre TEXT, corps TEXT, url TEXT, lu_at TEXT, envoye_at TEXT, created_at TEXT,
            UNIQUE(user_id, event_key))');

        return $pdo;
    }
}
