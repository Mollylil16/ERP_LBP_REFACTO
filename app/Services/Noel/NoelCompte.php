<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Models\Database;
use PDO;
use Throwable;

/**
 * Le compte de NOEL dans le logiciel.
 *
 * La messagerie rattache chaque message a un utilisateur : NOEL a donc besoin
 * d'une ligne dans `users`, comme un collegue. Sans elle, il pourrait penser
 * mais pas ecrire.
 *
 * PERSONNE NE PEUT SE CONNECTER AVEC CE COMPTE. Son mot de passe est tire au
 * hasard a la creation et n'est conserve nulle part : il n'existe aucune
 * combinaison a deviner, parce qu'il n'y a aucune combinaison. C'est important
 * — un compte de service qui garde les droits de parler a tout le monde serait
 * une belle porte d'entree s'il etait ouvrable.
 *
 * Il n'est pas administrateur et n'appartient a aucune agence. NOEL emprunte
 * les droits de la personne a qui il parle (voir NoelCarte) ; les siens
 * propres ne servent qu'a poser un message dans un fil.
 */
final class NoelCompte
{
    public const EMAIL = 'noel@labelleporte.ci';
    public const NOM = 'NOEL';

    private static ?int $id = null;

    /**
     * L'identifiant de NOEL, cree a la premiere utilisation s'il manque.
     *
     * On le cree paresseusement plutot que dans MigrationRunner : run() passe
     * a chaque requete HTTP, et deux pertes de donnees sont deja venues d'y
     * avoir mis des ecritures. Ici, la ligne n'est touchee que le jour ou
     * quelqu'un parle a NOEL pour la premiere fois.
     */
    public static function id(?PDO $pdo = null): ?int
    {
        if (self::$id !== null) {
            return self::$id;
        }

        $pdo ??= Database::getConnection();

        $existant = self::chercher($pdo);

        if ($existant !== null) {
            return self::$id = $existant;
        }

        return self::$id = self::creer($pdo);
    }

    private static function chercher(PDO $pdo): ?int
    {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => self::EMAIL]);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (Throwable $e) {
            error_log('[NOEL compte] lecture impossible : ' . $e->getMessage());

            return null;
        }
    }

    private static function creer(PDO $pdo): ?int
    {
        try {
            /*
             * Un mot de passe tire au hasard, jamais conserve ni affiche.
             * Le compte doit exister et rester ferme : la messagerie exige un
             * utilisateur actif pour accepter un membre, mais personne ne doit
             * pouvoir ouvrir une session avec.
             */
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, status, is_admin, created_at)
                VALUES (:nom, :email, :hash, 'active', 0, NOW())
            ");
            $stmt->execute([
                'nom' => self::NOM,
                'email' => self::EMAIL,
                'hash' => $hash,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            /*
             * Deux requetes simultanees ont pu creer la ligne en meme temps :
             * on relit avant d'abandonner, l'email etant unique.
             */
            error_log('[NOEL compte] creation impossible : ' . $e->getMessage());

            return self::chercher($pdo);
        }
    }

    /** Ce message vient-il de NOEL lui-meme ? Sert a ne pas se repondre. */
    public static function estNoel(?int $userId, ?PDO $pdo = null): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }

        return $userId === self::id($pdo);
    }

    /** Pour les tests : oublier l'identifiant retenu. */
    public static function oublier(): void
    {
        self::$id = null;
    }
}
