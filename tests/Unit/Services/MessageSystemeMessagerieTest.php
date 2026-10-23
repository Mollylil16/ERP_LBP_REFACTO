<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Messagerie\MessagerieException;
use App\Services\Messagerie\MessagerieService;
use App\Services\Messagerie\NotificationsMessagerie;
use App\Services\Mobile\NotificationsPush;
use DateTimeImmutable;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\Support\Messagerie\NotificationsDepotMemoire;
use Tests\Support\Messagerie\PushFactice;
use Tests\TestCase;

/**
 * Un message du système dans un groupe automatique : NOEL en est l'auteur
 * sans être membre, et les membres sont prévenus comme pour tout message.
 */
final class MessageSystemeMessagerieTest extends TestCase
{
    private const NOEL = 8;

    private DepotMessagerieMemoire $depot;
    private PushFactice $push;
    private DateTimeImmutable $heure;
    private int $groupe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->heure = new DateTimeImmutable('2026-10-23 18:00:00');
        $this->depot = new DepotMessagerieMemoire();
        $this->depot->ajouterUtilisateur(1, 'Awa');
        $this->depot->ajouterUtilisateur(2, 'Bakary');
        $this->depot->ajouterUtilisateur(self::NOEL, 'NOEL');

        $this->groupe = $this->depot->creerConversation([
            'type' => 'groupe', 'nom' => 'Agence Abobo Dokui', 'description' => '', 'cle_directe' => 'auto:agence:3403',
            'createur_id' => null, 'created_at' => '2026-10-01 08:00:00', 'agence_id' => 3403, 'automatique' => 1,
        ]);
        $this->depot->ajouterMembre($this->groupe, 1, 'admin', '2026-10-01 08:00:00');
        $this->depot->ajouterMembre($this->groupe, 2, 'membre', '2026-10-01 08:00:00');

        $this->push = new PushFactice();
        foreach ([1, 2, self::NOEL] as $userId) {
            $this->push->appareil($userId, 'https://fcm.googleapis.com/fcm/send/appareil-' . $userId);
        }
    }

    public function test_noel_publie_sans_devenir_membre_et_les_membres_sont_prevenus(): void
    {
        $notifications = new NotificationsMessagerie(
            new NotificationsDepotMemoire($this->depot),
            new NotificationsPush($this->push, $this->push),
            self::NOEL,
            'https://erp.test/messagerie',
            fn (): DateTimeImmutable => $this->heure,
        );
        $service = new MessagerieService(
            $this->depot, self::NOEL, false, fn (): DateTimeImmutable => $this->heure, null, null,
            static fn (int $messageId): int => $notifications->surMessage($messageId),
        );

        $id = $service->publierMessageSysteme($this->groupe, 'Paris a reçu 2 colis sur 3 du départ n° DEP-1.');

        self::assertSame(self::NOEL, $this->depot->messages[$id]['auteur_id']);
        self::assertSame('2026-10-23 18:00:00', $this->depot->messages[$id]['created_at']);
        self::assertNull($this->depot->membre($this->groupe, self::NOEL), 'NOEL n’entre pas dans le groupe.');
        self::assertCount(1, $this->push->recuPar(1));
        self::assertCount(1, $this->push->recuPar(2));
        self::assertSame([], $this->push->recuPar(self::NOEL));
    }

    public function test_les_regles_d_un_utilisateur_ne_s_appliquent_pas_mais_le_texte_reste_controle(): void
    {
        $service = new MessagerieService($this->depot, self::NOEL, false, fn (): DateTimeImmutable => $this->heure, null, null, static fn (): ?int => null);

        // Écrire comme un membre lui serait refusé : il n'en est pas un.
        try {
            $service->envoyer($this->groupe, 'Bonjour');
            self::fail('Un non-membre ne doit pas écrire par envoyer().');
        } catch (MessagerieException) {
        }

        self::assertGreaterThan(0, $service->publierMessageSysteme($this->groupe, 'Résumé'));

        $this->expectException(MessagerieException::class);
        $service->publierMessageSysteme($this->groupe, '   ');
    }

    public function test_jamais_dans_une_discussion_directe(): void
    {
        $directe = (new MessagerieService($this->depot, 1, false, fn (): DateTimeImmutable => $this->heure))->ouvrirDirecte(2);
        $service = new MessagerieService($this->depot, self::NOEL, false, fn (): DateTimeImmutable => $this->heure, null, null, static fn (): ?int => null);

        $this->expectException(MessagerieException::class);
        $service->publierMessageSysteme($directe, 'Résumé');
    }
}
