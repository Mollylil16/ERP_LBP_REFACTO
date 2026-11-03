<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Helpers\Auth;
use App\Models\Database;
use PDO;
use Throwable;

/**
 * Ce que la personne vient de faire — pour repondre a la cause, pas a la regle.
 *
 * Sans ce service, NOEL recite : « le decaissement se fait sur la fiche,
 * panneau du bas ». Avec lui, il comprend : « vous avez valide cette demande
 * ce matin a 9 h 12, c'est pour cela qu'elle vous est refusee ». Meme
 * assistant, mais l'un repete le manuel et l'autre regarde ce qui s'est passe.
 *
 * CE QUE LE JOURNAL CONTIENT, ET CE QU'IL NE CONTIENT PAS. Releve en
 * production le 22/10/2026 : il garde ce qui a REUSSI — colis cree, paiement
 * encaisse, point soumis, demande creee, facture supprimee. Il ne garde
 * presque aucun refus. NOEL ne peut donc pas lire « ce qui vient d'echouer » ;
 * il lit ce que la personne a fait, et en deduit pourquoi la suite coince.
 *
 * Il ne lit QUE les lignes de la personne qui parle. Le journal dit qui a
 * supprime quoi dans toute l'entreprise : en faire une fenetre ouverte a
 * chacun serait pire que tout ce qu'on a ferme cette semaine.
 */
final class NoelJournal
{
    /** Au-dela, ce n'est plus « ce que je viens de faire ». */
    public const MINUTES_RECENTES = 45;

    /**
     * Les actions du logiciel, dites en francais.
     *
     * Les cles sont les valeurs reellement presentes en base, relevees le
     * 22/10/2026 — et non celles qu'on imagine. Une action inconnue est
     * affichee telle quelle plutot que traduite de travers.
     *
     * @var array<string, string>
     */
    private const ACTIONS = [
        'create_parcel' => 'enregistre un colis',
        'delete_parcel' => 'supprime un colis',
        'withdraw_parcel' => 'remis un colis a son destinataire',
        'payment' => 'encaisse un reglement',
        'cancel_payment' => 'annule un reglement',
        'auto_facturer_colis' => 'facture un colis',
        'delete_invoice' => 'supprime une facture',
        'delete_invoice_auto' => 'supprime une facture automatique',
        'update_invoice_locked' => 'modifie une facture verrouillee',
        'submit_cash_report' => 'soumis un point de caisse',
        'create' => 'cree une demande',
        'validate' => 'valide une demande',
        'reject' => 'rejete une demande',
        'disburse' => 'decaisse une demande',
        'update_user' => 'modifie un compte',
        'login' => 'ouvert une session',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Ce que cette personne vient de faire, du plus recent au plus ancien.
     *
     * @return array<int, array{action: string, libelle: string, entite: string, entite_id: int, quand: string, heure: string}>
     */
    public function mesDernieresActions(int $limite = 8): array
    {
        $moi = (int) Auth::id();

        if ($moi <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT action, entity_type, entity_id, created_at
                  FROM lbp_audit_logs
                 WHERE user_id = :moi
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                 ORDER BY id DESC
                 LIMIT :limite
            ");
            $stmt->bindValue('moi', $moi, PDO::PARAM_INT);
            $stmt->bindValue('minutes', self::MINUTES_RECENTES, PDO::PARAM_INT);
            $stmt->bindValue('limite', max(1, $limite), PDO::PARAM_INT);
            $stmt->execute();
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL journal] lecture impossible : ' . $e->getMessage());

            return [];
        }

        return array_map(static function (array $ligne): array {
            $action = (string) $ligne['action'];

            return [
                'action' => $action,
                'libelle' => self::ACTIONS[$action] ?? $action,
                'entite' => (string) ($ligne['entity_type'] ?? ''),
                'entite_id' => (int) ($ligne['entity_id'] ?? 0),
                'quand' => (string) $ligne['created_at'],
                'heure' => date('G\hi', (int) strtotime((string) $ligne['created_at'])),
            ];
        }, $lignes);
    }

