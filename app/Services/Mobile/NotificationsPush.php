<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Repositories\Mobile\AbonnementsPushUtilisateursInterface;
use App\Repositories\Mobile\PushSubscriptionRepository;
use PDO;
use Throwable;

/**
 * Envoyer une notification à des utilisateurs, sur tous leurs appareils.
 *
 * Le socle commun à la messagerie (lot 4) et aux rappels de tâches (lot 6) :
 * on lui donne des identifiants d'utilisateurs et une charge, il retrouve les
 * abonnements, envoie (en parallèle quand le transport le sait), supprime
 * ceux que le navigateur dit expirés (404, 410) et compte les échecs des
 * autres. Il ne décide pas QUI prévenir : c'est l'affaire de l'appelant.
 *
 * La charge suit le format que les deux service workers comprennent
 * (/mobile/sw.js et /messagerie/sw.js) : titre, corps, url, tag, et urgent
 * facultatif ; type permet au service worker de la messagerie de se taire
 * quand l'écran est déjà sous les yeux de l'utilisateur.
 *
 * Il ne lève jamais d'exception : une notification qui ne part pas ne doit
 * rien casser chez celui qui a déclenché l'envoi.
 */
final class NotificationsPush
{
    public function __construct(
        private EnvoiPushInterface $envoi,
        private AbonnementsPushUtilisateursInterface $abonnements,
    ) {
    }

    public static function creer(PDO $pdo): self
    {
        return new self(new WebPushService($pdo), new PushSubscriptionRepository($pdo));
    }

    /**
     * La même charge pour tous.
     *
     * @param array<int, int> $userIds
     * @param array<string, mixed> $charge
     * @return array<int, int> par utilisateur abonné : le nombre d'appareils atteints
     */
    public function envoyerA(array $userIds, array $charge, int $ttl = 86400): array
    {
        $charges = [];
        foreach ($userIds as $userId) {
            $charges[(int) $userId] = $charge;
        }

        return $this->envoyerChacun($charges, $ttl);
    }

    /**
     * Une charge par utilisateur (la messagerie dit à chacun ce qui le concerne).
     *
     * @param array<int, array<string, mixed>> $charges indexées par utilisateur
     * @return array<int, int> par utilisateur abonné : le nombre d'appareils atteints ;
     *                         un utilisateur sans abonnement est absent
     */
    public function envoyerChacun(array $charges, int $ttl = 86400): array
    {
        if ($charges === []) {
            return [];
        }

        try {
            $abonnements = $this->abonnements->pourUtilisateurs(array_keys($charges));
        } catch (Throwable $e) {
            error_log('[Notifications push] abonnements illisibles : ' . $e->getMessage());

            return [];
        }

        $envois = [];
        foreach ($abonnements as $abonnement) {
            $userId = (int) $abonnement['user_id'];
            if (isset($charges[$userId])) {
                $envois[] = [$abonnement, $charges[$userId]];
            }
        }

        $atteints = [];
        foreach ($this->diffuser($envois, $ttl) as $i => $ok) {
            $userId = (int) $envois[$i][0]['user_id'];
            $atteints[$userId] = ($atteints[$userId] ?? 0) + ($ok ? 1 : 0);
        }

        return $atteints;
    }

    /**
     * Vers un seul abonnement (la notification d'essai d'un appareil).
     *
     * @param array<string, mixed> $abonnement id, endpoint, p256dh, auth
     * @param array<string, mixed> $charge
     */
    public function envoyerVers(array $abonnement, array $charge, int $ttl = 300): bool
    {
        return $this->diffuser([[$abonnement, $charge]], $ttl)[0] ?? false;
    }

    /**
     * @param array<int, array{0: array<string, mixed>, 1: array<string, mixed>}> $envois
     * @return array<int, bool> succès, mêmes clés que $envois
     */
    private function diffuser(array $envois, int $ttl): array
    {
        if ($envois === []) {
            return [];
        }

        try {
            if ($this->envoi instanceof EnvoiPushGroupeInterface) {
                $resultats = $this->envoi->envoyerTous($envois, $ttl);
            } else {
                $resultats = [];
                foreach ($envois as $i => [$abonnement, $charge]) {
                    $resultats[$i] = $this->envoi->envoyer($abonnement, $charge, $ttl);
                }
            }
        } catch (Throwable $e) {
            error_log('[Notifications push] envoi impossible : ' . $e->getMessage());

            return array_fill_keys(array_keys($envois), false);
        }

        $succes = [];
        foreach ($envois as $i => [$abonnement]) {
            $resultat = $resultats[$i] ?? ['ok' => false, 'expire' => false];
            $id = (int) ($abonnement['id'] ?? 0);
            $succes[$i] = (bool) $resultat['ok'];

            try {
                if ($resultat['ok']) {
                    $this->abonnements->marquerSucces($id);
                } elseif ($resultat['expire']) {
                    $this->abonnements->supprimer($id);
                } else {
                    $this->abonnements->marquerEchec($id);
                }
            } catch (Throwable $e) {
                error_log('[Notifications push] suivi de l’abonnement ' . $id . ' : ' . $e->getMessage());
            }
        }

        return $succes;
    }
}
