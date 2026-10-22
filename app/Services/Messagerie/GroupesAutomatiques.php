<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use App\Models\Database;
use App\Repositories\Messagerie\MessagerieDepotInterface;
use App\Repositories\Messagerie\MessagerieRepository;
use App\Security\MessagerieAcces;
use App\Services\Noel\NoelCompte;
use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Les groupes que personne ne gère à la main (lot 5, cahier des charges
 * validé en octobre 2026) :
 *
 *  - un groupe par agence de company_sites (« Agence Abobo Dokui ») : les
 *    comptes actifs rattachés à l'agence (users.agence_id), ses chefs
 *    d'agence en administrateurs. Les comptes « toutes agences »
 *    (agence_id NULL : Direction, Siège, Call center...) n'y sont pas ;
 *  - « Tout LBP » : tous les comptes actifs, et tout le monde y écrit ;
 *  - « Annonces Direction », discussion d'annonces : tous les comptes actifs
 *    la lisent, seuls ses administrateurs — la Direction au sens de
 *    MessagerieAcces (DG et administrateur) — y écrivent.
 *
 * La Direction administre aussi « Tout LBP » : il faut bien quelqu'un pour
 * y épingler ou y retirer un message déplacé.
 *
 * Les membres ne sont jamais réglés par les écrans qui modifient un compte :
 * cette synchronisation, idempotente, décide seule, et MessagerieService
 * refuse à quiconque de quitter un groupe automatique, d'y ajouter ou d'en
 * retirer un membre, de le renommer ou d'en changer le type. Elle passe :
 *  - pour l'utilisateur courant, à la lecture de sa liste de discussions —
 *    et seulement si son agence, son statut ou ses rôles ont changé depuis
 *    la dernière vérification, ou si celle-ci date de plus de dix minutes ;
 *  - en entier, chaque nuit (app/Console/SynchroniserGroupesMessagerie.php)
 *    et sur le bouton de l'écran « Espace de la messagerie ».
 * JAMAIS dans MigrationRunner : run() passe à chaque requête, et deux pertes
 * de données en production sont venues d'y avoir mis des écritures.
 *
 * Chaque groupe est reconnu par une clé posée dans cle_directe — « auto:tout »,
 * « auto:annonces », « auto:agence:3403 » — dont l'unique empêche deux
 * synchronisations simultanées de créer deux fois le même groupe. Le nom,
 * lui, suit l'agence : renommée dans company_sites, elle l'est ici à la
 * passe suivante.
 *
 * NOEL, l'assistant interne, est un compte actif : il n'entre jamais dans un
 * groupe automatique. Un compte désactivé, ou qui change d'agence, en sort ;
 * ses anciens messages restent visibles des autres (règle commune). Un
 * nouveau membre voit l'historique, déjà marqué lu.
 */
final class GroupesAutomatiques
{
    public const NOM_TOUT = 'Tout LBP';
    public const NOM_ANNONCES = 'Annonces Direction';

    public const CLE_TOUT = 'auto:tout';
    public const CLE_ANNONCES = 'auto:annonces';
    public const PREFIXE_AGENCE = 'auto:agence:';

    /** Les chefs d'agence administrent le groupe de leur agence. */
    public const ROLES_CHEF_AGENCE = ['chef_agence'];

    /** Au-delà, la vérification de l'utilisateur courant est refaite même sans changement. */
    public const DELAI_VERIFICATION_SECONDES = 600;

    private const FORMAT_DATE = 'Y-m-d H:i:s';

    private Closure $horloge;

    /** @var array<int, true> */
    private array $exclus = [];