    /**
     * Cette personne a-t-elle elle-meme valide cette demande de fonds ?
     *
     * C'est la question qui explique le refus le plus frequent depuis le
     * partage du 14/10/2026 : personne ne decaisse ce qu'il a valide. Sans
     * cette verification, NOEL repondrait « le panneau est en bas de la
     * fiche » a quelqu'un qui le cherche en vain parce qu'il n'y sera jamais.
     */
    public function aiJeValide(int $demandeId): bool
    {
        $moi = (int) Auth::id();

        if ($moi <= 0 || $demandeId <= 0) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM lbp_demandes_fonds
                 WHERE id = :demande AND validateur_id = :moi
                 LIMIT 1
            ");
            $stmt->execute(['demande' => $demandeId, 'moi' => $moi]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[NOEL journal] validation illisible : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Mon agence a-t-elle deja soumis son point aujourd'hui, et a quelle heure ?
     *
     * « Je n'arrive pas a soumettre mon point » a souvent une reponse tres
     * simple : c'est deja fait. Le dire evite un appel, une inquietude, et
     * parfois une double saisie.
     */
    public function pointDeCaisseDuJour(): ?string
    {
        $agence = (int) (Auth::agenceId() ?? 0);

        if ($agence <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT date_soumission FROM lbp_etats_journaliers
                 WHERE agence_id = :agence AND date_jour = CURDATE()
                   AND statut IN ('soumis', 'consolide')
                 LIMIT 1
            ");
            $stmt->execute(['agence' => $agence]);
            $quand = $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[NOEL journal] point du jour illisible : ' . $e->getMessage());

            return null;
        }

        if (!$quand) {
            return null;
        }

        return date('G\hi', (int) strtotime((string) $quand));
    }

    /**
     * Combien de colis ont ete enregistres aujourd hui.
     *
     * Le chiffre est borne a ce que la personne a le droit de voir : son
     * agence, ou le reseau entier pour ceux dont c est le perimetre. Un agent
     * de Dokui qui demande « combien de colis aujourd hui » parle de Dokui, et
     * NOEL ne doit pas lui apprendre ce qui se passe a Adjame.
     *
     * Un colis saisi peut en porter plusieurs — la colonne « nombre_colis » —,
     * et c est ce total qui compte au guichet, pas le nombre de lignes. Les
     * deux sont rendus, parce que l ecart entre eux se dit : « 39 colis en 12
     * saisies » n est pas la meme journee que « 39 colis en 39 saisies ».
     *
     * @return array{colis: int, saisies: int, portee: string, reseau: bool}|null
     */
    public function colisDuJour(): ?array
    {
        $reseau = Auth::isAdmin() || Auth::hasAnyRole(Auth::ROLES_PORTEE_RESEAU);
        $agence = (int) (Auth::agenceId() ?? 0);

        // Ni le reseau, ni une agence : on ne sait pas de quoi on parlerait.
        if (!$reseau && $agence <= 0) {
            return null;
        }

        // Un colis annule n a pas ete enregistre : il ne compte pas.
        $conditions = ["c.statut <> 'annule'", 'DATE(c.created_at) = CURDATE()'];
        $parametres = [];

        if (!$reseau) {
            $conditions[] = 'c.agence_depart_id = :agence';
            $parametres['agence'] = $agence;
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT COALESCE(SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)), 0) AS colis,
                       COUNT(*) AS saisies
                  FROM lbp_colis c
                 WHERE ' . implode(' AND ', $conditions) . '
            ');
            $stmt->execute($parametres);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL journal] colis du jour illisibles : ' . $e->getMessage());

            return null;
        }

