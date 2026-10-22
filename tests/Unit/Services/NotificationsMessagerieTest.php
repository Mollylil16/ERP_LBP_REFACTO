<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Messagerie\MessagerieDepotInterface;
use App\Services\Messagerie\MessagerieMentions;
use App\Services\Messagerie\MessagerieService;
use App\Services\Messagerie\MessagerieStockage;
use App\Services\Messagerie\NotificationsMessagerie;
use App\Services\Messagerie\SuitesEnvoi;
use App\Services\Mobile\EnvoiPushGroupeInterface;
use App\Services\Mobile\NotificationsPush;
use App\Services\Noel\NoelMessagerie;
use DateTimeImmutable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\Support\Messagerie\ImagesDeTest;
use Tests\Support\Messagerie\NotificationsDepotMemoire;
use Tests\Support\Messagerie\PushFactice;
use Tests\TestCase;

/**
 * Notifications de la messagerie (lot 4) : qui est prévenu, de quoi, et
 * combien de fois. Les messages sont écrits par MessagerieService comme à
 * l'écran ; le service de push est simulé (aucun réseau).
 *
 * Personnages : 1 Awa (qui écrit), 2 Bakary, 3 Clarisse, 4 Éloïse Koné,
 * 7 un compte désactivé, 8 NOEL.
 */
final class NotificationsMessagerieTest extends TestCase
{
    private const NOEL = 8;
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
        . "2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private DepotMessagerieMemoire $depot;
    private PushFactice $push;
    private NotificationsDepotMemoire $lectures;
    private DateTimeImmutable $heure;
    private ?string $dossier = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = new DepotMessagerieMemoire();
        $this->depot->ajouterUtilisateur(1, 'Awa');
        $this->depot->ajouterUtilisateur(2, 'Bakary');
        $this->depot->ajouterUtilisateur(3, 'Clarisse');
        $this->depot->ajouterUtilisateur(4, 'Éloïse Koné');
        $this->depot->ajouterUtilisateur(7, 'Ancien agent');
        $this->depot->ajouterUtilisateur(self::NOEL, 'NOEL');
        $this->heure = new DateTimeImmutable('2026-10-22 09:00:00');

