<?php

declare(strict_types=1);

namespace App\Controllers\Messagerie;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Messagerie\MessagerieRepository;
use App\Security\MessagerieAcces;
use App\Services\Messagerie\GroupesAutomatiques;
use App\Services\Messagerie\MessagerieException;
use App\Services\Messagerie\MessagerieService;
use App\Services\Messagerie\MessagerieStockage;
use App\View\Navigation\MessagerieNavigation;

/**
 * Messagerie interne : la page d'entrée et l'API JSON que l'écran (lot 2)
 * appellera.
 *
 * Le contrôleur ne fait que trois choses : vérifier la session (une réponse
 * 401 en JSON, pas une redirection vers la page de connexion que fetch
 * suivrait sans rien dire), vérifier le jeton CSRF des POST, et traduire les
 * refus du service en statut HTTP. Toutes les règles sont dans
 * MessagerieService.
 *
 * Le jeton CSRF est accepté là où le projet le fait déjà voyager : champ
 * _csrf_token d'un formulaire, en-tête X-CSRF-TOKEN (system-tests.js), ou
 * clé _csrf_token d'un corps JSON (PresenceApiController).
 */
final class MessagerieController extends BaseController
{
    /** Clé de session du mémo de GroupesAutomatiques::synchroniserCompteSiBesoin. */
    private const MEMO_GROUPES = 'messagerie_groupes_auto';

    // =================================================================
    // Page
    // =================================================================

    public function index(): void
    {
        AuthMiddleware::check();

        $this->view('messagerie/index', [
            'pageTitle' => 'Messagerie',
            'moduleName' => 'Messagerie',
            'moduleCode' => 'MSG',
            'activeModule' => 'discussions',
            'moduleNavigation' => MessagerieNavigation::items(),
            'moduleTheme' => [
                'accent' => '#0f766e',
                'accent2' => '#134e4a',
                'gradient' => 'linear-gradient(135deg, #134e4a, #0f766e)',
                'iconKey' => 'messagerie',
            ],
            'additionalStyles' => ['css/finea-ui.css'],
        ]);
    }

    /**
     * Le classeur des documents. La page ne porte aucune donnée : le
     * JavaScript les demande à l'API (/messagerie/api/documents).
     */
    public function classeur(): void
    {
        AuthMiddleware::check();

        $this->view('messagerie/documents', [
            'pageTitle' => 'Documents',
            'moduleName' => 'Messagerie',
            'moduleCode' => 'MSG',
            'activeModule' => 'documents',
            'moduleNavigation' => MessagerieNavigation::items(),
            'moduleTheme' => [
                'accent' => '#0f766e',
                'accent2' => '#134e4a',
                'gradient' => 'linear-gradient(135deg, #134e4a, #0f766e)',
                'iconKey' => 'messagerie',
            ],
            'additionalStyles' => ['css/finea-ui.css'],
            'additionalScripts' => ['js/messagerie-documents.js'],
        ]);
    }

    /**
     * Toutes les discussions, pour le DG et l'administrateur. Pour les autres,
     * l'adresse n'existe pas : un 404, comme les modules réservés.
     */
    public function direction(): void
    {
        AuthMiddleware::check();

        if (!MessagerieAcces::estDirection()) {
            (new \App\Controllers\Error\ErrorController())->notFound('/messagerie/direction');

            return;
        }

        $recherche = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
        $service = new MessagerieService(new MessagerieRepository(Database::getConnection()), (int) Auth::id(), true);

        $this->view('messagerie/direction', [
            'pageTitle' => 'Toutes les discussions',
            'moduleName' => 'Messagerie',
            'moduleCode' => 'MSG',
            'activeModule' => 'direction',
            'moduleNavigation' => MessagerieNavigation::items(),
            'moduleTheme' => [
                'accent' => '#0f766e',
                'accent2' => '#134e4a',
                'gradient' => 'linear-gradient(135deg, #134e4a, #0f766e)',
                'iconKey' => 'messagerie',
            ],
            'additionalStyles' => ['css/finea-ui.css'],
            'vue' => $service->vueDirection($recherche, $type),
            'filtres' => ['q' => trim($recherche), 'type' => $type],
        ]);
    }