        return [
            'colis' => (int) ($ligne['colis'] ?? 0),
            'saisies' => (int) ($ligne['saisies'] ?? 0),
            'portee' => $reseau ? 'sur l ensemble du reseau' : 'a ' . $this->nomDeLAgence($agence),
            'reseau' => $reseau,
        ];
    }

    /**
     * Ce qu il doit rester dans le tiroir ce soir.
     *
     * On ne recalcule rien ici. L ecran Mouvements de Caisse sait deja le
     * faire, avec la portee de la personne, les deux monnaies et le report de
     * la veille. Refaire la somme de notre cote donnerait un jour deux
     * chiffres differents pour la meme caisse, et le logiciel perdrait la
     * seule chose qu on lui demande vraiment : etre d accord avec lui-meme.
     *
     * Rend null quand la portee de la personne est bloquee : le service
     * renvoie alors un report vide, et NOEL se contente d orienter.
     *
     * @return array{ouverture: float, mouvement: float, cloture: float, entrees: float, sorties: float}|null
     */
    public function soldeDeCaisse(): ?array
    {
        /*
         * Le service va chercher sa propre connexion, et Database::getConnection()
         * n echoue pas : il arrete le processus. Un try/catch ne protegerait
         * donc de rien, et la suite de tests tomberait des que NOEL compte.
         * Meme parade que NoelCarte pour le menu du colisage : on s abstient.
         */
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '') === 'testing') {
            return null;
        }

        try {
            $tableau = \App\Services\Finance\MouvementsCaisseService::creer()->tableauDuJour([]);
        } catch (Throwable $e) {
            error_log('[NOEL journal] solde de caisse illisible : ' . $e->getMessage());

            return null;
        }

        $continu = is_array($tableau['continu'] ?? null) ? $tableau['continu'] : [];

        if ($continu === []) {
            return null;
        }

        $kpis = is_array($tableau['kpis'] ?? null) ? $tableau['kpis'] : [];

        return [
            'ouverture' => (float) ($continu['ouverture'] ?? 0),
            'mouvement' => (float) ($continu['mouvement'] ?? 0),
            'cloture' => (float) ($continu['cloture'] ?? 0),
            'entrees' => (float) ($kpis['entrees']['total'] ?? 0),
            'sorties' => (float) ($kpis['sorties']['total'] ?? 0),
        ];
    }

    /** Le nom de l agence, pour que le chiffre dise de quelle agence il parle. */
    private function nomDeLAgence(int $agence): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name FROM company_sites WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $agence]);
            $nom = trim((string) $stmt->fetchColumn());
        } catch (Throwable $e) {
            return 'votre agence';
        }

        return $nom !== '' ? $nom : 'votre agence';
    }

    /**
     * Une facture de cette personne a-t-elle recu deux reglements identiques ?
     *
     * Le 15/10/2026, deux caissieres ont encaisse le meme acompte de 100 000 F
     * a 92 secondes d'intervalle sur la facture de la cliente MARIE. L'erreur
     * a ete decouverte le lendemain, par un appel. Elle se voyait pourtant au
     * premier coup d'oeil, le jour meme.
     *
     * @return array<int, array{facture: string, montant: float, ecart_secondes: int, heure: string}>
     */
    public function doublonsDeReglementDuJour(): array
    {
        $agence = (int) (Auth::agenceId() ?? 0);

        if ($agence <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT f.numero_facture,
                       p1.montant,
                       TIMESTAMPDIFF(SECOND, p1.date_paiement, p2.date_paiement) AS ecart,
                       p2.date_paiement AS quand
                  FROM lbp_paiements p1
                  JOIN lbp_paiements p2
                    ON p2.facture_id = p1.facture_id
                   AND p2.id > p1.id
                   AND p2.montant = p1.montant
                   AND TIMESTAMPDIFF(SECOND, p1.date_paiement, p2.date_paiement) BETWEEN 0 AND 300
                  JOIN lbp_factures f ON f.id = p1.facture_id
                 WHERE p1.agence_id = :agence
                   AND DATE(p1.date_paiement) = CURDATE()
                 ORDER BY p2.date_paiement DESC
                 LIMIT 5
            ");
            $stmt->execute(['agence' => $agence]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL journal] doublons illisibles : ' . $e->getMessage());

            return [];
        }

        return array_map(static fn(array $l): array => [
            'facture' => (string) $l['numero_facture'],
            'montant' => (float) $l['montant'],
            'ecart_secondes' => (int) $l['ecart'],
            'heure' => date('G\hi', (int) strtotime((string) $l['quand'])),
        ], $lignes);
    }

    /**
     * Le doublon, dit comme NOEL le dirait.
     *
     * @param array{facture: string, montant: float, ecart_secondes: int, heure: string} $doublon
     */
    public static function phraseDuDoublon(array $doublon): string
    {
        $ecart = $doublon['ecart_secondes'];
        $delai = $ecart < 60
            ? $ecart . ' secondes'
            : round($ecart / 60) . ' minutes';

        return sprintf(
            "La facture %s a recu deux reglements de %s XOF a %s d'intervalle, le dernier a %s. "
                . "Si c'est une erreur, annulez l'un des deux avant la cloture : apres, l'ecart de caisse est enregistre.",
            $doublon['facture'],
            number_format($doublon['montant'], 0, ',', ' '),
            $delai,
            $doublon['heure']
        );
    }
}
