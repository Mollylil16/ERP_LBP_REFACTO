<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use App\Repositories\Messagerie\MessagerieDepotInterface;
use App\Services\Shared\AuditLogService;
use Closure;
use DateTimeImmutable;

/**
 * Règles de la messagerie interne, vérifiées côté serveur.
 *
 * Le service est lié à celui qui agit : son identifiant, et s'il appartient
 * à la Direction (DG ou administrateur). Tout ce que l'écran masque ou
 * grise est de nouveau contrôlé ici — un bouton caché ne protège rien.
 *
 * Qui lit : les membres actifs d'une discussion. La Direction peut ouvrir
 * n'importe laquelle, et chaque ouverture de ce genre est inscrite dans
 * msg_acces_direction et dans le journal d'audit chaîné. Elle lit sans
 * pouvoir écrire là où elle n'est pas membre.
 *
 * Une discussion qu'on ne peut pas lire répond « introuvable » et non
 * « interdit » : sinon on confirmerait qu'elle existe.
 */
final class MessagerieService
{
    public const TEXTE_MAX = 4000;
    public const DELAI_MODIFICATION_SECONDES = 900;
    public const ENVOIS_PAR_MINUTE = 30;
    public const MESSAGES_PAR_PAGE = 50;
    public const EPINGLES_MAX = 3;
    public const EN_LIGNE_SECONDES = 60;
    public const RELEVE_MAX = 200;
    public const NOM_GROUPE_MAX = 120;
    public const DESCRIPTION_MAX = 500;
    public const RECHERCHE_MAX = 50;

    /** Libellé rendu à la place d'un message supprimé. */
    public const LIBELLE_SUPPRIME = 'Message supprimé';

    private const FORMAT_DATE = 'Y-m-d H:i:s';

    private Closure $horloge;