    // =================================================================
    // Lecture
    // =================================================================

    public function conversations(): void
    {
        $service = $this->service();

        // NOEL, l'assistant interne, est un contact comme un autre : sa
        // discussion existe d'office pour chacun, sauf s'il est éteint.
        if (\App\Security\NoelAcces::estAllume()) {
            \App\Services\Noel\NoelMessagerie::conversationAvec(
                (int) Auth::id(),
                new MessagerieRepository(Database::getConnection())
            );
        }

        $this->synchroniserGroupesAutomatiques();

        $this->repondre(static fn(): array => ['conversations' => $service->conversations()]);
    }

    public function detail(string $id): void
    {
        $service = $this->service();
        $conversationId = $this->identifiant($id);
        $this->repondre(static fn(): array => ['conversation' => $service->detail($conversationId)]);
    }

    public function messages(string $id): void
    {
        $service = $this->service();
        $conversationId = $this->identifiant($id);
        $avant = max(0, (int) ($_GET['avant'] ?? 0));
        $this->repondre(static fn(): array => $service->messages($conversationId, $avant));
    }

    public function luPar(string $id): void
    {
        $service = $this->service();
        $messageId = $this->identifiant($id);
        $this->repondre(static fn(): array => $service->luPar($messageId));
    }

    public function recherche(): void
    {
        $service = $this->service();
        $requete = (string) ($_GET['q'] ?? '');
        $conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : null;
        $this->repondre(static fn(): array => ['resultats' => $service->rechercher($requete, $conversationId)]);
    }

    public function annuaire(): void
    {
        $service = $this->service();
        $recherche = (string) ($_GET['q'] ?? '');
        $this->repondre(static fn(): array => ['collegues' => $service->annuaire($recherche)]);
    }

    /**
     * Le relevé des 3 secondes. Il met à jour vu_at (msg_presence) et rien
     * d'autre : ni users.last_login_at, ni journal. Voir la note de lot sur
     * la session PHP, que toute requête prolonge.
     */
    public function nouveautes(): void
    {
        $service = $this->service();
        // Absent : premier relevé. 0 : curseur réel d'un utilisateur sans message.
        $depuis = isset($_GET['depuis']) && $_GET['depuis'] !== '' ? max(0, (int) $_GET['depuis']) : null;
        $lecturesDepuis = isset($_GET['lectures_depuis']) ? (string) $_GET['lectures_depuis'] : null;
        $this->repondre(static fn(): array => $service->nouveautes($depuis, $lecturesDepuis));
    }

    public function preferences(): void
    {
        $service = $this->service();
        $this->repondre(static fn(): array => ['preferences' => $service->preferences()]);
    }

    // =================================================================
    // Écriture (POST + CSRF)
    // =================================================================

    public function envoyer(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $reponseA = isset($d['reponse_a_id']) ? (int) $d['reponse_a_id'] : null;
        $this->repondre(static fn(): array => ['message' => $service->envoyer($conversationId, (string) ($d['texte'] ?? ''), $reponseA)], 201);
    }

    public function modifier(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $messageId = $this->identifiant($id);
        $this->repondre(static fn(): array => ['message' => $service->modifier($messageId, (string) ($d['texte'] ?? ''))]);
    }

    public function supprimer(string $id): void
    {
        [$service] = $this->ecriture();
        $messageId = $this->identifiant($id);
        $this->repondre(static fn(): array => ['message' => $service->supprimer($messageId)]);
    }

    public function transferer(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $messageId = $this->identifiant($id);
        $vers = (int) ($d['conversation_id'] ?? 0);
        $this->repondre(static fn(): array => ['message' => $service->transferer($messageId, $vers)], 201);
    }

    public function marquerLu(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $jusquA = (int) ($d['jusqu_a'] ?? 0);
        $this->repondre(static function () use ($service, $conversationId, $jusquA): array {
            $service->marquerLu($conversationId, $jusquA);

            return [];
        });
    }

    public function epingler(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $messageId = (int) ($d['message_id'] ?? 0);
        $this->repondre(static function () use ($service, $conversationId, $messageId): array {
            $service->epingler($conversationId, $messageId);

            return [];
        });
    }

