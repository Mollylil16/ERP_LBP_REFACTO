<?php

declare(strict_types=1);

namespace App\Repositories\Messagerie;

use PDO;
use PDOException;
use Throwable;

/**
 * Tables msg_* sous MySQL / MariaDB.
 *
 * La connexion travaille en requêtes préparées natives
 * (ATTR_EMULATE_PREPARES à false) : un même paramètre nommé ne peut pas
 * figurer deux fois dans une requête. Chaque répétition porte donc son
 * propre nom (:u, :u2...) — c'est le piège déjà corrigé dans six fichiers
 * du CRM.
 *
 * Le relevé (nouveautes) passe toutes les 3 secondes : ses requêtes partent
 * toutes des adhésions de l'utilisateur (idx_msg_membre_user) et ne lisent
 * les messages que par (conversation_id, id) ou par maj_at.
 */
final class MessagerieRepository implements MessagerieDepotInterface
{
    private const COLONNES_MESSAGE = 'm.id, m.conversation_id, m.auteur_id, u.full_name AS auteur_nom, m.texte,
        m.reponse_a_id, m.transfere_de_id, m.modifie_at, m.supprime_at, m.supprime_par, m.created_at';

    public function __construct(private PDO $pdo)
    {
    }

    // ------------------------------------------------------------------
    // Utilisateurs
    // ------------------------------------------------------------------

    public function utilisateur(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, full_name AS nom, status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ligne) {
            return null;
        }

