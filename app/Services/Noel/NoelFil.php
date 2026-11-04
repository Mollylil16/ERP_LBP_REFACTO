<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Models\Database;
use PDO;
use Throwable;

/**
 * Ce que NOEL retient du fil de la conversation.
 *
 * Sans cela il pose des questions qu'il n'entend pas : « voulez-vous que je
 * transmette ? » — « oui » — « je n'ai pas compris votre question ». C'est
 * pire que de ne rien proposer : quelqu'un qui dit oui et n'obtient rien cesse
 * de faire confiance.
 *
 * CETTE MEMOIRE A VECU DANS LA SESSION, ET C'ETAIT UN PIEGE.
 *
 * Constate le 04/11/2026 sur deux captures : NOEL pose l'enigme du matin, la
 * personne repond, et il repond « je n'ai pas compris votre question ». Il
 * avait oublie sa propre question en une seconde.
 *
 * La cause : sa reponse n'est pas produite dans la requete de la personne.
 * Elle part dans SuitesEnvoi, une file d'arriere-plan qui appelle
 * session_write_close() AVANT d'executer ses travaux — pour rendre la main au
 * navigateur sans attendre. NOEL ecrivait donc sa memoire dans une session
 * deja fermee : l'ecriture n'arrivait nulle part. En ligne de commande, ou
 * cette file est court-circuitee, tout marchait — c'est ce qui a si longtemps
 * cache le defaut.
 *
 * Elle vit donc en base, et vingt-cinq minutes au plus. La duree n'est pas un
 * detail : savoir qui a seche sur l'enigme de mercredi ne regarde personne, et
 * un « oui » donne une heure apres la question ne doit rien transmettre. Les
 * lignes perimees sont effacees a l'ecriture suivante.
 *
 * En test, rien ne touche la base : la memoire tient dans un tableau, le temps
 * du processus.
 */
final class NoelFil
{
    /** Au-dela, le contexte d'une conversation n'a plus a servir. */
    private const MINUTES = 25;

    /** Une ecriture sur vingt fait le menage : la purge ne doit rien couter. */
    private const CHANCES_DE_PURGE = 20;

    /** Au-dela, on ne garde pas en memoire le contexte de fils oublies. */
    private const FILS_RETENUS = 10;

    /**
     * Le cache de la requete en cours. Il evite de relire la base deux fois
     * pour le meme fil, et porte seul la memoire en test.
     *
     * @var array<int, array{concept: ?string, module: ?string, attente: ?string, question: ?string, essais: int}>
     */
    private static array $memoire = [];

    /**
     * Ce dont on parlait, et ce que NOEL attend.
     *
     * @return array{concept: ?string, module: ?string, attente: ?string, question: ?string, essais: int}
     */
    public static function dernier(int $conversationId): array
    {
        $vide = ['concept' => null, 'module' => null, 'attente' => null, 'question' => null, 'essais' => 0];

        if ($conversationId <= 0) {
            return $vide;
        }

        if (isset(self::$memoire[$conversationId])) {
            return self::$memoire[$conversationId];
        }

        if (self::enTest()) {
            return $vide;
        }

        try {
            $stmt = self::base()->prepare('
                SELECT etat FROM lbp_noel_fils
                 WHERE conversation_id = :id AND expire_at > NOW()
                 LIMIT 1
            ');
            $stmt->execute(['id' => $conversationId]);
            $brut = $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[NOEL fil] lecture impossible : ' . $e->getMessage());

            return $vide;
        }

        if (!is_string($brut) || $brut === '') {
            return $vide;
        }

        $etat = json_decode($brut, true);

        if (!is_array($etat)) {
            return $vide;
        }

        self::$memoire[$conversationId] = array_merge($vide, self::propre($etat));

        return self::$memoire[$conversationId];
    }

    /**
     * Retenir l'etat du fil apres une reponse.
     *
     * @param array<string, mixed> $etat
     */
    public static function retenir(int $conversationId, array $etat): void
    {
        if ($conversationId <= 0) {
            return;
        }

        $propre = self::propre($etat);
        self::$memoire[$conversationId] = $propre;

        /*
         * On ne garde en memoire que les derniers fils. Un agent qui ecrit a
         * vingt collegues n'a pas besoin qu'on se souvienne de tout, et le
         * cache d'une requete n'a aucune raison d'enfler.
         */
        if (count(self::$memoire) > self::FILS_RETENUS) {
            self::$memoire = array_slice(self::$memoire, -self::FILS_RETENUS, null, true);
        }

        if (self::enTest()) {
            return;
        }

        try {
            $base = self::base();

            $stmt = $base->prepare('
                INSERT INTO lbp_noel_fils (conversation_id, etat, expire_at)
                VALUES (:id, :etat, DATE_ADD(NOW(), INTERVAL ' . self::MINUTES . ' MINUTE))
                ON DUPLICATE KEY UPDATE
                    etat = VALUES(etat),
                    expire_at = VALUES(expire_at)
            ');
            $stmt->execute([
                'id' => $conversationId,
                'etat' => (string) json_encode($propre, JSON_UNESCAPED_UNICODE),
            ]);

            // Le menage, de temps en temps : une conversation oubliee n'a pas
            // a laisser sa trace plus longtemps que les autres.
            if (random_int(1, self::CHANCES_DE_PURGE) === 1) {
                $base->exec('DELETE FROM lbp_noel_fils WHERE expire_at < NOW()');
            }
        } catch (Throwable $e) {
            // La memoire du tour reste en cache : NOEL repondra juste, et il
            // oubliera au message suivant. Jamais de messagerie cassee pour ca.
            error_log('[NOEL fil] ecriture impossible : ' . $e->getMessage());
        }
    }

    /** NOEL n'attend plus rien sur ce fil. */
    public static function oublierAttente(int $conversationId): void
    {
        $fil = self::dernier($conversationId);
        $fil['attente'] = null;
        $fil['question'] = null;
        $fil['essais'] = 0;

        self::retenir($conversationId, $fil);
    }

    /** Pour les tests, et pour repartir propre. */
    public static function toutOublier(): void
    {
        self::$memoire = [];

        if (self::enTest()) {
            return;
        }

        try {
            self::base()->exec('DELETE FROM lbp_noel_fils');
        } catch (Throwable $e) {
            error_log('[NOEL fil] remise a zero impossible : ' . $e->getMessage());
        }
    }

    /**
     * Les cinq champs, et rien d'autre : ce qu'on ecrit est ce qu'on a decrit.
     *
     * @param array<string, mixed> $etat
     *
     * @return array{concept: ?string, module: ?string, attente: ?string, question: ?string, essais: int}
     */
    private static function propre(array $etat): array
    {
        return [
            'concept' => isset($etat['concept']) ? (string) $etat['concept'] : null,
            'module' => isset($etat['module']) ? (string) $etat['module'] : null,
            'attente' => isset($etat['attente']) ? (string) $etat['attente'] : null,
            'question' => isset($etat['question']) ? (string) $etat['question'] : null,
            'essais' => (int) ($etat['essais'] ?? 0),
        ];
    }

    private static function base(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Database::getConnection() n'echoue pas : il arrete le processus. Un
     * try/catch ne protegerait donc de rien, et toute la suite de tests
     * tomberait des que NOEL retient quelque chose.
     */
    private static function enTest(): bool
    {
        return ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '') === 'testing';
    }
}