    public function desepingler(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $messageId = (int) ($d['message_id'] ?? 0);
        $this->repondre(static function () use ($service, $conversationId, $messageId): array {
            $service->desepingler($conversationId, $messageId);

            return [];
        });
    }

    public function sourdine(string $id): void
    {
        $this->reglage($id, 'sourdine');
    }

    public function archiver(string $id): void
    {
        $this->reglage($id, 'archivee');
    }

    public function ouvrirDirecte(): void
    {
        [$service, $d] = $this->ecriture();
        $autre = (int) ($d['user_id'] ?? 0);
        $this->repondre(static fn(): array => ['conversation_id' => $service->ouvrirDirecte($autre)]);
    }

    public function creerGroupe(): void
    {
        [$service, $d] = $this->ecriture();
        $membres = is_array($d['membres'] ?? null) ? $d['membres'] : [];
        $this->repondre(static fn(): array => [
            'conversation_id' => $service->creerGroupe((string) ($d['nom'] ?? ''), (string) ($d['description'] ?? ''), $membres),
        ], 201);
    }

    public function ajouterMembres(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $ids = is_array($d['user_ids'] ?? null) ? $d['user_ids'] : [];
        $this->repondre(static fn(): array => ['ajoutes' => $service->ajouterMembres($conversationId, $ids)]);
    }

    public function retirerMembre(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $userId = (int) ($d['user_id'] ?? 0);
        $this->repondre(static function () use ($service, $conversationId, $userId): array {
            $service->retirerMembre($conversationId, $userId);

            return [];
        });
    }

    public function changerRole(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $userId = (int) ($d['user_id'] ?? 0);
        $role = (string) ($d['role'] ?? '');
        $this->repondre(static function () use ($service, $conversationId, $userId, $role): array {
            $service->changerRole($conversationId, $userId, $role);

            return [];
        });
    }

    public function quitter(string $id): void
    {
        [$service] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $this->repondre(static function () use ($service, $conversationId): array {
            $service->quitter($conversationId);

            return [];
        });
    }

    public function renommer(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $nom = (string) ($d['nom'] ?? '');
        $description = array_key_exists('description', $d) ? (string) $d['description'] : null;
        $this->repondre(static function () use ($service, $conversationId, $nom, $description): array {
            $service->renommer($conversationId, $nom, $description);

            return [];
        });
    }