        return ['id' => (int) $ligne['id'], 'nom' => (string) $ligne['nom'], 'actif' => $ligne['status'] === 'active'];
    }

    public function annuaire(int $saufId, string $recherche, int $limite): array
    {
        $filtre = '';
        $parametres = ['moi' => $saufId];
        if ($recherche !== '') {
            $filtre = 'AND u.full_name LIKE :motif';
            $parametres['motif'] = '%' . addcslashes($recherche, '%_\\') . '%';
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.full_name AS nom, s.name AS agence, f.name AS fonction
            FROM users u
            LEFT JOIN company_sites s ON s.id = u.agence_id
            LEFT JOIN rh_employees e ON e.id = u.rh_employee_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            WHERE u.status = 'active'
              AND u.id <> :moi
              {$filtre}
            ORDER BY u.full_name
            LIMIT " . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function idsActifs(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($ids, 'i');
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE status = 'active' AND id IN ({$marques})");
        $stmt->execute($parametres);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // ------------------------------------------------------------------
    // Discussions et membres
    // ------------------------------------------------------------------

    public function conversation(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM msg_conversations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function conversationDirecte(string $cle): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM msg_conversations WHERE cle_directe = :cle LIMIT 1');
        $stmt->execute(['cle' => $cle]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function creerConversation(array $donnees): int
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO msg_conversations (type, nom, description, cle_directe, createur_id, created_at, maj_at)
                VALUES (:type, :nom, :description, :cle, :createur, :cree, :maj)
            ');
            $stmt->execute([
                'type' => $donnees['type'],
                'nom' => $donnees['nom'],
                'description' => $donnees['description'],
                'cle' => $donnees['cle_directe'],
                'createur' => $donnees['createur_id'],
                'cree' => $donnees['created_at'],
                'maj' => $donnees['created_at'],
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            // Les deux collègues ont ouvert la discussion au même instant :
            // l'unique sur cle_directe a joué, on reprend celle de l'autre.
            if ($e->getCode() === '23000' && !empty($donnees['cle_directe'])) {
                $existante = $this->conversationDirecte((string) $donnees['cle_directe']);
                if ($existante !== null) {
                    return $existante;
                }
            }
            throw $e;
        }
    }

    public function modifierConversation(int $id, array $champs, string $maintenant): void
    {
        $sets = ['maj_at = :maj'];
        $parametres = ['maj' => $maintenant, 'id' => $id];
        foreach (['nom', 'description', 'type', 'photo'] as $colonne) {
            if (array_key_exists($colonne, $champs)) {
                $sets[] = "{$colonne} = :{$colonne}";
                $parametres[$colonne] = $champs[$colonne];
            }
        }

        $stmt = $this->pdo->prepare('UPDATE msg_conversations SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($parametres);
    }

    public function membre(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM msg_membres WHERE conversation_id = :c AND user_id = :u LIMIT 1');
        $stmt->execute(['c' => $conversationId, 'u' => $userId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function membresActifs(int $conversationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT mb.conversation_id, mb.user_id, mb.role, mb.rejoint_at, mb.dernier_message_lu_id, mb.lu_at,
                   u.full_name AS nom, (u.status = 'active') AS actif
            FROM msg_membres mb
            JOIN users u ON u.id = mb.user_id
            WHERE mb.conversation_id = :c
              AND mb.quitte_at IS NULL
            ORDER BY (mb.role = 'admin') DESC, u.full_name
        ");
        $stmt->execute(['c' => $conversationId]);

        return array_map(static function (array $l): array {
            $l['actif'] = (bool) $l['actif'];

            return $l;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function ajouterMembre(int $conversationId, int $userId, string $role, string $maintenant): void
    {
        $dernier = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM msg_messages WHERE conversation_id = :c');
        $dernier->execute(['c' => $conversationId]);
        $dernierId = (int) $dernier->fetchColumn();

        if ($this->membre($conversationId, $userId) === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO msg_membres (conversation_id, user_id, role, rejoint_at, dernier_message_lu_id, lu_at)
                VALUES (:c, :u, :role, :rejoint, :lu, :lu_at)
            ');
        } else {
            // Ancien membre réintégré : réglages remis à zéro, historique lu.
            $stmt = $this->pdo->prepare('
                UPDATE msg_membres
                SET role = :role, rejoint_at = :rejoint, quitte_at = NULL,
                    dernier_message_lu_id = GREATEST(dernier_message_lu_id, :lu), lu_at = :lu_at,
                    sourdine = 0, archivee = 0
                WHERE conversation_id = :c AND user_id = :u
            ');
        }

        $stmt->execute([
            'c' => $conversationId,
            'u' => $userId,
            'role' => $role,
            'rejoint' => $maintenant,
            'lu' => $dernierId,
            'lu_at' => $maintenant,
        ]);
    }

    public function retirerMembre(int $conversationId, int $userId, string $maintenant): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE msg_membres SET quitte_at = :q, role = \'membre\'
            WHERE conversation_id = :c AND user_id = :u AND quitte_at IS NULL
        ');
        $stmt->execute(['q' => $maintenant, 'c' => $conversationId, 'u' => $userId]);
    }

    public function changerRole(int $conversationId, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare('UPDATE msg_membres SET role = :role WHERE conversation_id = :c AND user_id = :u');
        $stmt->execute(['role' => $role, 'c' => $conversationId, 'u' => $userId]);
    }

    public function reglerMembre(int $conversationId, int $userId, string $reglage, bool $valeur): void
    {
        $colonne = $reglage === 'sourdine' ? 'sourdine' : 'archivee';
        $stmt = $this->pdo->prepare("UPDATE msg_membres SET {$colonne} = :v WHERE conversation_id = :c AND user_id = :u");
        $stmt->execute(['v' => $valeur ? 1 : 0, 'c' => $conversationId, 'u' => $userId]);
    }

    public function conversationsDe(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, mb.role, mb.sourdine, mb.archivee, mb.dernier_message_lu_id
            FROM msg_membres mb
            JOIN msg_conversations c ON c.id = mb.conversation_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
        ');
        $stmt->execute(['u' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function derniersMessages(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($conversationIds, 'c');
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM (
                SELECT MAX(id) AS id FROM msg_messages WHERE conversation_id IN (' . $marques . ') GROUP BY conversation_id
            ) d
            JOIN msg_messages m ON m.id = d.id
            LEFT JOIN users u ON u.id = m.auteur_id
        ');
        $stmt->execute($parametres);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resultat[(int) $ligne['conversation_id']] = $ligne;
        }

        return $resultat;
    }

    public function correspondantsDirects(array $conversationIds, int $userId): array
    {
        if ($conversationIds === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($conversationIds, 'c');
        $parametres['moi'] = $userId;
        $stmt = $this->pdo->prepare("
            SELECT mb.conversation_id, u.id, u.full_name AS nom, (u.status = 'active') AS actif
            FROM msg_membres mb
            JOIN msg_conversations c ON c.id = mb.conversation_id AND c.type = 'direct'
            JOIN users u ON u.id = mb.user_id
            WHERE mb.conversation_id IN ({$marques})
              AND mb.user_id <> :moi
        ");
        $stmt->execute($parametres);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $ligne['actif'] = (bool) $ligne['actif'];
            $resultat[(int) $ligne['conversation_id']] = $ligne;
        }

        return $resultat;
    }

    public function nonLus(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.conversation_id, COUNT(*) AS n
            FROM msg_membres mb
            JOIN msg_messages m ON m.conversation_id = mb.conversation_id AND m.id > mb.dernier_message_lu_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
              AND m.auteur_id <> :u2
              AND m.supprime_at IS NULL
            GROUP BY m.conversation_id
        ');
        $stmt->execute(['u' => $userId, 'u2' => $userId]);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resultat[(int) $ligne['conversation_id']] = (int) $ligne['n'];
        }

        return $resultat;
    }

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    public function message(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_messages m
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function messagesParIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($ids, 'i');
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_messages m
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.id IN (' . $marques . ')
        ');
        $stmt->execute($parametres);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resultat[(int) $ligne['id']] = $ligne;
        }

        return $resultat;
    }

    public function messages(int $conversationId, int $avant, int $limite): array
    {
        $parametres = ['c' => $conversationId];
        $borne = '';
        if ($avant > 0) {
            $borne = 'AND m.id < :avant';
            $parametres['avant'] = $avant;
        }

        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_messages m
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.conversation_id = :c ' . $borne . '
            ORDER BY m.id DESC
            LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function compterEnvoisDepuis(int $userId, string $depuis): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM msg_messages WHERE auteur_id = :u AND created_at >= :d');
        $stmt->execute(['u' => $userId, 'd' => $depuis]);

        return (int) $stmt->fetchColumn();
    }

    public function ajouterMessage(array $donnees, array $pieces = []): int
    {
        return $this->transaction(function () use ($donnees, $pieces): int {
            $stmt = $this->pdo->prepare('
                INSERT INTO msg_messages (conversation_id, auteur_id, texte, reponse_a_id, transfere_de_id, created_at)
                VALUES (:c, :a, :t, :r, :tr, :cree)
            ');
            $stmt->execute([
                'c' => $donnees['conversation_id'],
                'a' => $donnees['auteur_id'],
                't' => $donnees['texte'],
                'r' => $donnees['reponse_a_id'],
                'tr' => $donnees['transfere_de_id'],
                'cree' => $donnees['created_at'],
            ]);
            $id = (int) $this->pdo->lastInsertId();

            if ($pieces !== []) {
                $piece = $this->pdo->prepare('
                    INSERT INTO msg_pieces_jointes (message_id, fichier_id, nom_origine, legende, created_at)
                    VALUES (:m, :f, :n, :l, :d)
                ');
                foreach ($pieces as $p) {
                    $piece->execute([
                        'm' => $id,
                        'f' => $p['fichier_id'],
                        'n' => $p['nom_origine'],
                        'l' => $p['legende'],
                        'd' => $donnees['created_at'],
                    ]);
                }
            }

            $this->pdo->prepare('UPDATE msg_conversations SET dernier_message_at = :d WHERE id = :c')
                ->execute(['d' => $donnees['created_at'], 'c' => $donnees['conversation_id']]);

            // L'auteur a lu ce qu'il vient d'écrire.
            $this->pdo->prepare('
                UPDATE msg_membres SET dernier_message_lu_id = GREATEST(dernier_message_lu_id, :id), lu_at = :d
                WHERE conversation_id = :c AND user_id = :u
            ')->execute(['id' => $id, 'd' => $donnees['created_at'], 'c' => $donnees['conversation_id'], 'u' => $donnees['auteur_id']]);

            return $id;
        });
    }

    public function modifierMessage(int $id, string $nouveauTexte, string $ancienTexte, int $parId, string $maintenant): void
    {
        $this->transaction(function () use ($id, $nouveauTexte, $ancienTexte, $parId, $maintenant): int {
            $this->journal($id, 'modification', $ancienTexte, $parId, $maintenant);
            $this->pdo->prepare('UPDATE msg_messages SET texte = :t, modifie_at = :m, maj_at = :m2 WHERE id = :id')
                ->execute(['t' => $nouveauTexte, 'm' => $maintenant, 'm2' => $maintenant, 'id' => $id]);
            // Le texte d'un message à fichier en est la légende : le classeur
            // la cherche dans la pièce, qui doit suivre.
            $this->pdo->prepare('UPDATE msg_pieces_jointes SET legende = :l WHERE message_id = :id')
                ->execute(['l' => $nouveauTexte === '' ? null : $nouveauTexte, 'id' => $id]);

            return $id;
        });
    }

    public function supprimerMessage(int $id, string $ancienTexte, int $parId, string $maintenant): void
    {
        $this->transaction(function () use ($id, $ancienTexte, $parId, $maintenant): int {
            $this->journal($id, 'suppression', $ancienTexte, $parId, $maintenant);
            $this->pdo->prepare('
                UPDATE msg_messages SET supprime_at = :s, supprime_par = :p, maj_at = :m
                WHERE id = :id AND supprime_at IS NULL
            ')->execute(['s' => $maintenant, 'p' => $parId, 'm' => $maintenant, 'id' => $id]);
            $this->pdo->prepare('DELETE FROM msg_epingles WHERE message_id = :id')->execute(['id' => $id]);

            return $id;
        });
    }

    public function marquerLu(int $conversationId, int $userId, int $jusquA, string $maintenant): void
    {
        // Jamais en arrière, jamais au-delà du dernier message de la discussion.
        $stmt = $this->pdo->prepare('
            UPDATE msg_membres
            SET dernier_message_lu_id = LEAST(:j, (SELECT COALESCE(MAX(id), 0) FROM msg_messages WHERE conversation_id = :c2)),
                lu_at = :m
            WHERE conversation_id = :c
              AND user_id = :u
              AND quitte_at IS NULL
              AND dernier_message_lu_id < LEAST(:j2, (SELECT COALESCE(MAX(id), 0) FROM msg_messages WHERE conversation_id = :c3))
        ');
        $stmt->execute([
            'j' => $jusquA,
            'c2' => $conversationId,
            'm' => $maintenant,
            'c' => $conversationId,
            'u' => $userId,
            'j2' => $jusquA,
            'c3' => $conversationId,
        ]);
    }

    public function luPar(int $conversationId, int $messageId, int $auteurId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT mb.user_id, u.full_name AS nom, mb.lu_at
            FROM msg_membres mb
            JOIN users u ON u.id = mb.user_id
            WHERE mb.conversation_id = :c
              AND mb.quitte_at IS NULL
              AND mb.user_id <> :a
              AND mb.dernier_message_lu_id >= :m
            ORDER BY u.full_name
        ');
        $stmt->execute(['c' => $conversationId, 'a' => $auteurId, 'm' => $messageId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rechercher(array $conversationIds, array $mots, bool $pleinTexte, int $limite): array
    {
        if ($conversationIds === [] || $mots === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($conversationIds, 'c');

        if ($pleinTexte) {
            $condition = 'MATCH(m.texte) AGAINST (:requete IN BOOLEAN MODE)';
            $parametres['requete'] = implode(' ', array_map(static fn(string $mot): string => '+' . $mot . '*', $mots));
        } else {
            $conditions = [];
            foreach (array_values($mots) as $i => $mot) {
                $conditions[] = "m.texte LIKE :mot{$i}";
                $parametres["mot{$i}"] = '%' . addcslashes($mot, '%_\\') . '%';
            }
            $condition = implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_messages m
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.conversation_id IN (' . $marques . ')
              AND m.supprime_at IS NULL
              AND ' . $condition . '
            ORDER BY m.id DESC
            LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Épinglés
    // ------------------------------------------------------------------

    public function epingles(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . ', e.created_at AS epingle_at
            FROM msg_epingles e
            JOIN msg_messages m ON m.id = e.message_id
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE e.conversation_id = :c
            ORDER BY e.created_at, e.id
        ');
        $stmt->execute(['c' => $conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function epingler(int $conversationId, int $messageId, int $parId, string $maintenant): void
    {
        $this->pdo->prepare('
            INSERT IGNORE INTO msg_epingles (conversation_id, message_id, epingle_par, created_at)
            VALUES (:c, :m, :p, :d)
        ')->execute(['c' => $conversationId, 'm' => $messageId, 'p' => $parId, 'd' => $maintenant]);
    }

    public function desepingler(int $conversationId, int $messageId): void
    {
        $this->pdo->prepare('DELETE FROM msg_epingles WHERE conversation_id = :c AND message_id = :m')
            ->execute(['c' => $conversationId, 'm' => $messageId]);
    }

    // ------------------------------------------------------------------
    // Fichiers et pièces jointes
    // ------------------------------------------------------------------

    private const COLONNES_PIECE = 'pj.id, pj.message_id, pj.fichier_id, pj.nom_origine, pj.legende, pj.created_at,
        f.mime, f.categorie, f.extension, f.taille, f.largeur, f.hauteur, f.chemin_miniature';

    /** Tri du classeur => colonnes SQL ; la clé vient d'une liste fermée, jamais de la saisie. */
    private const TRIS_DOCUMENTS = [
        'nom' => ['pj.nom_origine'],
        'date' => ['pj.created_at'],
        'taille' => ['f.taille'],
        'expediteur' => ['u.full_name'],
        'type' => ['f.categorie', 'f.extension'],
    ];

    public function fichierParEmpreinte(string $empreinte): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM msg_fichiers WHERE empreinte = :e LIMIT 1');
        $stmt->execute(['e' => $empreinte]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function enregistrerFichier(array $donnees): array
    {
        try {
            $this->pdo->prepare('
                INSERT INTO msg_fichiers (empreinte, chemin, chemin_miniature, mime, categorie, extension,
                    taille, taille_miniature, largeur, hauteur, created_at)
                VALUES (:e, :c, :cm, :mime, :cat, :ext, :t, :tm, :l, :h, :d)
            ')->execute([
                'e' => $donnees['empreinte'],
                'c' => $donnees['chemin'],
                'cm' => $donnees['chemin_miniature'],
                'mime' => $donnees['mime'],
                'cat' => $donnees['categorie'],
                'ext' => $donnees['extension'],
                't' => $donnees['taille'],
                'tm' => $donnees['taille_miniature'],
                'l' => $donnees['largeur'],
                'h' => $donnees['hauteur'],
                'd' => $donnees['created_at'],
            ]);
        } catch (PDOException $e) {
            // Le même contenu envoyé au même instant par deux postes :
            // l'unique sur l'empreinte a joué, c'est l'autre copie qui reste.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        $ligne = $this->fichierParEmpreinte((string) $donnees['empreinte']);
        if ($ligne === null) {
            throw new \RuntimeException('Fichier de messagerie introuvable après enregistrement.');
        }

        return $ligne;
    }

    public function piecesDesMessages(array $messageIds, int $userId): array
    {
        if ($messageIds === []) {
            return [];
        }

        [$marques, $parametres] = $this->liste($messageIds, 'm');
        $parametres['u'] = $userId;
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_PIECE . ', (fav.id IS NOT NULL) AS favori
            FROM msg_pieces_jointes pj
            JOIN msg_fichiers f ON f.id = pj.fichier_id
            LEFT JOIN msg_favoris fav ON fav.piece_id = pj.id AND fav.user_id = :u
            WHERE pj.message_id IN (' . $marques . ')
            ORDER BY pj.id
        ');
        $stmt->execute($parametres);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $ligne['favori'] = (bool) $ligne['favori'];
            $resultat[(int) $ligne['message_id']][] = $ligne;
        }

        return $resultat;
    }

    public function piece(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_PIECE . ', f.chemin, m.conversation_id, m.supprime_at
            FROM msg_pieces_jointes pj
            JOIN msg_fichiers f ON f.id = pj.fichier_id
            JOIN msg_messages m ON m.id = pj.message_id
            WHERE pj.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function marquerFavori(int $userId, int $pieceId, bool $actif, string $maintenant): void
    {
        if ($actif) {
            $this->pdo->prepare('INSERT IGNORE INTO msg_favoris (user_id, piece_id, created_at) VALUES (:u, :p, :d)')
                ->execute(['u' => $userId, 'p' => $pieceId, 'd' => $maintenant]);

            return;
        }

        $this->pdo->prepare('DELETE FROM msg_favoris WHERE user_id = :u AND piece_id = :p')
            ->execute(['u' => $userId, 'p' => $pieceId]);
    }

    public function documents(int $userId, array $filtres, int $limite, int $decalage): array
    {
        // Marques positionnelles, l'utilisateur figurant deux fois : d'abord
        // dans la jointure du favori, puis dans la condition d'adhésion.
        $parametres = [$userId, $userId];
        $conditions = ['mb.user_id = ?', 'mb.quitte_at IS NULL', 'm.supprime_at IS NULL'];

        if (!empty($filtres['conversation_id'])) {
            $conditions[] = 'm.conversation_id = ?';
            $parametres[] = (int) $filtres['conversation_id'];
        }
        if (!empty($filtres['expediteur_id'])) {
            $conditions[] = 'm.auteur_id = ?';
            $parametres[] = (int) $filtres['expediteur_id'];
        }
        if (!empty($filtres['categorie'])) {
            $conditions[] = 'f.categorie = ?';
            $parametres[] = (string) $filtres['categorie'];
        }
        if (!empty($filtres['du'])) {
            $conditions[] = 'pj.created_at >= ?';
            $parametres[] = (string) $filtres['du'];
        }
        if (!empty($filtres['avant'])) {
            $conditions[] = 'pj.created_at < ?';
            $parametres[] = (string) $filtres['avant'];
        }
        if (!empty($filtres['favoris'])) {
            $conditions[] = 'fav.id IS NOT NULL';
        }
        if (($filtres['q'] ?? '') !== '') {
            $motif = '%' . addcslashes((string) $filtres['q'], '%_\\') . '%';
            $conditions[] = '(pj.nom_origine LIKE ? OR pj.legende LIKE ?)';
            $parametres[] = $motif;
            $parametres[] = $motif;
        }

        $depuis = '
            FROM msg_membres mb
            JOIN msg_messages m ON m.conversation_id = mb.conversation_id
            JOIN msg_pieces_jointes pj ON pj.message_id = m.id
            JOIN msg_fichiers f ON f.id = pj.fichier_id
            JOIN msg_conversations c ON c.id = m.conversation_id
            LEFT JOIN users u ON u.id = m.auteur_id
            LEFT JOIN msg_favoris fav ON fav.piece_id = pj.id AND fav.user_id = ?
            WHERE ' . implode(' AND ', $conditions);

        $total = $this->pdo->prepare('SELECT COUNT(*) ' . $depuis);
        $total->execute($parametres);

        $sens = ($filtres['ordre'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $ordre = [];
        foreach (self::TRIS_DOCUMENTS[$filtres['tri'] ?? 'date'] ?? self::TRIS_DOCUMENTS['date'] as $colonne) {
            $ordre[] = $colonne . ' ' . $sens;
        }
        $ordre[] = 'pj.id ' . $sens;

        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_PIECE . ', (fav.id IS NOT NULL) AS favori,
                   m.conversation_id, c.type AS conversation_type, c.nom AS conversation_nom,
                   m.auteur_id, u.full_name AS auteur_nom
            ' . $depuis . '
            ORDER BY ' . implode(', ', $ordre) . '
            LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $decalage)
        );
        $stmt->execute($parametres);

        return [
            'lignes' => array_map(static function (array $l): array {
                $l['favori'] = (bool) $l['favori'];

                return $l;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => (int) $total->fetchColumn(),
        ];
    }

    public function espaceFichiers(): array
    {
        $total = $this->pdo->query('
            SELECT COALESCE(SUM(taille + taille_miniature), 0) AS octets, COUNT(*) AS fichiers FROM msg_fichiers
        ')->fetch(PDO::FETCH_ASSOC) ?: ['octets' => 0, 'fichiers' => 0];

        $pieces = $this->pdo->query('
            SELECT COUNT(*) AS pieces, COALESCE(SUM(f.taille + f.taille_miniature), 0) AS octets
            FROM msg_pieces_jointes pj JOIN msg_fichiers f ON f.id = pj.fichier_id
        ')->fetch(PDO::FETCH_ASSOC) ?: ['pieces' => 0, 'octets' => 0];

        $parMois = $this->pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, SUM(taille + taille_miniature) AS octets, COUNT(*) AS fichiers
            FROM msg_fichiers
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mois DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $parCategorie = $this->pdo->query('
            SELECT categorie, SUM(taille + taille_miniature) AS octets, COUNT(*) AS fichiers
            FROM msg_fichiers
            GROUP BY categorie
            ORDER BY octets DESC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $entiers = static fn(array $lignes, string $cle): array => array_map(static fn(array $l): array => [
            $cle => (string) $l[$cle],
            'octets' => (int) $l['octets'],
            'fichiers' => (int) $l['fichiers'],
        ], $lignes);

        return [
            'octets' => (int) $total['octets'],
            'fichiers' => (int) $total['fichiers'],
            'pieces' => (int) $pieces['pieces'],
            'octets_sans_dedoublonnage' => (int) $pieces['octets'],
            'par_mois' => $entiers($parMois, 'mois'),
            'par_categorie' => $entiers($parCategorie, 'categorie'),
        ];
    }

    // ------------------------------------------------------------------
    // Relevé
    // ------------------------------------------------------------------

    public function curseurMessages(int $userId): int
    {
        // Sous-requête corrélée : un seul saut dans (conversation_id, id) par discussion.
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(MAX((SELECT MAX(m.id) FROM msg_messages m WHERE m.conversation_id = mb.conversation_id)), 0)
            FROM msg_membres mb
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
        ');
        $stmt->execute(['u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function nouveauxMessages(int $userId, int $depuis, int $limite): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_membres mb
            JOIN msg_messages m ON m.conversation_id = mb.conversation_id AND m.id > :depuis
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
            ORDER BY m.id
            LIMIT ' . max(1, $limite)
        );
        $stmt->execute(['depuis' => $depuis, 'u' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function messagesRetouches(int $userId, int $jusquA, string $depuis): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . self::COLONNES_MESSAGE . '
            FROM msg_messages m
            JOIN msg_membres mb ON mb.conversation_id = m.conversation_id AND mb.user_id = :u AND mb.quitte_at IS NULL
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.maj_at >= :d
              AND m.id <= :j
            ORDER BY m.id
            LIMIT 200
        ');
        $stmt->execute(['u' => $userId, 'd' => $depuis, 'j' => $jusquA]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lecturesDepuis(int $userId, string $depuis): array
    {
        $stmt = $this->pdo->prepare('
            SELECT o.conversation_id, o.user_id, o.dernier_message_lu_id
            FROM msg_membres mb
            JOIN msg_membres o ON o.conversation_id = mb.conversation_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
              AND o.user_id <> :u2
              AND o.quitte_at IS NULL
              AND o.lu_at >= :d
        ');
        $stmt->execute(['u' => $userId, 'u2' => $userId, 'd' => $depuis]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function conversationsChangees(int $userId, string $depuis): array
    {
        $modifiees = $this->pdo->prepare('
            SELECT c.id
            FROM msg_membres mb
            JOIN msg_conversations c ON c.id = mb.conversation_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
              AND (c.maj_at >= :d OR mb.rejoint_at >= :d2)
        ');
        $modifiees->execute(['u' => $userId, 'd' => $depuis, 'd2' => $depuis]);

        $quittees = $this->pdo->prepare('SELECT conversation_id FROM msg_membres WHERE user_id = :u AND quitte_at >= :d');
        $quittees->execute(['u' => $userId, 'd' => $depuis]);

        return [
            'modifiees' => array_map('intval', $modifiees->fetchAll(PDO::FETCH_COLUMN)),
            'quittees' => array_map('intval', $quittees->fetchAll(PDO::FETCH_COLUMN)),
        ];
    }

    public function presenceCorrespondants(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT o.user_id, (u.status = 'active') AS actif, p.vu_at,
                   COALESCE(p.presence_masquee, 0) AS presence_masquee
            FROM msg_membres mb
            JOIN msg_membres o ON o.conversation_id = mb.conversation_id AND o.quitte_at IS NULL AND o.user_id <> :u2
            JOIN users u ON u.id = o.user_id
            LEFT JOIN msg_presence p ON p.user_id = o.user_id
            WHERE mb.user_id = :u
              AND mb.quitte_at IS NULL
        ");
        $stmt->execute(['u2' => $userId, 'u' => $userId]);

        return array_map(static function (array $l): array {
            $l['actif'] = (bool) $l['actif'];
            $l['presence_masquee'] = (bool) $l['presence_masquee'];

            return $l;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ------------------------------------------------------------------
    // Présence, préférences, Direction
    // ------------------------------------------------------------------

    public function toucherPresence(int $userId, string $maintenant): void
    {
        $this->pdo->prepare('
            INSERT INTO msg_presence (user_id, vu_at) VALUES (:u, :v)
            ON DUPLICATE KEY UPDATE vu_at = :v2
        ')->execute(['u' => $userId, 'v' => $maintenant, 'v2' => $maintenant]);
    }

    public function preferences(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT presence_masquee, son_actif, apercu_masque FROM msg_presence WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: ['presence_masquee' => 0, 'son_actif' => 1, 'apercu_masque' => 0];
    }

    public function enregistrerPreferences(int $userId, array $preferences): void
    {
        $this->pdo->prepare('
            INSERT INTO msg_presence (user_id, presence_masquee, son_actif, apercu_masque)
            VALUES (:u, :pm, :sa, :am)
            ON DUPLICATE KEY UPDATE presence_masquee = :pm2, son_actif = :sa2, apercu_masque = :am2
        ')->execute([
            'u' => $userId,
            'pm' => $preferences['presence_masquee'] ? 1 : 0,
            'sa' => $preferences['son_actif'] ? 1 : 0,
            'am' => $preferences['apercu_masque'] ? 1 : 0,
            'pm2' => $preferences['presence_masquee'] ? 1 : 0,
            'sa2' => $preferences['son_actif'] ? 1 : 0,
            'am2' => $preferences['apercu_masque'] ? 1 : 0,
        ]);
    }

    public function journaliserAccesDirection(int $userId, int $conversationId, string $maintenant): void
    {
        $this->pdo->prepare('INSERT INTO msg_acces_direction (user_id, conversation_id, created_at) VALUES (:u, :c, :d)')
            ->execute(['u' => $userId, 'c' => $conversationId, 'd' => $maintenant]);
    }

    public function toutesConversations(string $recherche, string $type, int $limite): array
    {
        // Marques positionnelles : la base refuse un même :nom répété.
        $conditions = [];
        $parametres = [];
        if ($type !== '') {
            $conditions[] = 'c.type = ?';
            $parametres[] = $type;
        }
        if ($recherche !== '') {
            $motif = '%' . $recherche . '%';
            $conditions[] = '(c.nom LIKE ? OR EXISTS (
                SELECT 1 FROM msg_membres rm JOIN users ru ON ru.id = rm.user_id
                WHERE rm.conversation_id = c.id AND ru.full_name LIKE ?
            ))';
            $parametres[] = $motif;
            $parametres[] = $motif;
        }

        $stmt = $this->pdo->prepare('
            SELECT c.id, c.type, c.nom, c.created_at, c.dernier_message_at,
                (SELECT COUNT(*) FROM msg_messages m WHERE m.conversation_id = c.id) AS nb_messages,
                (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR \', \')
                   FROM msg_membres mb JOIN users u ON u.id = mb.user_id
                   WHERE mb.conversation_id = c.id AND mb.quitte_at IS NULL) AS participants
            FROM msg_conversations c
            ' . ($conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions)) . '
            ORDER BY COALESCE(c.dernier_message_at, c.created_at) DESC, c.id DESC
            LIMIT ' . max(1, $limite) . '
        ');
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accesDirectionRecents(int $limite): array
    {
        $stmt = $this->pdo->query('
            SELECT a.created_at, u.full_name AS lecteur, a.conversation_id, c.type, c.nom
            FROM msg_acces_direction a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN msg_conversations c ON c.id = a.conversation_id
            ORDER BY a.id DESC
            LIMIT ' . max(1, $limite) . '
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------

    private function journal(int $messageId, string $action, string $ancienTexte, int $parId, string $maintenant): void
    {
        $this->pdo->prepare('
            INSERT INTO msg_messages_journal (message_id, action, ancien_texte, auteur_id, created_at)
            VALUES (:m, :a, :t, :p, :d)
        ')->execute(['m' => $messageId, 'a' => $action, 't' => $ancienTexte, 'p' => $parId, 'd' => $maintenant]);
    }

    /**
     * @param array<int, int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private function liste(array $ids, string $prefixe): array
    {
        $marques = [];
        $parametres = [];
        foreach (array_values($ids) as $i => $id) {
            $marques[] = ':' . $prefixe . $i;
            $parametres[$prefixe . $i] = (int) $id;
        }

        return [implode(', ', $marques), $parametres];
    }

    private function transaction(callable $travail): int
    {
        $this->pdo->beginTransaction();
        try {
            $resultat = (int) $travail();
            $this->pdo->commit();

            return $resultat;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