    /**
     * @param array<int, int|null> $exclus comptes techniques jamais membres (NOEL)
     */
    public function __construct(
        private MessagerieDepotInterface $depot,
        array $exclus = [],
        ?Closure $horloge = null,
    ) {
        foreach ($exclus as $id) {
            if ($id !== null && $id > 0) {
                $this->exclus[(int) $id] = true;
            }
        }
        $this->horloge = $horloge ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * Branché sur la base réelle, NOEL écarté.
     *
     * NoelCompte::existant() et non id() : la synchronisation reconnaît NOEL,
     * elle ne crée pas son compte.
     */
    public static function parDefaut(?PDO $pdo = null): self
    {
        $pdo ??= Database::getConnection();

        $exclus = [];
        try {
            $exclus[] = NoelCompte::existant($pdo);
        } catch (Throwable $e) {
            error_log('[Messagerie groupes] compte NOEL illisible : ' . $e->getMessage());
        }

        return new self(new MessagerieRepository($pdo), $exclus);
    }

    /** @param array<string, mixed> $conversation */
    public static function estAutomatique(array $conversation): bool
    {
        return (bool) ($conversation['automatique'] ?? false);
    }

    /**
     * Ce qui, dans un compte, décide de ses groupes : si rien n'a bougé, la
     * vérification légère n'a rien à refaire.
     *
     * @param array<string, mixed> $compte id, agence_id, actif, admin, roles
     */
    public static function empreinte(array $compte): string
    {
        $compte = self::normaliser($compte);
        sort($compte['roles']);

        return sha1(json_encode([$compte['id'], $compte['agence_id'], $compte['actif'], $compte['admin'], $compte['roles']]) ?: '');
    }

    // ==================================================================
    // Passe complète
    // ==================================================================

    /**
     * Crée les groupes manquants, ajoute les comptes actifs absents, retire
     * les comptes désactivés, ceux qui ont changé d'agence, et NOEL ; remet
     * chacun à son rôle, chaque groupe à son nom. Deux passes de suite : la
     * seconde ne change rien.
     *
     * @return array<string, int> bilan (voir bilanVide)
     */
    public function synchroniserTout(): array
    {
        $bilan = self::bilanVide();
        $agences = $this->agences();
        $groupes = $this->groupesParCle();

        // Qui doit être où, et avec quel rôle.
        $attendus = [self::CLE_TOUT => [], self::CLE_ANNONCES => []];
        foreach ($this->depot->comptesPourGroupes() as $compte) {
            foreach ($this->placesDe($compte, $agences) as $cle => $role) {
                $attendus[$cle][(int) $compte['id']] = $role;
            }
        }
        // Un groupe d'agence qui n'attend plus personne (agence fermée, ou
        // vidée) reste pour son historique, mais se vide.
        foreach (array_keys($groupes) as $cle) {
            $attendus[$cle] ??= [];
        }

        $actuels = [];
        foreach ($this->depot->membresAutomatiques() as $m) {
            $actuels[$m['conversation_id']][$m['user_id']] = $m['role'];
        }

        foreach ($attendus as $cle => $membres) {
            // Pas de groupe vide créé pour une agence sans personne.
            if (!isset($groupes[$cle]) && $membres === [] && str_starts_with($cle, self::PREFIXE_AGENCE)) {
                continue;
            }

            $conversation = $this->groupe($cle, $groupes, $agences, $bilan);
            if ($conversation === null) {
                continue;
            }

            $id = (int) $conversation['id'];
            $this->accorder($id, $membres, $actuels[$id] ?? [], $bilan);
        }

        return $bilan;
    }

    /**
     * Les groupes automatiques tels qu'ils sont, pour l'écran
     * d'administration : nom, type, nombre de membres et d'administrateurs.
     *
     * @return array<int, array{id:int, nom:string, type:string, agence_id:?int, membres:int, admins:int}>
     */
    public function etat(): array
    {
        $compte = [];
        foreach ($this->depot->membresAutomatiques() as $m) {
            $compte[$m['conversation_id']]['membres'] = ($compte[$m['conversation_id']]['membres'] ?? 0) + 1;
            if ($m['role'] === 'admin') {
                $compte[$m['conversation_id']]['admins'] = ($compte[$m['conversation_id']]['admins'] ?? 0) + 1;
            }
        }

        $lignes = [];
        foreach ($this->groupesParCle() as $cle => $c) {
            $id = (int) $c['id'];
            $lignes[] = [
                'id' => $id,
                'nom' => (string) $c['nom'],
                'type' => (string) $c['type'],
                'agence_id' => str_starts_with($cle, self::PREFIXE_AGENCE) ? (int) substr($cle, strlen(self::PREFIXE_AGENCE)) : null,
                'membres' => (int) ($compte[$id]['membres'] ?? 0),
                'admins' => (int) ($compte[$id]['admins'] ?? 0),
            ];
        }

        // Les deux groupes de tous d'abord, puis les agences par nom.
        usort($lignes, static fn(array $a, array $b): int => [$a['agence_id'] !== null, $a['nom']] <=> [$b['agence_id'] !== null, $b['nom']]);

        return $lignes;
    }

    // ==================================================================
    // Un seul compte
    // ==================================================================

    /**
     * Le compte est dans le groupe de son agence et dans aucun autre groupe
     * d'agence, dans « Tout LBP » et « Annonces Direction », avec le bon
     * rôle — ou dans aucun s'il est désactivé ou technique.
     *
     * Un groupe qui manque encore (première ouverture de la messagerie après
     * la mise en service) appelle une passe complète : créé pour un seul
     * compte, il resterait vide des autres jusqu'à la nuit.
     *
     * @param array<string, mixed> $compte id, agence_id, actif, admin, roles
     * @return array<string, int> bilan
     */
    public function synchroniserCompte(array $compte): array
    {
        $compte = self::normaliser($compte);
        $userId = $compte['id'];
        $bilan = self::bilanVide();
        if ($userId <= 0) {
            return $bilan;
        }

        $agences = $this->agences();
        $groupes = $this->groupesParCle();
        $places = $this->placesDe($compte, $agences);

        foreach (array_keys($places) as $cle) {
            if (!isset($groupes[$cle])) {
                return $this->synchroniserTout();
            }
        }

        $actuels = [];
        foreach ($this->depot->membresAutomatiques($userId) as $m) {
            $actuels[$m['conversation_id']] = $m['role'];
        }

        foreach ($groupes as $cle => $conversation) {
            $id = (int) $conversation['id'];
            $attendu = isset($places[$cle]) ? [$userId => $places[$cle]] : [];
            $actuel = isset($actuels[$id]) ? [$userId => $actuels[$id]] : [];
            $this->accorder($id, $attendu, $actuel, $bilan);
        }

        return $bilan;
    }

    /**
     * La vérification légère de l'utilisateur courant, à la lecture de sa
     * liste de discussions : rien à faire — aucune requête — si son
     * empreinte n'a pas changé depuis moins de dix minutes.
     *
     * $memo est ce que l'appelant a gardé de la fois précédente (la session) ;
     * il garde ensuite le second élément rendu.
     *
     * @param array<string, mixed> $compte id, agence_id, actif, admin, roles
     * @return array{0: array<string, int>|null, 1: array{empreinte: string, at: int}}
     *         le bilan (null si rien n'a été vérifié) et le nouveau mémo
     */
    public function synchroniserCompteSiBesoin(array $compte, mixed $memo): array
    {
        $maintenant = ($this->horloge)()->getTimestamp();
        $empreinte = self::empreinte($compte);

        if (is_array($memo)
            && ($memo['empreinte'] ?? null) === $empreinte
            && is_int($memo['at'] ?? null)
            && $maintenant - $memo['at'] >= 0
            && $maintenant - $memo['at'] < self::DELAI_VERIFICATION_SECONDES
        ) {
            return [null, $memo];
        }

        return [$this->synchroniserCompte($compte), ['empreinte' => $empreinte, 'at' => $maintenant]];
    }

    // ==================================================================
    // Rouages
    // ==================================================================

    /**
     * Les groupes où ce compte doit être, et son rôle dans chacun.
     *
     * @param array<string, mixed> $compte
     * @param array<int, string> $agences id => nom du groupe
     * @return array<string, string> clé du groupe => « membre » ou « admin »
     */
    private function placesDe(array $compte, array $agences): array
    {
        $compte = self::normaliser($compte);
        if (!$compte['actif'] || isset($this->exclus[$compte['id']])) {
            return [];
        }

        $direction = MessagerieAcces::estDirectionCompte($compte['admin'], $compte['roles']) ? 'admin' : 'membre';
        $places = [self::CLE_TOUT => $direction, self::CLE_ANNONCES => $direction];

        $agence = $compte['agence_id'];
        if ($agence !== null && isset($agences[$agence])) {
            $places[self::PREFIXE_AGENCE . $agence] = array_intersect(self::ROLES_CHEF_AGENCE, $compte['roles']) !== [] ? 'admin' : 'membre';
        }

        return $places;
    }

    /**
     * Met les adhésions d'un groupe en accord avec ce qui est attendu.
     * N'ajoute jamais un membre déjà présent : ajouterMembre remettrait à
     * zéro sa sourdine et son archive.
     *
     * @param array<int, string> $attendus user_id => rôle
     * @param array<int, string> $actuels user_id => rôle
     * @param array<string, int> $bilan
     */
    private function accorder(int $conversationId, array $attendus, array $actuels, array &$bilan): void
    {
        $maintenant = $this->maintenant();
        $change = false;

        foreach ($attendus as $userId => $role) {
            if (!isset($actuels[$userId])) {
                $change = $this->tenter(fn() => $this->depot->ajouterMembre($conversationId, $userId, $role, $maintenant), 'membres_ajoutes', $bilan) || $change;
            } elseif ($actuels[$userId] !== $role) {
                $change = $this->tenter(fn() => $this->depot->changerRole($conversationId, $userId, $role), 'roles_changes', $bilan) || $change;
            }
        }

        foreach (array_keys($actuels) as $userId) {
            if (!isset($attendus[$userId])) {
                $change = $this->tenter(fn() => $this->depot->retirerMembre($conversationId, $userId, $maintenant), 'membres_retires', $bilan) || $change;
            }
        }

        // Comme un ajout à la main : les autres membres voient la
        // discussion changer au relevé suivant.
        if ($change) {
            $this->tenter(fn() => $this->depot->modifierConversation($conversationId, [], $maintenant), null, $bilan);
        }
    }

    /**
     * Le groupe de cette clé, créé s'il manque ; son nom et son type remis
     * en ordre s'ils ont dérivé.
     *
     * @param array<string, array<string, mixed>> $groupes
     * @param array<int, string> $agences
     * @param array<string, int> $bilan
     * @return array<string, mixed>|null
     */
    private function groupe(string $cle, array &$groupes, array $agences, array &$bilan): ?array
    {
        $agenceId = str_starts_with($cle, self::PREFIXE_AGENCE) ? (int) substr($cle, strlen(self::PREFIXE_AGENCE)) : null;
        $type = $cle === self::CLE_ANNONCES ? 'annonces' : 'groupe';
        $nom = match (true) {
            $cle === self::CLE_TOUT => self::NOM_TOUT,
            $cle === self::CLE_ANNONCES => self::NOM_ANNONCES,
            default => $agences[$agenceId] ?? null,
        };

        if (isset($groupes[$cle])) {
            $conversation = $groupes[$cle];
            $champs = [];
            // Une agence fermée garde le nom qu'avait son groupe.
            if ($nom !== null && (string) $conversation['nom'] !== $nom) {
                $champs['nom'] = $nom;
            }
            if ((string) $conversation['type'] !== $type) {
                $champs['type'] = $type;
            }
            if ($champs !== [] && $this->tenter(fn() => $this->depot->modifierConversation((int) $conversation['id'], $champs, $this->maintenant()), 'groupes_renommes', $bilan)) {
                $groupes[$cle] = $champs + $conversation;
            }

            return $groupes[$cle];
        }

        if ($nom === null) {
            return null;
        }

        $id = null;
        $this->tenter(function () use (&$id, $cle, $type, $nom, $agenceId): void {
            $id = $this->depot->creerConversation([
                'type' => $type,
                'nom' => $nom,
                'description' => self::description($cle, $nom),
                'cle_directe' => $cle,
                'createur_id' => null,
                'created_at' => $this->maintenant(),
                'agence_id' => $agenceId,
                'automatique' => 1,
            ]);
        }, 'groupes_crees', $bilan);

        if ($id === null) {
            return null;
        }

        $conversation = $this->depot->conversation($id);
        if ($conversation !== null) {
            $groupes[$cle] = $conversation;
        }

        return $conversation;
    }

    /**
     * Une opération qui échoue (une adhésion créée au même instant par une
     * autre requête, par exemple) ne doit pas arrêter les suivantes.
     *
     * @param array<string, int> $bilan
     */
    private function tenter(callable $operation, ?string $compteur, array &$bilan): bool
    {
        try {
            $operation();
            if ($compteur !== null) {
                $bilan[$compteur]++;
            }

            return true;
        } catch (Throwable $e) {
            $bilan['erreurs']++;
            error_log('[Messagerie groupes] ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<int, string> id de l'agence => nom de son groupe */
    private function agences(): array
    {
        $agences = [];
        foreach ($this->depot->agencesPourGroupes() as $a) {
            $agences[(int) $a['id']] = self::nomAgence((string) $a['nom']);
        }

        return $agences;
    }

    /**
     * Les groupes automatiques reconnus, par clé. Un groupe marqué
     * automatique sans clé connue n'est pas touché.
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupesParCle(): array
    {
        $groupes = [];
        foreach ($this->depot->conversationsAutomatiques() as $c) {
            $cle = (string) ($c['cle_directe'] ?? '');
            if ($cle === self::CLE_TOUT || $cle === self::CLE_ANNONCES
                || (str_starts_with($cle, self::PREFIXE_AGENCE) && ctype_digit(substr($cle, strlen(self::PREFIXE_AGENCE))))
            ) {
                $groupes[$cle] = $c;
            }
        }

        return $groupes;
    }

    /** « Abobo Dokui » => « Agence Abobo Dokui » ; « Agence Sénégal » reste tel quel. */
    public static function nomAgence(string $nom): string
    {
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));
        if (!preg_match('/^agence\b/iu', $nom)) {
            $nom = 'Agence ' . $nom;
        }

        return mb_substr($nom, 0, MessagerieService::NOM_GROUPE_MAX);
    }

    private static function description(string $cle, string $nom): string
    {
        return match ($cle) {
            self::CLE_TOUT => 'Tous les comptes actifs de LBP. Chacun peut y écrire.',
            self::CLE_ANNONCES => 'Les annonces de la Direction. Seuls le DG et l’administrateur y écrivent.',
            default => 'Les comptes rattachés à : ' . mb_substr(preg_replace('/^Agence\s+/iu', '', $nom) ?? $nom, 0, 300) . '.',
        };
    }

    /**
     * @param array<string, mixed> $compte
     * @return array{id:int, agence_id:?int, actif:bool, admin:bool, roles:array<int, string>}
     */
    private static function normaliser(array $compte): array
    {
        $agence = $compte['agence_id'] ?? null;

        return [
            'id' => (int) ($compte['id'] ?? 0),
            'agence_id' => $agence !== null && (int) $agence > 0 ? (int) $agence : null,
            'actif' => (bool) ($compte['actif'] ?? false),
            'admin' => (bool) ($compte['admin'] ?? false),
            'roles' => array_values(array_filter(
                array_map(static fn($r): string => trim((string) $r), (array) ($compte['roles'] ?? [])),
                static fn(string $r): bool => $r !== ''
            )),
        ];
    }

    /** @return array<string, int> */
    public static function bilanVide(): array
    {
        return [
            'groupes_crees' => 0,
            'groupes_renommes' => 0,
            'membres_ajoutes' => 0,
            'membres_retires' => 0,
            'roles_changes' => 0,
            'erreurs' => 0,
        ];
    }

    private function maintenant(): string
    {
        return ($this->horloge)()->format(self::FORMAT_DATE);
    }
}