    public function annonces(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $actif = filter_var($d['actif'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->repondre(static function () use ($service, $conversationId, $actif): array {
            $service->passerEnAnnonces($conversationId, $actif);

            return [];
        });
    }

    public function enregistrerPreferences(): void
    {
        [$service, $d] = $this->ecriture();
        $this->repondre(static fn(): array => ['preferences' => $service->enregistrerPreferences($d)]);
    }

    // =================================================================
    // Fichiers (lot 3)
    // =================================================================

    /**
     * Un fichier par requête (multipart : fichier, legende?, reponse_a_id?).
     * Il devient un message de la discussion : 201 { "message": ... }.
     */
    public function envoyerFichier(string $id): void
    {
        [$service, $d] = $this->ecritureFichier();
        $conversationId = $this->identifiant($id);
        $reponseA = isset($d['reponse_a_id']) ? (int) $d['reponse_a_id'] : null;
        $legende = is_string($d['legende'] ?? null) ? $d['legende'] : '';
        $fichier = $this->fichierTransmis('fichier');

        $this->repondre(
            static fn(): array => ['message' => $service->envoyerFichier($conversationId, $fichier, $legende, $reponseA)],
            201
        );
    }

    /** Aperçu (PDF, images) ou téléchargement (?telecharger=1, et tout le reste). */
    public function piece(string $id): void
    {
        $service = $this->service();
        $pieceId = $this->identifiant($id);
        $fichier = $this->ouvrir(static fn(): array => $service->fichierAServir($pieceId));

        $enLigne = $fichier['en_ligne'] && empty($_GET['telecharger']);
        $this->servir($fichier['chemin'], $fichier['mime'], $fichier['nom'], $enLigne, 'private, max-age=0, must-revalidate');
    }

    public function miniature(string $id): void
    {
        $service = $this->service();
        $pieceId = $this->identifiant($id);
        $fichier = $this->ouvrir(static fn(): array => $service->fichierAServir($pieceId, true));

        // Le contenu d'une pièce ne change jamais : le navigateur peut garder
        // la vignette, pour lui seul.
        $this->servir($fichier['chemin'], $fichier['mime'], $fichier['nom'], true, 'private, max-age=86400');
    }

    public function favori(string $id): void
    {
        [$service, $d] = $this->ecriture();
        $pieceId = $this->identifiant($id);
        $actif = filter_var($d['actif'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->repondre(static function () use ($service, $pieceId, $actif): array {
            $service->marquerFavori($pieceId, $actif);

            return ['favori' => $actif];
        });
    }

    public function documents(): void
    {
        $service = $this->service();
        $criteres = [];
        foreach (['q', 'tri', 'ordre', 'conversation_id', 'expediteur_id', 'categorie', 'du', 'au', 'favoris', 'page'] as $cle) {
            if (isset($_GET[$cle]) && is_string($_GET[$cle])) {
                $criteres[$cle] = $_GET[$cle];
            }
        }
        $this->repondre(static fn(): array => $service->documents($criteres));
    }

    /** Photo du groupe (multipart : photo), par un administrateur du groupe. */
    public function definirPhoto(string $id): void
    {
        [$service] = $this->ecritureFichier();
        $conversationId = $this->identifiant($id);
        $fichier = $this->fichierTransmis('photo');
        $this->repondre(static fn(): array => $service->definirPhoto($conversationId, $fichier));
    }

    public function retirerPhoto(string $id): void
    {
        [$service] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $this->repondre(static function () use ($service, $conversationId): array {
            $service->retirerPhoto($conversationId);

            return ['photo' => null];
        });
    }

    public function photo(string $id): void
    {
        $service = $this->service();
        $conversationId = $this->identifiant($id);
        $photo = $this->ouvrir(static fn(): array => $service->photo($conversationId));

        // L'adresse porte la version (?v=) : une nouvelle photo en change.
        $this->servir($photo['chemin'], $photo['mime'], 'photo-groupe.' . pathinfo($photo['chemin'], PATHINFO_EXTENSION), true, 'private, max-age=86400');
    }

    // =================================================================
    // Outillage
    // =================================================================

    private function reglage(string $id, string $reglage): void
    {
        [$service, $d] = $this->ecriture();
        $conversationId = $this->identifiant($id);
        $actif = filter_var($d['actif'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->repondre(static function () use ($service, $conversationId, $reglage, $actif): array {
            $service->regler($conversationId, $reglage, $actif);

            return [];
        });
    }

    /**
     * Les groupes automatiques de l'utilisateur courant (lot 5) : son agence,
     * « Tout LBP », « Annonces Direction ». Rien n'est relu tant que son
     * agence, son statut et ses rôles n'ont pas bougé depuis moins de dix
     * minutes — le mémo vit dans la session. Un échec ne prive jamais
     * l'utilisateur de sa liste : la passe de nuit rattrapera.
     */
    private function synchroniserGroupesAutomatiques(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        try {
            [, $memo] = GroupesAutomatiques::parDefaut()->synchroniserCompteSiBesoin([
                'id' => (int) $user->id,
                'agence_id' => $user->agenceId,
                'actif' => $user->status === 'active',
                'admin' => $user->isAdmin,
                'roles' => $user->roles,
            ], Session::get(self::MEMO_GROUPES));
            Session::set(self::MEMO_GROUPES, $memo);
        } catch (\Throwable $e) {
            error_log('[Messagerie groupes] utilisateur ' . (int) $user->id . ' : ' . $e->getMessage());
        }
    }

    /**
     * Session valide et compte actif, sinon 401 en JSON. Même règle que
     * AuthMiddleware : un compte désactivé perd sa session.
     */
    private function service(): MessagerieService
    {
        $user = Auth::check() ? Auth::user() : null;

        if ($user === null || $user->status !== 'active') {
            if ($user !== null) {
                Session::forget('auth_user_id');
            }
            $this->json(['ok' => false, 'message' => 'Votre session a expiré. Reconnectez-vous.'], 401);
        }

        return (new MessagerieService(
            new MessagerieRepository(Database::getConnection()),
            (int) $user->id,
            MessagerieAcces::estDirection(),
        ))->avecUrlApi(View::url('messagerie/api'));
    }

    /**
     * POST multipart. Quand le corps dépasse post_max_size, PHP vide $_POST
     * et $_FILES sans rien dire — jeton CSRF compris : on répond donc 413
     * avant de conclure à une page expirée.
     *
     * @return array{0: MessagerieService, 1: array<string, mixed>}
     */
    private function ecritureFichier(): array
    {
        $this->service();

        $longueur = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($longueur > 0 && $_POST === [] && $_FILES === [] && $longueur > MessagerieStockage::octetsIni((string) ini_get('post_max_size'))) {
            $this->json(['ok' => false, 'message' => MessagerieStockage::messageTaille()], 413);
        }

        return $this->ecriture();
    }

    /** @return array<string, mixed> l'entrée de $_FILES, ou une entrée « aucun fichier » */
    private function fichierTransmis(string $champ): array
    {
        $fichier = $_FILES[$champ] ?? null;

        return is_array($fichier) ? $fichier : ['error' => UPLOAD_ERR_NO_FILE];
    }

    /**
     * Le refus d'accès à un fichier répond en JSON, comme le reste de l'API :
     * une balise <img> l'ignore, l'écran peut le lire.
     *
     * @return array<string, mixed>
     */
    private function ouvrir(callable $action): array
    {
        try {
            return $action();
        } catch (MessagerieException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }
    }

    /**
     * Sert un fichier vérifié par le service. Le type est celui reconnu à la
     * réception, jamais deviné par le navigateur (nosniff) ; le nom d'origine
     * voyage en RFC 5987 pour garder ses accents, avec un repli ASCII pour
     * les logiciels anciens.
     */
    private function servir(string $chemin, string $mime, string $nom, bool $enLigne, string $cache): never
    {
        // Un long téléchargement ne doit pas bloquer le relevé des 3 secondes,
        // qui attendrait sinon la libération de la session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Repli ASCII : « Relevé été.pdf » => « Releve ete.pdf ». Selon le
        // système, la translittération écrit l'accent à part (« e' ») : on
        // l'enlève avant de remplacer le reste.
        $translittere = (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom) ?: 'fichier');
        $ascii = (string) preg_replace('/[^A-Za-z0-9._\- ]/', '_', str_replace(["'", '`', '^', '"', '~'], '', $translittere));

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLigne ? 'inline' : 'attachment')
            . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nom));
        header('Cache-Control: ' . $cache);

        readfile($chemin);
        exit;
    }

    /**
     * POST authentifié et protégé par le jeton CSRF.
     *
     * @return array{0: MessagerieService, 1: array<string, mixed>}
     */
    private function ecriture(): array
    {
        $service = $this->service();
        $donnees = $this->donnees();

        $jeton = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $donnees['_csrf_token'] ?? null;
        if (!Csrf::verify(is_string($jeton) ? $jeton : null)) {
            $this->json(['ok' => false, 'message' => 'La page a expiré. Rechargez-la puis recommencez.'], 419);
        }

        return [$service, $donnees];
    }

    /** Corps JSON ou formulaire classique. @return array<string, mixed> */
    private function donnees(): array
    {
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($type, 'application/json')) {
            $corps = json_decode((string) file_get_contents('php://input'), true);

            return is_array($corps) ? $corps : [];
        }

        return $_POST;
    }

    private function identifiant(string $id): int
    {
        if (!ctype_digit($id) || (int) $id <= 0) {
            $this->json(['ok' => false, 'message' => 'Introuvable.'], 404);
        }

        return (int) $id;
    }

    private function repondre(callable $action, int $statut = 200): void
    {
        try {
            $resultat = $action();
        } catch (MessagerieException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        $this->json(['ok' => true] + $resultat, $statut);
    }

    /** @param array<string, mixed> $donnees */
    private function json(array $donnees, int $statut): never
    {
        header('Cache-Control: no-store');
        Response::json($donnees, $statut);
        exit;
    }
}