        $this->lectures = new NotificationsDepotMemoire($this->depot);
        $this->push = new PushFactice();
        foreach ([1, 2, 3, 4, 7, self::NOEL] as $userId) {
            $this->push->appareil($userId, 'https://fcm.googleapis.com/fcm/send/appareil-' . $userId);
        }
    }

    protected function tearDown(): void
    {
        if ($this->dossier !== null && is_dir($this->dossier)) {
            $tout = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dossier, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($tout as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->dossier);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Qui est prévenu
    // ------------------------------------------------------------------

    public function test_ni_l_auteur_ni_un_compte_desactive_ni_noel_ni_celui_qui_a_quitte(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2, 3, 4, 7, self::NOEL]);
        $this->en(4)->quitter($groupe);
        $this->depot->utilisateurs[7]['actif'] = false;

        $this->en(1)->envoyer($groupe, 'Point de caisse à 17 h');

        self::assertCount(1, $this->push->recuPar(2));
        self::assertCount(1, $this->push->recuPar(3));
        self::assertSame([], $this->push->recuPar(1), 'L’auteur n’est pas prévenu de son propre message.');
        self::assertSame([], $this->push->recuPar(4), 'Un membre qui a quitté n’est plus prévenu.');
        self::assertSame([], $this->push->recuPar(7), 'Un compte désactivé n’est pas prévenu.');
        self::assertSame([], $this->push->recuPar(self::NOEL), 'NOEL n’a pas de téléphone à réveiller.');
    }

    public function test_un_membre_sans_appareil_abonne_ne_coute_rien(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->push->abonnements = [];

        $this->en(1)->envoyer($groupe, 'Bonjour');

        self::assertSame([], $this->push->envoyes);
    }

    public function test_un_message_deja_lu_ou_supprime_ne_notifie_pas(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $message = $this->silencieux(1)->envoyer($directe, 'Tu es là ?');
        $this->en(2)->marquerLu($directe, (int) $message['id']);

        self::assertSame(0, $this->notifications()->surMessage((int) $message['id']));

        $autre = $this->silencieux(1)->envoyer($directe, 'Oublie');
        $this->silencieux(1)->supprimer((int) $autre['id']);

        self::assertSame(0, $this->notifications()->surMessage((int) $autre['id']));
        self::assertSame([], $this->push->envoyes);
    }

    // ------------------------------------------------------------------
    // Ce que dit la notification
    // ------------------------------------------------------------------

    public function test_la_charge_d_un_message_de_groupe(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $message = $this->en(1)->envoyer($groupe, "Point de caisse\n à 17 h");

        self::assertSame([[
            'type' => 'messagerie',
            'titre' => 'Caisse Dokui',
            'corps' => 'Awa : Point de caisse à 17 h',
            'url' => '/messagerie#c=' . $groupe . '&m=' . $message['id'],
            'tag' => 'msg-c-' . $groupe,
            'conversation_id' => $groupe,
            'message_id' => (int) $message['id'],
        ]], $this->push->recuPar(2));
        self::assertSame(NotificationsMessagerie::DUREE_DE_VIE_SECONDES, $this->push->envoyes[0]['ttl']);
    }

    public function test_la_charge_d_une_discussion_directe(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $this->en(1)->envoyer($directe, 'Tu passes à 15 h ?');

        $charge = $this->push->recuPar(2)[0];
        self::assertSame('Awa', $charge['titre']);
        self::assertSame('Tu passes à 15 h ?', $charge['corps']);
        self::assertSame('msg-c-' . $directe, $charge['tag']);
    }

    public function test_un_long_message_est_coupe(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $this->en(1)->envoyer($directe, str_repeat('é', 300));

        $corps = $this->push->recuPar(2)[0]['corps'];
        self::assertSame(NotificationsMessagerie::APERCU_MAX, mb_strlen($corps));
        self::assertStringEndsWith('…', $corps);
    }

    public function test_l_apercu_masque_ne_dit_que_l_expediteur(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $directe = $this->en(1)->ouvrirDirecte(2);
        $this->en(2)->enregistrerPreferences(['apercu_masque' => true]);

        $this->en(1)->envoyer($directe, 'Le code du coffre est 4512');
        $this->en(1)->envoyer($groupe, 'Le code du coffre est 4512');

        [$direct, $deGroupe] = $this->push->recuPar(2);
        self::assertSame('Nouveau message de Awa', $direct['titre']);
        self::assertStringNotContainsString('4512', $direct['corps']);
        self::assertSame('Nouveau message de Awa', $deGroupe['titre']);
        self::assertSame('Dans « Caisse Dokui »', $deGroupe['corps']);
        self::assertStringNotContainsString('4512', json_encode($this->push->envoyes, JSON_UNESCAPED_UNICODE));
    }

    public function test_un_fichier_dit_photo_ou_document(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $this->enAvecFichiers(1)->envoyerFichier($directe, $this->fichier('Devis transport.pdf', self::PDF));
        $this->enAvecFichiers(1)->envoyerFichier($directe, $this->fichier('IMG_2026.png', ImagesDeTest::pngTransparent()));
        $this->enAvecFichiers(1)->envoyerFichier($directe, $this->fichier('bon.pdf', self::PDF . ' '), 'Le bon signé');

        $recus = $this->push->recuPar(2);
        self::assertSame('Document : Devis transport.pdf', $recus[0]['corps']);
        self::assertSame('2 nouveaux messages · Photo', $recus[1]['corps']);
        self::assertSame('3 nouveaux messages · Le bon signé', $recus[2]['corps']);
    }

    public function test_un_transfert_est_annonce_comme_tel(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $message = $this->silencieux(1)->envoyer($groupe, 'Inventaire vendredi');
        $directe = $this->en(2)->ouvrirDirecte(3);

        $this->en(2)->transferer((int) $message['id'], $directe);

        $charge = $this->push->recuPar(3)[0];
        self::assertSame('Bakary', $charge['titre']);
        self::assertSame('Transféré : Inventaire vendredi', $charge['corps']);
    }

    // ------------------------------------------------------------------
    // Sourdine et mentions
    // ------------------------------------------------------------------

    public function test_une_discussion_en_sourdine_se_tait_sauf_mention(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2, 4]);
        $this->en(2)->regler($groupe, 'sourdine', true);
        $this->en(4)->regler($groupe, 'sourdine', true);

        $this->en(1)->envoyer($groupe, 'Point de caisse à 17 h');
        self::assertSame([], $this->push->recuPar(2));
        self::assertSame([], $this->push->recuPar(4));

        $this->heure = $this->heure->modify('+5 seconds');
        $this->en(1)->envoyer($groupe, 'Merci @ELOISE de vérifier le coffre');

        self::assertSame([], $this->push->recuPar(2), 'Seule la personne mentionnée est réveillée.');
        $charge = $this->push->recuPar(4)[0];
        self::assertSame('Caisse Dokui', $charge['titre']);
        self::assertSame('2 nouveaux messages · Awa vous a mentionné : Merci @ELOISE de vérifier le coffre', $charge['corps']);
    }

    public function test_les_mentions_ignorent_casse_et_accents(): void
    {
        self::assertTrue(MessagerieMentions::mentionne('@Éloïse tu es là ?', 'Éloïse Koné'));
        self::assertTrue(MessagerieMentions::mentionne('cc @eloise', 'Éloïse Koné'));
        self::assertTrue(MessagerieMentions::mentionne('Merci @KONE.', 'Éloïse Koné'));
        self::assertTrue(MessagerieMentions::mentionne('Voir avec @éloïse koné demain', 'Éloïse  KONÉ'));
        self::assertTrue(MessagerieMentions::mentionne('@Jean, ton avis ?', 'KOUAKOU Jean'));

        self::assertFalse(MessagerieMentions::mentionne('Écrire à eloise@lbp.ci', 'Éloïse Koné'), 'Une adresse électronique ne mentionne personne.');
        self::assertFalse(MessagerieMentions::mentionne('@Jeanne arrive', 'KOUAKOU Jean'), 'Le nom doit s’arrêter là.');
        self::assertFalse(MessagerieMentions::mentionne('Éloïse, tu es là ?', 'Éloïse Koné'), 'Sans « @ », pas de mention.');
        self::assertFalse(MessagerieMentions::mentionne('@ tout le monde', 'Éloïse Koné'));
    }

    // ------------------------------------------------------------------
    // Pas de bombardement
    // ------------------------------------------------------------------

    public function test_dans_un_groupe_une_notification_par_minute_au_plus(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);

        foreach (['Un', 'Deux', 'Trois'] as $i => $texte) {
            $this->heure = new DateTimeImmutable('2026-10-22 09:00:' . str_pad((string) ($i * 20), 2, '0', STR_PAD_LEFT));
            $this->en(1)->envoyer($groupe, $texte);
        }
        self::assertCount(1, $this->push->recuPar(2), 'Trois messages en 40 secondes : une seule notification.');

        $this->heure = new DateTimeImmutable('2026-10-22 09:01:05');
        $this->en(1)->envoyer($groupe, 'Quatre');

        $recus = $this->push->recuPar(2);
        self::assertCount(2, $recus);
        self::assertSame('4 nouveaux messages · Awa : Quatre', $recus[1]['corps']);
        self::assertSame($recus[0]['tag'], $recus[1]['tag'], 'La suivante remplace la précédente à l’écran.');
    }

    public function test_une_mention_passe_outre_la_limite_du_groupe(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->en(1)->envoyer($groupe, 'Un');
        $this->heure = $this->heure->modify('+10 seconds');
        $this->en(1)->envoyer($groupe, '@Bakary urgent');

        self::assertCount(2, $this->push->recuPar(2));
    }

    public function test_une_discussion_directe_n_est_pas_limitee(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $this->en(1)->envoyer($directe, 'Un');
        $this->heure = $this->heure->modify('+5 seconds');
        $this->en(1)->envoyer($directe, 'Deux');

        $recus = $this->push->recuPar(2);
        self::assertCount(2, $recus);
        self::assertSame('2 nouveaux messages · Deux', $recus[1]['corps']);
    }

    public function test_un_envoi_rate_ne_compte_pas_dans_la_limite(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->push->statuts['https://fcm.googleapis.com/fcm/send/appareil-2'] = 500;
        $this->en(1)->envoyer($groupe, 'Un');

        unset($this->push->statuts['https://fcm.googleapis.com/fcm/send/appareil-2']);
        $this->heure = $this->heure->modify('+10 seconds');
        $this->en(1)->envoyer($groupe, 'Deux');

        self::assertCount(2, $this->push->recuPar(2), 'Personne n’a été prévenu : la minute ne commence pas.');
    }

    // ------------------------------------------------------------------
    // Abonnements
    // ------------------------------------------------------------------

    public function test_un_abonnement_expire_est_supprime_et_les_echecs_comptes(): void
    {
        $expire = $this->push->appareil(2, 'https://fcm.googleapis.com/fcm/send/ancien-telephone');
        $this->push->statuts['https://fcm.googleapis.com/fcm/send/ancien-telephone'] = 410;
        $panne = $this->push->appareil(3, 'https://updates.push.services.mozilla.com/wpush/v2/panne');
        $this->push->statuts['https://updates.push.services.mozilla.com/wpush/v2/panne'] = 503;

        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2, 3]);
        $this->en(1)->envoyer($groupe, 'Bonjour');

        self::assertArrayNotHasKey($expire, $this->push->abonnements, 'Un abonnement 410 disparaît.');
        self::assertSame(1, $this->push->echecs[$panne] ?? 0, 'Une panne passagère est comptée, pas supprimée.');
        self::assertArrayHasKey($panne, $this->push->abonnements);
        self::assertSame(1, $this->push->succes[2] ?? 0, 'L’autre appareil de Bakary a bien reçu.');
    }

    public function test_l_envoi_groupe_passe_par_le_transport_parallele(): void
    {
        $transport = new class implements EnvoiPushGroupeInterface {
            public int $lots = 0;

            public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array
            {
                throw new \LogicException('Un envoi groupé ne doit pas partir un par un.');
            }

            public function envoyerTous(array $envois, int $ttl = 86400): array
            {
                $this->lots++;

                return array_map(static fn(): array => ['ok' => true, 'status' => 201, 'expire' => false, 'message' => ''], $envois);
            }
        };

        $atteints = (new NotificationsPush($transport, $this->push))->envoyerA([2, 3, 99], ['titre' => 'Rappel', 'corps' => 'Tâche']);

        self::assertSame([2 => 1, 3 => 1], $atteints, 'Un utilisateur sans appareil est absent du résultat.');
        self::assertSame(1, $transport->lots);
    }

    // ------------------------------------------------------------------
    // Branchement dans le service et réponse de NOEL
    // ------------------------------------------------------------------

    public function test_l_accroche_de_noel_ne_voit_que_le_texte_les_notifications_tout(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $accroche = [];
        $notifies = [];
        $service = new MessagerieService(
            $this->depot, 1, false, fn(): DateTimeImmutable => $this->heure, $this->stockage(),
            function (int $c, int $a, string $texte) use (&$accroche): void {
                $accroche[] = $texte;
            },
            function (int $messageId, int $c, int $a) use (&$notifies): void {
                $notifies[] = [$messageId, $c, $a];
            },
        );

        $texte = $service->envoyer($directe, 'Bonjour');
        $fichier = $service->envoyerFichier($directe, $this->fichier('a.pdf', self::PDF));
        $transfert = $service->transferer((int) $texte['id'], $directe);

        self::assertSame(['Bonjour'], $accroche, 'NOEL ne répond qu’aux messages texte.');
        self::assertSame([
            [(int) $texte['id'], $directe, 1],
            [(int) $fichier['id'], $directe, 1],
            [(int) $transfert['id'], $directe, 1],
        ], $notifies);
    }

    public function test_une_notification_en_panne_ne_casse_pas_l_envoi(): void
    {
        $directe = $this->en(1)->ouvrirDirecte(2);
        $service = new MessagerieService(
            $this->depot, 1, false, fn(): DateTimeImmutable => $this->heure, null, null,
            static function (): void {
                throw new \RuntimeException('service de push injoignable');
            },
        );

        self::assertSame('Toujours parti', $service->envoyer($directe, 'Toujours parti')['texte']);
    }

    public function test_la_reponse_de_noel_notifie_son_destinataire(): void
    {
        $avecNoel = $this->silencieux(1)->ouvrirDirecte(self::NOEL);
        $message = $this->silencieux(1)->envoyer($avecNoel, 'Où en est le dossier AF57 ?');
        $notifications = $this->notifications();

        // NOEL d'abord, puis les notifications du message et de sa réponse.
        $ordre = [];
        SuitesEnvoi::enchainer(
            (int) $message['id'],
            $avecNoel,
            1,
            'Où en est le dossier AF57 ?',
            $this->depot,
            function (int $c, int $a, string $t, MessagerieDepotInterface $depot) use (&$ordre): ?int {
                $ordre[] = 'noel';

                return $depot->ajouterMessage([
                    'conversation_id' => $c, 'auteur_id' => self::NOEL, 'texte' => 'Le dossier AF57 est parti hier.',
                    'reponse_a_id' => null, 'transfere_de_id' => null, 'created_at' => '2026-10-22 09:00:03',
                ]);
            },
            function (int $id) use (&$ordre, $notifications): void {
                $ordre[] = 'notifier ' . $id;
                $notifications->surMessage($id);
            },
        );

        self::assertSame('noel', $ordre[0]);
        self::assertCount(3, $ordre);
        self::assertSame([], $this->push->recuPar(self::NOEL));
        $recus = $this->push->recuPar(1);
        self::assertCount(1, $recus);
        self::assertSame('NOEL', $recus[0]['titre']);
        self::assertSame('Le dossier AF57 est parti hier.', $recus[0]['corps']);
    }

    public function test_noel_rend_l_identifiant_de_sa_reponse(): void
    {
        $type = (new \ReflectionMethod(NoelMessagerie::class, 'surMessage'))->getReturnType();

        self::assertSame('?int', (string) $type);
    }

    public function test_les_suites_ne_bloquent_pas_l_agent(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Messagerie/SuitesEnvoi.php');

        // Une seule fonction d'arrêt, qui rend la réponse avant de travailler.
        self::assertSame(1, substr_count($source, 'register_shutdown_function('));
        self::assertLessThan(strpos($source, '$travail();'), strpos($source, 'litespeed_finish_request();'));
        self::assertLessThan(strpos($source, '$travail();'), strpos($source, 'session_write_close();'));
        self::assertStringContainsString('set_time_limit(self::TEMPS_MAX_SECONDES)', $source);

        $service = (string) file_get_contents(BASE_PATH . '/app/Services/Messagerie/MessagerieService.php');
        self::assertStringNotContainsString('register_shutdown_function', $service);
        self::assertStringContainsString('SuitesEnvoi::planifier(', $service);
    }

    // ------------------------------------------------------------------

    private function notifications(): NotificationsMessagerie
    {
        return new NotificationsMessagerie(
            $this->lectures,
            new NotificationsPush($this->push, $this->push),
            self::NOEL,
            '/messagerie',
            fn(): DateTimeImmutable => $this->heure,
        );
    }

    /** Un utilisateur dont les envois notifient, comme sur le serveur. */
    private function en(int $userId): MessagerieService
    {
        return new MessagerieService(
            $this->depot, $userId, false, fn(): DateTimeImmutable => $this->heure, null, null,
            fn(int $messageId): int => $this->notifications()->surMessage($messageId),
        );
    }

    private function enAvecFichiers(int $userId): MessagerieService
    {
        return new MessagerieService(
            $this->depot, $userId, false, fn(): DateTimeImmutable => $this->heure, $this->stockage(), null,
            fn(int $messageId): int => $this->notifications()->surMessage($messageId),
        );
    }

    /** Un utilisateur dont les envois ne notifient pas (en ligne de commande). */
    private function silencieux(int $userId): MessagerieService
    {
        return new MessagerieService($this->depot, $userId, false, fn(): DateTimeImmutable => $this->heure);
    }

    private function stockage(): MessagerieStockage
    {
        if ($this->dossier === null) {
            $this->dossier = sys_get_temp_dir() . '/lbp_notif_' . bin2hex(random_bytes(4));
            mkdir($this->dossier . '/storage', 0777, true);
            mkdir($this->dossier . '/envois', 0777, true);
        }

        return new MessagerieStockage($this->dossier . '/storage', false);
    }

    /** @return array<string, mixed> une entrée façon $_FILES */
    private function fichier(string $nom, string $contenu): array
    {
        $this->stockage();
        $chemin = $this->dossier . '/envois/' . bin2hex(random_bytes(6));
        file_put_contents($chemin, $contenu);

        return ['name' => $nom, 'type' => 'application/octet-stream', 'tmp_name' => $chemin, 'error' => UPLOAD_ERR_OK, 'size' => filesize($chemin)];
    }
}
