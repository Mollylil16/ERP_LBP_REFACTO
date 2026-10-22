<?php

declare(strict_types=1);

namespace App\Repositories\Messagerie;

/**
 * Accès aux tables msg_* de la messagerie interne.
 *
 * Abstrait pour que les règles du service (qui lit quoi, qui écrit où, le
 * délai de modification, la limite d'envoi) soient vérifiables sans base.
 * Les dates sont toujours fournies par le service, au format
 * « Y-m-d H:i:s » : la base ne décide jamais de l'heure.
 *
 * Convention des lignes renvoyées :
 *  - utilisateur : id, nom, actif (bool), agence, fonction ;
 *  - conversation : id, type, nom, description, photo, agence_id,
 *    automatique, createur_id, created_at, dernier_message_at ;
 *  - membre : conversation_id, user_id, role, rejoint_at, quitte_at,
 *    dernier_message_lu_id, sourdine, archivee (+ nom, actif pour la liste) ;
 *  - message : id, conversation_id, auteur_id, auteur_nom, texte,
 *    reponse_a_id, transfere_de_id, modifie_at, supprime_at, supprime_par,
 *    created_at.
 */
interface MessagerieDepotInterface
{
    // ------------------------------------------------------------------
    // Utilisateurs
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function utilisateur(int $id): ?array;

    /**
     * Collègues actifs, hors l'utilisateur lui-même, filtrés sur le nom.
     *
     * @return array<int, array<string, mixed>>
     */
    public function annuaire(int $saufId, string $recherche, int $limite): array;

    /**
     * Parmi ces identifiants, ceux des comptes actifs.
     *
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function idsActifs(array $ids): array;

    // ------------------------------------------------------------------
    // Discussions et membres
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function conversation(int $id): ?array;

    public function conversationDirecte(string $cle): ?int;

    /** @param array<string, mixed> $donnees */
    public function creerConversation(array $donnees): int;

    /** @param array<string, mixed> $champs nom, description, type, photo */
    public function modifierConversation(int $id, array $champs, string $maintenant): void;

    /** Adhésion, active ou passée. @return array<string, mixed>|null */
    public function membre(int $conversationId, int $userId): ?array;

    /**
     * Membres actifs (quitte_at nul), avec nom et état du compte.
     *
     * @return array<int, array<string, mixed>>
     */
    public function membresActifs(int $conversationId): array;

    /**
     * Ajoute un membre ou réintègre un ancien membre. Il arrive avec tout
     * l'historique déjà lu, pour ne pas hériter de centaines de non-lus.
     */
    public function ajouterMembre(int $conversationId, int $userId, string $role, string $maintenant): void;

    public function retirerMembre(int $conversationId, int $userId, string $maintenant): void;

    public function changerRole(int $conversationId, int $userId, string $role): void;

    /** Sourdine ou archive : $reglage vaut « sourdine » ou « archivee ». */
    public function reglerMembre(int $conversationId, int $userId, string $reglage, bool $valeur): void;

    /**
     * Discussions dont l'utilisateur est membre actif, avec son adhésion.
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversationsDe(int $userId): array;

    /**
     * Dernier message de chacune de ces discussions, indexé par discussion.
     *
     * @param array<int, int> $conversationIds
     * @return array<int, array<string, mixed>>
     */
    public function derniersMessages(array $conversationIds): array;

    /**
     * Correspondant de chaque discussion directe, indexé par discussion.
     *
     * @param array<int, int> $conversationIds
     * @return array<int, array<string, mixed>>
     */
    public function correspondantsDirects(array $conversationIds, int $userId): array;

    /**
     * Non-lus par discussion (messages des autres, non supprimés, au-delà
     * du dernier lu). Les discussions sans non-lu sont absentes.
     *
     * @return array<int, int>
     */
    public function nonLus(int $userId): array;

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function message(int $id): ?array;

    /**
     * Messages par identifiants, indexés par identifiant.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function messagesParIds(array $ids): array;

    /**
     * Page de messages, du plus ancien au plus récent, antérieurs à $avant
     * (0 : les plus récents).
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(int $conversationId, int $avant, int $limite): array;

    public function compterEnvoisDepuis(int $userId, string $depuis): int;

    /**
     * Enregistre le message et ses pièces jointes en une transaction, avance
     * dernier_message_at de la discussion et le dernier lu de l'auteur.
     *
     * @param array<string, mixed> $donnees
     * @param array<int, array{fichier_id:int, nom_origine:string, legende:?string}> $pieces
     */
    public function ajouterMessage(array $donnees, array $pieces = []): int;

    /** Nouveau texte + ancien au journal, en une transaction. */
    public function modifierMessage(int $id, string $nouveauTexte, string $ancienTexte, int $parId, string $maintenant): void;

    /** Pose supprime_at, journalise, retire l'épingle éventuelle. */
    public function supprimerMessage(int $id, string $ancienTexte, int $parId, string $maintenant): void;

    /** Avance le dernier lu, sans jamais reculer ni dépasser le dernier message. */
    public function marquerLu(int $conversationId, int $userId, int $jusquA, string $maintenant): void;

    /**
     * Membres actifs (hors auteur) dont le dernier lu atteint ce message.
     *
     * @return array<int, array<string, mixed>>
     */
    public function luPar(int $conversationId, int $messageId, int $auteurId): array;