    public function __construct(
        private MessagerieDepotInterface $depot,
        private int $utilisateurId,
        private bool $direction = false,
        ?Closure $horloge = null,
    ) {
        $this->horloge = $horloge ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    // ==================================================================
    // Discussions
    // ==================================================================

    /**
     * Les discussions de l'utilisateur, la plus récemment active en tête.
     *
     * Quatre requêtes quel que soit leur nombre : discussions, derniers
     * messages, correspondants des directes, non-lus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(): array
    {
        $lignes = $this->depot->conversationsDe($this->utilisateurId);
        if ($lignes === []) {
            return [];
        }

        $ids = array_map(static fn(array $l): int => (int) $l['id'], $lignes);
        $derniers = $this->depot->derniersMessages($ids);
        $correspondants = $this->depot->correspondantsDirects($ids, $this->utilisateurId);
        $nonLus = $this->depot->nonLus($this->utilisateurId);

        $resultat = [];
        foreach ($lignes as $ligne) {
            $id = (int) $ligne['id'];
            $dernier = $derniers[$id] ?? null;
            $correspondant = $correspondants[$id] ?? null;

            $resultat[] = $this->formaterConversation($ligne, $correspondant) + [
                'mon_role' => (string) $ligne['role'],
                'sourdine' => (bool) $ligne['sourdine'],
                'archivee' => (bool) $ligne['archivee'],
                'non_lus' => (int) ($nonLus[$id] ?? 0),
                'dernier_lu_id' => (int) $ligne['dernier_message_lu_id'],
                'dernier_message' => $dernier === null ? null : [
                    'id' => (int) $dernier['id'],
                    'auteur_id' => (int) $dernier['auteur_id'],
                    'auteur_nom' => (string) ($dernier['auteur_nom'] ?? ''),
                    'apercu' => $this->apercu($dernier),
                    'supprime' => $dernier['supprime_at'] !== null,
                    'created_at' => (string) $dernier['created_at'],
                ],
            ];
        }

        usort($resultat, static function (array $a, array $b): int {
            $da = (string) ($a['dernier_message_at'] ?? $a['created_at']);
            $db = (string) ($b['dernier_message_at'] ?? $b['created_at']);

            return [$db, $b['id']] <=> [$da, $a['id']];
        });

        return $resultat;
    }

    /**
     * Fiche d'une discussion : membres, épinglés et place de l'utilisateur.
     *
     * @return array<string, mixed>
     */
    public function detail(int $conversationId): array
    {
        [$conversation, $membre] = $this->lecture($conversationId, true);

        $membres = $this->depot->membresActifs($conversationId);
        $correspondant = null;
        if ($conversation['type'] === 'direct') {
            foreach ($membres as $m) {
                if ((int) $m['user_id'] !== $this->utilisateurId) {
                    $correspondant = ['id' => (int) $m['user_id'], 'nom' => (string) $m['nom'], 'actif' => (bool) $m['actif']];
                }
            }
        }

        return $this->formaterConversation($conversation, $correspondant) + [
            'mon_role' => $membre === null ? null : (string) $membre['role'],
            'membre' => $membre !== null,
            'acces_direction' => $membre === null,
            'peut_ecrire' => $this->peutEcrire($conversation, $membre, $membres),
            'peut_administrer' => $membre !== null && $membre['role'] === 'admin' && $conversation['type'] !== 'direct',
            'sourdine' => $membre !== null && (bool) $membre['sourdine'],
            'archivee' => $membre !== null && (bool) $membre['archivee'],
            'membres' => array_map(static fn(array $m): array => [
                'id' => (int) $m['user_id'],
                'nom' => (string) $m['nom'],
                'role' => (string) $m['role'],
                'actif' => (bool) $m['actif'],
                'rejoint_at' => (string) $m['rejoint_at'],
                // Jusqu'où chacun a lu : l'écran en tire les accusés « lu »
                // des messages déjà affichés, que le relevé ne redonne pas.
                'dernier_lu_id' => (int) ($m['dernier_message_lu_id'] ?? 0),
            ], $membres),
            'epingles' => $this->formaterMessages($this->depot->epingles($conversationId)),
        ];
    }

    /** Ouvre (ou retrouve) la discussion directe avec un collègue. */
    public function ouvrirDirecte(int $autreId): int
    {
        if ($autreId === $this->utilisateurId) {
            throw MessagerieException::invalide('Choisissez un autre collègue que vous-même.');
        }

        $autre = $this->depot->utilisateur($autreId);
        if ($autre === null || !$autre['actif']) {
            throw MessagerieException::invalide("Ce collègue n'a pas accès à la messagerie.");
        }

        $cle = min($autreId, $this->utilisateurId) . '-' . max($autreId, $this->utilisateurId);
        $existante = $this->depot->conversationDirecte($cle);
        $maintenant = $this->maintenant();

        if ($existante === null) {
            $existante = $this->depot->creerConversation([
                'type' => 'direct',
                'nom' => null,
                'description' => null,
                'cle_directe' => $cle,
                'createur_id' => $this->utilisateurId,
                'created_at' => $maintenant,
            ]);
        }

        // La discussion existait peut-être déjà sans l'un des deux (création
        // concurrente interrompue) : on s'assure que les deux y figurent.
        foreach ([$this->utilisateurId, $autreId] as $userId) {
            $membre = $this->depot->membre($existante, $userId);
            if ($membre === null || $membre['quitte_at'] !== null) {
                $this->depot->ajouterMembre($existante, $userId, 'membre', $maintenant);
            }
        }

        return $existante;
    }

    /**
     * Tout utilisateur peut créer un groupe ; il en devient administrateur.
     *
     * @param array<int, int> $membres
     */
    public function creerGroupe(string $nom, string $description, array $membres): int
    {
        $nom = $this->nomGroupe($nom);
        $description = $this->description($description);
        $maintenant = $this->maintenant();

        $id = $this->depot->creerConversation([
            'type' => 'groupe',
            'nom' => $nom,
            'description' => $description,
            'cle_directe' => null,
            'createur_id' => $this->utilisateurId,
            'created_at' => $maintenant,
        ]);

        $this->depot->ajouterMembre($id, $this->utilisateurId, 'admin', $maintenant);
        foreach ($this->collegesActifs($membres) as $userId) {
            $this->depot->ajouterMembre($id, $userId, 'membre', $maintenant);
        }

        return $id;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, int> les identifiants réellement ajoutés
     */
    public function ajouterMembres(int $conversationId, array $userIds): array
    {
        $this->administration($conversationId);
        $maintenant = $this->maintenant();

        $ajoutes = [];
        foreach ($this->collegesActifs($userIds) as $userId) {
            $membre = $this->depot->membre($conversationId, $userId);
            if ($membre !== null && $membre['quitte_at'] === null) {
                continue;
            }
            $this->depot->ajouterMembre($conversationId, $userId, 'membre', $maintenant);
            $ajoutes[] = $userId;
        }

        if ($ajoutes !== []) {
            $this->depot->modifierConversation($conversationId, [], $maintenant);
        }

        return $ajoutes;
    }

    public function retirerMembre(int $conversationId, int $userId): void
    {
        $this->administration($conversationId);

        if ($userId === $this->utilisateurId) {
            throw MessagerieException::invalide('Pour partir vous-même, quittez la discussion.');
        }

        $membre = $this->depot->membre($conversationId, $userId);
        if ($membre === null || $membre['quitte_at'] !== null) {
            throw MessagerieException::invalide("Ce collègue n'est pas membre de la discussion.");
        }

        $maintenant = $this->maintenant();
        $this->depot->retirerMembre($conversationId, $userId, $maintenant);
        $this->depot->modifierConversation($conversationId, [], $maintenant);
    }

    public function changerRole(int $conversationId, int $userId, string $role): void
    {
        $this->administration($conversationId);

        if (!in_array($role, ['membre', 'admin'], true)) {
            throw MessagerieException::invalide('Rôle inconnu : choisissez « membre » ou « admin ».');
        }

        $membre = $this->depot->membre($conversationId, $userId);
        if ($membre === null || $membre['quitte_at'] !== null) {
            throw MessagerieException::invalide("Ce collègue n'est pas membre de la discussion.");
        }
        if ($membre['role'] === $role) {
            return;
        }

        if ($role === 'membre' && $this->nombreAdmins($conversationId) <= 1) {
            throw MessagerieException::invalide('Une discussion de groupe garde au moins un administrateur.');
        }

        $this->depot->changerRole($conversationId, $userId, $role);
        $this->depot->modifierConversation($conversationId, [], $this->maintenant());
    }

    /**
     * Quitter un groupe. Le dernier administrateur qui part passe la main au
     * plus ancien membre restant, pour que le groupe reste administrable.
     */
    public function quitter(int $conversationId): void
    {
        [$conversation, $membre] = $this->adhesion($conversationId);

        if ($conversation['type'] === 'direct') {
            throw MessagerieException::invalide('Une discussion directe ne se quitte pas : archivez-la.');
        }

        $maintenant = $this->maintenant();

        if ($membre['role'] === 'admin' && $this->nombreAdmins($conversationId) <= 1) {
            $successeur = null;
            foreach ($this->depot->membresActifs($conversationId) as $autre) {
                if ((int) $autre['user_id'] === $this->utilisateurId || !$autre['actif']) {
                    continue;
                }
                if ($successeur === null || (string) $autre['rejoint_at'] < (string) $successeur['rejoint_at']) {
                    $successeur = $autre;
                }
            }
            if ($successeur !== null) {
                $this->depot->changerRole($conversationId, (int) $successeur['user_id'], 'admin');
            }
        }

        $this->depot->retirerMembre($conversationId, $this->utilisateurId, $maintenant);
        $this->depot->modifierConversation($conversationId, [], $maintenant);
    }

    public function renommer(int $conversationId, string $nom, ?string $description): void
    {
        $this->administration($conversationId);

        $champs = ['nom' => $this->nomGroupe($nom)];
        if ($description !== null) {
            $champs['description'] = $this->description($description);
        }

        $this->depot->modifierConversation($conversationId, $champs, $this->maintenant());
    }

    /** Passe un groupe en annonces (seuls les admins y écrivent), ou l'inverse. */
    public function passerEnAnnonces(int $conversationId, bool $annonces): void
    {
        [$conversation] = $this->administration($conversationId);

        $type = $annonces ? 'annonces' : 'groupe';
        if ($conversation['type'] === $type) {
            return;
        }

        $this->depot->modifierConversation($conversationId, ['type' => $type], $this->maintenant());
    }

    public function regler(int $conversationId, string $reglage, bool $valeur): void
    {
        if (!in_array($reglage, ['sourdine', 'archivee'], true)) {
            throw MessagerieException::invalide('Réglage inconnu.');
        }

        $this->adhesion($conversationId);
        $this->depot->reglerMembre($conversationId, $this->utilisateurId, $reglage, $valeur);
    }

    // ==================================================================
    // Messages
    // ==================================================================

    /**
     * Page de 50 messages, du plus ancien au plus récent. $avant = 0 ouvre
     * la discussion (et l'inscrit au registre si c'est la Direction hors
     * membres) ; $avant > 0 remonte l'historique.
     *
     * @return array{messages: array<int, array<string, mixed>>, plus_anciens: bool}
     */
    public function messages(int $conversationId, int $avant = 0): array
    {
        $this->lecture($conversationId, $avant <= 0);

        $lignes = $this->depot->messages($conversationId, max(0, $avant), self::MESSAGES_PAR_PAGE + 1);
        $plusAnciens = count($lignes) > self::MESSAGES_PAR_PAGE;
        if ($plusAnciens) {
            $lignes = array_slice($lignes, 1);
        }

        return ['messages' => $this->formaterMessages($lignes), 'plus_anciens' => $plusAnciens];
    }

    /** @return array<string, mixed> le message enregistré */
    public function envoyer(int $conversationId, string $texte, ?int $reponseA = null): array
    {
        $texte = $this->texte($texte);
        $this->ecriture($conversationId);
        $this->antiAbus();

        if ($reponseA !== null && $reponseA > 0) {
            $cible = $this->depot->message($reponseA);
            if ($cible === null || (int) $cible['conversation_id'] !== $conversationId) {
                throw MessagerieException::invalide('On ne répond qu’à un message de la même discussion.');
            }
        } else {
            $reponseA = null;
        }

        $id = $this->depot->ajouterMessage([
            'conversation_id' => $conversationId,
            'auteur_id' => $this->utilisateurId,
            'texte' => $texte,
            'reponse_a_id' => $reponseA,
            'transfere_de_id' => null,
            'created_at' => $this->maintenant(),
        ]);

        return $this->messageFormate($id);
    }

    /** Transfère un message vers une autre discussion où l'on est membre. */
    public function transferer(int $messageId, int $versConversationId): array
    {
        $source = $this->depot->message($messageId);
        if ($source === null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }

        // La source doit être lisible comme membre : transférer depuis une
        // discussion ouverte au titre de la Direction la ferait fuiter.
        $membre = $this->depot->membre((int) $source['conversation_id'], $this->utilisateurId);
        if ($membre === null || $membre['quitte_at'] !== null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }
        if ($source['supprime_at'] !== null) {
            throw MessagerieException::invalide('Un message supprimé ne se transfère pas.');
        }

        $this->ecriture($versConversationId);
        $this->antiAbus();

        $id = $this->depot->ajouterMessage([
            'conversation_id' => $versConversationId,
            'auteur_id' => $this->utilisateurId,
            'texte' => (string) $source['texte'],
            'reponse_a_id' => null,
            'transfere_de_id' => $messageId,
            'created_at' => $this->maintenant(),
        ]);

        return $this->messageFormate($id);
    }

    /** Par l'auteur seul, au plus 15 minutes après l'envoi. */
    public function modifier(int $messageId, string $texte): array
    {
        $texte = $this->texte($texte);
        $message = $this->messageDeMembre($messageId);

        if ((int) $message['auteur_id'] !== $this->utilisateurId) {
            throw MessagerieException::interdit('Seul l’auteur peut modifier son message.');
        }
        if ($message['supprime_at'] !== null) {
            throw MessagerieException::invalide('Ce message a été supprimé.');
        }
        if (!$this->encoreModifiable($message)) {
            throw MessagerieException::interdit('Un message ne se modifie plus 15 minutes après son envoi.');
        }

        if ($texte !== (string) $message['texte']) {
            $this->depot->modifierMessage($messageId, $texte, (string) $message['texte'], $this->utilisateurId, $this->maintenant());
        }

        return $this->messageFormate($messageId);
    }

    /**
     * Par l'auteur, ou par un administrateur du groupe. Le texte reste en
     * base et au journal ; seule la Direction le relit ensuite.
     */
    public function supprimer(int $messageId): array
    {
        $message = $this->messageDeMembre($messageId);
        $conversationId = (int) $message['conversation_id'];

        $auteur = (int) $message['auteur_id'] === $this->utilisateurId;
        if (!$auteur) {
            $conversation = $this->depot->conversation($conversationId);
            $membre = $this->depot->membre($conversationId, $this->utilisateurId);
            $adminGroupe = $conversation !== null
                && $conversation['type'] !== 'direct'
                && $membre !== null
                && $membre['role'] === 'admin';

            if (!$adminGroupe) {
                throw MessagerieException::interdit('Seul l’auteur ou un administrateur du groupe peut supprimer ce message.');
            }
        }

        if ($message['supprime_at'] !== null) {
            throw MessagerieException::invalide('Ce message est déjà supprimé.');
        }

        $this->depot->supprimerMessage($messageId, (string) $message['texte'], $this->utilisateurId, $this->maintenant());

        return $this->messageFormate($messageId);
    }

    public function marquerLu(int $conversationId, int $jusquA): void
    {
        if ($jusquA <= 0) {
            throw MessagerieException::invalide('Indiquez le dernier message lu.');
        }

        $this->adhesion($conversationId);
        $this->depot->marquerLu($conversationId, $this->utilisateurId, $jusquA, $this->maintenant());
    }

    /**
     * « Lu par » : membres actifs, auteur exclu, dont le dernier lu atteint
     * ce message.
     *
     * @return array{lu_par: array<int, array<string, mixed>>, non_lu_par: array<int, array<string, mixed>>}
     */
    public function luPar(int $messageId): array
    {
        $message = $this->depot->message($messageId);
        if ($message === null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }

        $conversationId = (int) $message['conversation_id'];
        $this->lecture($conversationId, false);

        $lecteurs = [];
        foreach ($this->depot->luPar($conversationId, $messageId, (int) $message['auteur_id']) as $ligne) {
            $lecteurs[(int) $ligne['user_id']] = ['id' => (int) $ligne['user_id'], 'nom' => (string) $ligne['nom'], 'lu_at' => $ligne['lu_at'] ?? null];
        }

        $nonLecteurs = [];
        foreach ($this->depot->membresActifs($conversationId) as $m) {
            $id = (int) $m['user_id'];
            if ($id !== (int) $message['auteur_id'] && !isset($lecteurs[$id])) {
                $nonLecteurs[] = ['id' => $id, 'nom' => (string) $m['nom']];
            }
        }

        return ['lu_par' => array_values($lecteurs), 'non_lu_par' => $nonLecteurs];
    }

    public function epingler(int $conversationId, int $messageId): void
    {
        $this->ecriture($conversationId);

        $message = $this->depot->message($messageId);
        if ($message === null || (int) $message['conversation_id'] !== $conversationId) {
            throw MessagerieException::introuvable('Message introuvable.');
        }
        if ($message['supprime_at'] !== null) {
            throw MessagerieException::invalide('Un message supprimé ne s’épingle pas.');
        }

        $epingles = $this->depot->epingles($conversationId);
        foreach ($epingles as $e) {
            if ((int) $e['id'] === $messageId) {
                return;
            }
        }
        if (count($epingles) >= self::EPINGLES_MAX) {
            throw MessagerieException::invalide('Trois messages au plus restent épinglés : retirez-en un d’abord.');
        }

        $this->depot->epingler($conversationId, $messageId, $this->utilisateurId, $this->maintenant());
    }

    public function desepingler(int $conversationId, int $messageId): void
    {
        $this->ecriture($conversationId);
        $this->depot->desepingler($conversationId, $messageId);
    }

    // ==================================================================
    // Recherche, annuaire, présence
    // ==================================================================

    /**
     * Recherche dans une discussion, ou dans toutes celles de l'utilisateur.
     * Plein texte quand chaque mot a au moins 3 lettres (taille minimale de
     * l'index InnoDB), LIKE sinon — et LIKE encore si le plein texte ne
     * trouve rien (mots vides du moteur).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rechercher(string $requete, ?int $conversationId = null): array
    {
        $mots = self::motsRecherche($requete);
        if ($mots === []) {
            throw MessagerieException::invalide('Saisissez au moins deux caractères.');
        }

        if ($conversationId !== null && $conversationId > 0) {
            $this->lecture($conversationId, true);
            $conversations = [$conversationId];
        } else {
            $conversations = array_map(
                static fn(array $l): int => (int) $l['id'],
                $this->depot->conversationsDe($this->utilisateurId)
            );
        }

        if ($conversations === []) {
            return [];
        }

        $pleinTexte = self::pleinTexteApplicable($mots);
        $lignes = $this->depot->rechercher($conversations, $mots, $pleinTexte, self::RECHERCHE_MAX);
        if ($lignes === [] && $pleinTexte) {
            $lignes = $this->depot->rechercher($conversations, $mots, false, self::RECHERCHE_MAX);
        }

        return $this->formaterMessages($lignes);
    }

    /** @return array<int, array<string, mixed>> */
    public function annuaire(string $recherche = ''): array
    {
        $recherche = mb_substr(trim($recherche), 0, 80);

        return array_map(static fn(array $u): array => [
            'id' => (int) $u['id'],
            'nom' => (string) $u['nom'],
            'agence' => $u['agence'] ?? null,
            'fonction' => $u['fonction'] ?? null,
        ], $this->depot->annuaire($this->utilisateurId, $recherche, 200));
    }

    /**
     * Le relevé, appelé toutes les 3 secondes par chaque poste ouvert.
     *
     * $depuis est le dernier identifiant de message connu ; null au premier
     * appel : on rend alors seulement le curseur, pas l'historique. Un
     * curseur à 0 est un vrai curseur (aucun message encore) et ne doit pas
     * être confondu avec un premier appel, sinon le tout premier message
     * reçu serait sauté.
     * $lecturesDepuis est le « serveur_at » du relevé précédent : il sert à
     * retrouver les lectures, retouches et réglages survenus entre deux.
     *
     * @return array<string, mixed>
     */
    public function nouveautes(?int $depuis, ?string $lecturesDepuis): array
    {
        $maintenant = $this->maintenant();
        $this->depot->toucherPresence($this->utilisateurId, $maintenant);

        $messages = [];
        $incomplet = false;
        if ($depuis === null) {
            $curseur = $this->depot->curseurMessages($this->utilisateurId);
        } else {
            $depuis = max(0, $depuis);
            $lignes = $this->depot->nouveauxMessages($this->utilisateurId, $depuis, self::RELEVE_MAX + 1);
            $incomplet = count($lignes) > self::RELEVE_MAX;
            $lignes = array_slice($lignes, 0, self::RELEVE_MAX);
            $messages = $this->formaterMessages($lignes);
            $curseur = $lignes === [] ? $depuis : (int) $lignes[count($lignes) - 1]['id'];
        }

        $lecturesDepuis = self::dateValide($lecturesDepuis);
        $retouches = [];
        $lectures = [];
        $changees = ['modifiees' => [], 'quittees' => []];
        if ($lecturesDepuis !== null && $depuis !== null) {
            $retouches = $this->formaterMessages($this->depot->messagesRetouches($this->utilisateurId, $depuis, $lecturesDepuis));
            $lectures = array_map(static fn(array $l): array => [
                'conversation_id' => (int) $l['conversation_id'],
                'user_id' => (int) $l['user_id'],
                'dernier_message_lu_id' => (int) $l['dernier_message_lu_id'],
            ], $this->depot->lecturesDepuis($this->utilisateurId, $lecturesDepuis));
            $changees = $this->depot->conversationsChangees($this->utilisateurId, $lecturesDepuis);
        }

        $nonLus = [];
        foreach ($this->depot->nonLus($this->utilisateurId) as $conversationId => $nombre) {
            $nonLus[(string) $conversationId] = (int) $nombre;
        }

        return [
            'serveur_at' => $maintenant,
            'curseur' => $curseur,
            'incomplet' => $incomplet,
            'messages' => $messages,
            'messages_modifies' => $retouches,
            'lectures' => $lectures,
            'non_lus' => (object) $nonLus,
            'non_lus_total' => array_sum($nonLus),
            'conversations_modifiees' => array_values(array_map('intval', $changees['modifiees'])),
            'conversations_quittees' => array_values(array_map('intval', $changees['quittees'])),
            'presence' => $this->presence($maintenant),
        ];
    }

    /** @return array<string, bool> */
    public function preferences(): array
    {
        $p = $this->depot->preferences($this->utilisateurId);

        return [
            'presence_masquee' => (bool) ($p['presence_masquee'] ?? false),
            'son_actif' => (bool) ($p['son_actif'] ?? true),
            'apercu_masque' => (bool) ($p['apercu_masque'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $valeurs seules les clés connues sont lues
     * @return array<string, bool>
     */
    public function enregistrerPreferences(array $valeurs): array
    {
        $preferences = $this->preferences();
        foreach (array_keys($preferences) as $cle) {
            if (array_key_exists($cle, $valeurs)) {
                $preferences[$cle] = filter_var($valeurs[$cle], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->depot->enregistrerPreferences($this->utilisateurId, $preferences);

        return $preferences;
    }

    // ==================================================================
    // Contrôles d'accès
    // ==================================================================

    /**
     * Lire une discussion : membre actif, ou Direction (journalisée).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    /**
     * L'écran de la Direction : toutes les discussions et les dernières
     * ouvertures. Lister ne journalise rien ; c'est ouvrir une discussion qui
     * laisse une trace, comme partout ailleurs.
     *
     * @return array{conversations: array<int, array<string, mixed>>, acces: array<int, array<string, mixed>>, plafond: int}
     */
    public function vueDirection(string $recherche = '', string $type = ''): array
    {
        if (!$this->direction) {
            throw MessagerieException::introuvable();
        }

        $recherche = trim(mb_substr($recherche, 0, 80));
        if (!in_array($type, ['direct', 'groupe', 'annonces'], true)) {
            $type = '';
        }

        $plafond = 200;
        $conversations = [];
        foreach ($this->depot->toutesConversations($recherche, $type, $plafond) as $c) {
            $conversations[] = [
                'id' => (int) $c['id'],
                'type' => (string) $c['type'],
                'nom' => $c['nom'] !== null && $c['nom'] !== '' ? (string) $c['nom'] : null,
                'participants' => (string) ($c['participants'] ?? ''),
                'nb_messages' => (int) $c['nb_messages'],
                'dernier_message_at' => $c['dernier_message_at'] ?? null,
                'created_at' => (string) $c['created_at'],
            ];
        }

        return [
            'conversations' => $conversations,
            'acces' => $this->depot->accesDirectionRecents(30),
            'plafond' => $plafond,
        ];
    }

    private function lecture(int $conversationId, bool $journaliser): array
    {
        $conversation = $this->depot->conversation($conversationId);
        if ($conversation === null) {
            throw MessagerieException::introuvable();
        }

        $membre = $this->depot->membre($conversationId, $this->utilisateurId);
        if ($membre !== null && $membre['quitte_at'] === null) {
            return [$conversation, $membre];
        }

        if ($this->direction) {
            if ($journaliser) {
                $this->depot->journaliserAccesDirection($this->utilisateurId, $conversationId, $this->maintenant());
                AuditLogService::log('MESSAGERIE_ACCES_DIRECTION', 'msg_conversation', $conversationId);
            }

            return [$conversation, null];
        }

        throw MessagerieException::introuvable();
    }

    /**
     * Membre actif, sans exception pour la Direction.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function adhesion(int $conversationId): array
    {
        $conversation = $this->depot->conversation($conversationId);
        $membre = $conversation === null ? null : $this->depot->membre($conversationId, $this->utilisateurId);

        if ($conversation === null || $membre === null || $membre['quitte_at'] !== null) {
            throw MessagerieException::introuvable();
        }

        return [$conversation, $membre];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function ecriture(int $conversationId): array
    {
        [$conversation, $membre] = $this->adhesion($conversationId);

        if ($conversation['type'] === 'annonces' && $membre['role'] !== 'admin') {
            throw MessagerieException::interdit('Seuls les administrateurs écrivent dans une discussion d’annonces.');
        }

        if ($conversation['type'] === 'direct') {
            foreach ($this->depot->membresActifs($conversationId) as $m) {
                if ((int) $m['user_id'] !== $this->utilisateurId && !$m['actif']) {
                    throw MessagerieException::interdit('Ce collègue n’a plus accès à la messagerie.');
                }
            }
        }

        return [$conversation, $membre];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function administration(int $conversationId): array
    {
        [$conversation, $membre] = $this->adhesion($conversationId);

        if ($conversation['type'] === 'direct') {
            throw MessagerieException::interdit('Une discussion directe n’a pas d’administrateur.');
        }
        if ($membre['role'] !== 'admin') {
            throw MessagerieException::interdit('Réservé aux administrateurs du groupe.');
        }

        return [$conversation, $membre];
    }

    /** @param array<int, array<string, mixed>> $membres */
    private function peutEcrire(array $conversation, ?array $membre, array $membres): bool
    {
        if ($membre === null) {
            return false;
        }
        if ($conversation['type'] === 'annonces') {
            return $membre['role'] === 'admin';
        }
        if ($conversation['type'] === 'direct') {
            foreach ($membres as $m) {
                if ((int) $m['user_id'] !== $this->utilisateurId && !$m['actif']) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function messageDeMembre(int $messageId): array
    {
        $message = $this->depot->message($messageId);
        if ($message === null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }

        $membre = $this->depot->membre((int) $message['conversation_id'], $this->utilisateurId);
        if ($membre === null || $membre['quitte_at'] !== null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }

        return $message;
    }

    private function antiAbus(): void
    {
        $depuis = ($this->horloge)()->modify('-60 seconds')->format(self::FORMAT_DATE);

        if ($this->depot->compterEnvoisDepuis($this->utilisateurId, $depuis) >= self::ENVOIS_PAR_MINUTE) {
            throw MessagerieException::tropDEnvois('Trop de messages en une minute. Patientez quelques secondes.');
        }
    }

    private function nombreAdmins(int $conversationId): int
    {
        $n = 0;
        foreach ($this->depot->membresActifs($conversationId) as $m) {
            if ($m['role'] === 'admin') {
                $n++;
            }
        }

        return $n;
    }

    // ==================================================================
    // Saisie
    // ==================================================================

    private function texte(string $texte): string
    {
        // Caractères de contrôle retirés, retours à la ligne et tabulations gardés.
        $texte = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $texte)));

        if ($texte === '') {
            throw MessagerieException::invalide('Le message est vide.');
        }
        if (mb_strlen($texte) > self::TEXTE_MAX) {
            throw MessagerieException::invalide('Un message compte au plus 4 000 caractères.');
        }

        return $texte;
    }

    private function nomGroupe(string $nom): string
    {
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));
        if ($nom === '') {
            throw MessagerieException::invalide('Donnez un nom au groupe.');
        }
        if (mb_strlen($nom) > self::NOM_GROUPE_MAX) {
            throw MessagerieException::invalide('Le nom du groupe compte au plus 120 caractères.');
        }

        return $nom;
    }

    private function description(string $description): ?string
    {
        $description = trim($description);
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            throw MessagerieException::invalide('La description compte au plus 500 caractères.');
        }

        return $description === '' ? null : $description;
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function collegesActifs(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn(int $id): bool => $id > 0 && $id !== $this->utilisateurId
        )));

        return $ids === [] ? [] : $this->depot->idsActifs($ids);
    }

    /** @return array<int, string> */
    public static function motsRecherche(string $requete): array
    {
        // Les opérateurs du mode booléen sont retirés : ils viennent de l'utilisateur.
        $requete = trim((string) preg_replace('/[+\-<>()~*"@%_\\\\]+/u', ' ', $requete));
        if (mb_strlen($requete) < 2) {
            return [];
        }

        $mots = preg_split('/\s+/u', mb_substr($requete, 0, 100)) ?: [];

        return array_slice(array_values(array_filter($mots, static fn(string $m): bool => $m !== '')), 0, 8);
    }

    /** @param array<int, string> $mots */
    public static function pleinTexteApplicable(array $mots): bool
    {
        foreach ($mots as $mot) {
            if (mb_strlen($mot) < 3) {
                return false;
            }
        }

        return $mots !== [];
    }

    // ==================================================================
    // Mise en forme
    // ==================================================================

    /**
     * @param array<string, mixed> $conversation
     * @param array<string, mixed>|null $correspondant
     * @return array<string, mixed>
     */
    private function formaterConversation(array $conversation, ?array $correspondant): array
    {
        $direct = $conversation['type'] === 'direct';

        return [
            'id' => (int) $conversation['id'],
            'type' => (string) $conversation['type'],
            'nom' => $direct ? (string) ($correspondant['nom'] ?? 'Discussion') : (string) $conversation['nom'],
            'description' => $conversation['description'] ?? null,
            'photo' => $conversation['photo'] ?? null,
            'agence_id' => isset($conversation['agence_id']) ? (int) $conversation['agence_id'] : null,
            'automatique' => (bool) ($conversation['automatique'] ?? false),
            'created_at' => (string) $conversation['created_at'],
            'dernier_message_at' => $conversation['dernier_message_at'] ?? null,
            'correspondant' => $direct && $correspondant !== null ? [
                'id' => (int) $correspondant['id'],
                'nom' => (string) $correspondant['nom'],
                'actif' => (bool) $correspondant['actif'],
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function messageFormate(int $id): array
    {
        $message = $this->depot->message($id);
        if ($message === null) {
            throw MessagerieException::introuvable('Message introuvable.');
        }

        return $this->formaterMessages([$message])[0];
    }

    /**
     * Mise en forme commune ; les messages cités sont chargés en une fois.
     *
     * @param array<int, array<string, mixed>> $lignes
     * @return array<int, array<string, mixed>>
     */
    private function formaterMessages(array $lignes): array
    {
        $citesIds = [];
        foreach ($lignes as $l) {
            if (!empty($l['reponse_a_id'])) {
                $citesIds[] = (int) $l['reponse_a_id'];
            }
        }
        $cites = $citesIds === [] ? [] : $this->depot->messagesParIds(array_values(array_unique($citesIds)));

        $resultat = [];
        foreach ($lignes as $l) {
            $supprime = $l['supprime_at'] !== null;
            $cite = !empty($l['reponse_a_id']) ? ($cites[(int) $l['reponse_a_id']] ?? null) : null;

            $resultat[] = [
                'id' => (int) $l['id'],
                'conversation_id' => (int) $l['conversation_id'],
                'auteur' => ['id' => (int) $l['auteur_id'], 'nom' => (string) ($l['auteur_nom'] ?? '')],
                'texte' => $supprime && !$this->direction ? null : (string) $l['texte'],
                'supprime' => $supprime,
                'libelle' => $supprime ? self::LIBELLE_SUPPRIME : null,
                'modifie' => $l['modifie_at'] !== null,
                'transfere' => !empty($l['transfere_de_id']),
                'reponse_a' => $cite === null ? null : [
                    'id' => (int) $cite['id'],
                    'auteur_nom' => (string) ($cite['auteur_nom'] ?? ''),
                    'apercu' => $this->apercu($cite),
                    'supprime' => $cite['supprime_at'] !== null,
                ],
                'modifiable' => !$supprime
                    && (int) $l['auteur_id'] === $this->utilisateurId
                    && $this->encoreModifiable($l),
                'created_at' => (string) $l['created_at'],
                'modifie_at' => $l['modifie_at'] ?? null,
                'supprime_at' => $l['supprime_at'] ?? null,
            ];
        }

        return $resultat;
    }

    /** @param array<string, mixed> $message */
    private function apercu(array $message): string
    {
        if ($message['supprime_at'] !== null && !$this->direction) {
            return self::LIBELLE_SUPPRIME;
        }

        $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $message['texte']));

        return mb_strlen($texte) > 120 ? mb_substr($texte, 0, 119) . '…' : $texte;
    }

    /** @param array<string, mixed> $message */
    private function encoreModifiable(array $message): bool
    {
        $envoi = DateTimeImmutable::createFromFormat(self::FORMAT_DATE, (string) $message['created_at']);
        if ($envoi === false) {
            return false;
        }

        return ($this->horloge)()->getTimestamp() - $envoi->getTimestamp() <= self::DELAI_MODIFICATION_SECONDES;
    }

    /** @return array<int, array<string, mixed>> */
    private function presence(string $maintenant): array
    {
        $seuil = (new DateTimeImmutable($maintenant))->modify('-' . self::EN_LIGNE_SECONDES . ' seconds')->format(self::FORMAT_DATE);

        return array_map(static function (array $p) use ($seuil): array {
            $visible = !$p['presence_masquee'] && $p['actif'];
            $vu = $visible ? ($p['vu_at'] ?? null) : null;

            return [
                'user_id' => (int) $p['user_id'],
                'en_ligne' => $vu !== null && (string) $vu >= $seuil,
                'vu_at' => $vu,
            ];
        }, $this->depot->presenceCorrespondants($this->utilisateurId));
    }

    private function maintenant(): string
    {
        return ($this->horloge)()->format(self::FORMAT_DATE);
    }

    private static function dateValide(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $d = DateTimeImmutable::createFromFormat(self::FORMAT_DATE, $date);

        return $d !== false && $d->format(self::FORMAT_DATE) === $date ? $date : null;
    }
}