    /**
     * Recherche plein texte (ou LIKE si $pleinTexte est faux) dans les
     * discussions données, messages supprimés exclus.
     *
     * @param array<int, int> $conversationIds
     * @param array<int, string> $mots
     * @return array<int, array<string, mixed>>
     */
    public function rechercher(array $conversationIds, array $mots, bool $pleinTexte, int $limite): array;

    // ------------------------------------------------------------------
    // Épinglés
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function epingles(int $conversationId): array;

    public function epingler(int $conversationId, int $messageId, int $parId, string $maintenant): void;

    public function desepingler(int $conversationId, int $messageId): void;

    // ------------------------------------------------------------------
    // Fichiers et pièces jointes
    // ------------------------------------------------------------------

    /** Le fichier déjà stocké pour ce contenu. @return array<string, mixed>|null */
    public function fichierParEmpreinte(string $empreinte): ?array;

    /**
     * Inscrit un fichier rangé et rend la ligne qui fait foi : si un envoi
     * concurrent du même contenu l'a précédé (unique sur l'empreinte), c'est
     * la sienne — à l'appelant d'effacer alors sa propre copie.
     *
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    public function enregistrerFichier(array $donnees): array;

    /**
     * Pièces de ces messages en une requête, avec le fichier et le favori de
     * l'utilisateur, indexées par message.
     *
     * Ligne : id, message_id, fichier_id, nom_origine, legende, created_at,
     * mime, categorie, extension, taille, largeur, hauteur, chemin_miniature,
     * favori (bool).
     *
     * @param array<int, int> $messageIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function piecesDesMessages(array $messageIds, int $userId): array;

    /**
     * Une pièce, son fichier et son message (conversation_id, supprime_at).
     *
     * @return array<string, mixed>|null
     */
    public function piece(int $id): ?array;

    public function marquerFavori(int $userId, int $pieceId, bool $actif, string $maintenant): void;

    /**
     * Le classeur : pièces des discussions dont l'utilisateur est membre
     * actif, messages supprimés exclus. Les filtres arrivent validés :
     * q, tri (nom|date|taille|expediteur|type), ordre (asc|desc),
     * conversation_id, expediteur_id, categorie, du et avant (bornes de
     * created_at, « Y-m-d H:i:s », la seconde exclue), favoris (bool).
     *
     * Ligne : celle de piecesDesMessages, plus conversation_id,
     * conversation_type, conversation_nom, auteur_id, auteur_nom.
     *
     * @param array<string, mixed> $filtres
     * @return array{lignes: array<int, array<string, mixed>>, total: int}
     */
    public function documents(int $userId, array $filtres, int $limite, int $decalage): array;

    /**
     * Ce que les fichiers occupent sur le disque, en tout, par mois et par
     * catégorie, et ce qu'ils occuperaient sans dédoublonnage.
     *
     * @return array{octets:int, fichiers:int, pieces:int, octets_sans_dedoublonnage:int,
     *     par_mois: array<int, array{mois:string, octets:int, fichiers:int}>,
     *     par_categorie: array<int, array{categorie:string, octets:int, fichiers:int}>}
     */
    public function espaceFichiers(): array;

    // ------------------------------------------------------------------
    // Relevé
    // ------------------------------------------------------------------

    /** Plus grand identifiant de message dans les discussions de l'utilisateur. */
    public function curseurMessages(int $userId): int;

    /**
     * Messages postérieurs à $depuis dans les discussions de l'utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nouveauxMessages(int $userId, int $depuis, int $limite): array;

    /**
     * Messages déjà connus (id ≤ $jusquA) modifiés ou supprimés depuis $depuis.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messagesRetouches(int $userId, int $jusquA, string $depuis): array;

    /**
     * Lectures des autres membres avancées depuis $depuis.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lecturesDepuis(int $userId, string $depuis): array;

    /**
     * Discussions renommées, réglées, rejointes ou quittées depuis $depuis.
     *
     * @return array{modifiees: array<int, int>, quittees: array<int, int>}
     */
    public function conversationsChangees(int $userId, string $depuis): array;

    /**
     * Présence des collègues qui partagent une discussion avec l'utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function presenceCorrespondants(int $userId): array;

    // ------------------------------------------------------------------
    // Présence, préférences, Direction
    // ------------------------------------------------------------------

    public function toucherPresence(int $userId, string $maintenant): void;

    /** @return array<string, mixed> presence_masquee, son_actif, apercu_masque */
    public function preferences(int $userId): array;

    /** @param array<string, bool> $preferences */
    public function enregistrerPreferences(int $userId, array $preferences): void;

    public function journaliserAccesDirection(int $userId, int $conversationId, string $maintenant): void;

    /**
     * Toutes les discussions, pour l'écran de la Direction : la plus récemment
     * active en tête. $recherche porte sur le nom du groupe ou d'un participant.
     *
     * @return array<int, array{id:int|string, type:string, nom:?string, created_at:string, dernier_message_at:?string, nb_messages:int|string, participants:?string}>
     */
    public function toutesConversations(string $recherche, string $type, int $limite): array;

    /**
     * Dernières ouvertures de discussions par la Direction.
     *
     * @return array<int, array{created_at:string, lecteur:?string, conversation_id:int|string, type:?string, nom:?string}>
     */
    public function accesDirectionRecents(int $limite): array;
}
