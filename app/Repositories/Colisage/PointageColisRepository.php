<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;

/**
 * Pointage des colis au départ et à la réception.
 *
 * Un « départ » est une ligne de lbp_expeditions : l'agence d'envoi y rattache
 * les colis qu'elle expédie, l'agence d'arrivée les coche un par un. Le module
 * Groupage s'appuyait déjà sur cette table mais n'avait jamais servi en
 * production (aucune ligne au 14/09/2026) : le pointage la reprend plutôt que
 * de créer un second système.
 *
 * Deux pièges de la base :
 * - lbp_colis.statut n'accepte que enregistre, facture, en_transit, arrive,
 *   livre, retire et annule. Les anciennes valeurs EN_PRÉPARATION ou
 *   RÉCEPTIONNÉ sont refusées en mode strict (MariaDB en production) : on
 *   n'écrit ici que des valeurs de l'enum.
 * - PDO tourne sans émulation des requêtes préparées : un même paramètre nommé
 *   ne peut pas figurer deux fois dans une requête.
 *
 * Ce fichier ne dépend que de PDO : le script de reprise l'inclut directement,
 * sans charger l'application.
 */
final class PointageColisRepository
{
    /** Statuts d'un colis qui n'est plus à pointer. */
    public const STATUTS_TERMINES = ['retire', 'livre', 'annule'];

    /**
     * Colis enregistré, pas encore parti, et destiné à une autre agence que la
     * sienne. Condition partagée par l'écran de départ et par la reprise, pour
     * qu'ils ne divergent jamais.
     */
    private const A_EXPEDIER = "c.expedition_id IS NULL
        AND c.agence_arrivee_id IS NOT NULL
        AND c.agence_arrivee_id <> c.agence_depart_id
        AND c.statut IN ('enregistre', 'facture')";

    public function __construct(private PDO $pdo)
    {
    }

    /** Heure de la base : la seule référence commune aux dates qu'elle a écrites. */
    public function maintenant(): string
    {
        return (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Agences
    // ------------------------------------------------------------------

    /** @return array<int, array{id:int, name:string}> */
    public function agencesActives(): array
    {
        $lignes = $this->pdo->query('SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']],
            $lignes
        );
    }

    // ------------------------------------------------------------------
    // Départ
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function colisAExpedier(int $agenceDepartId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.numero_tracking, c.nombre_colis, c.poids_total, c.statut, c.created_at,
                   c.agence_arrivee_id, a.name AS agence_arrivee,
                   exp.name AS expediteur, dest.name AS destinataire
            FROM lbp_colis c
            LEFT JOIN company_sites a ON a.id = c.agence_arrivee_id
            LEFT JOIN lbp_clients exp ON exp.id = c.expediteur_id
            LEFT JOIN lbp_clients dest ON dest.id = c.destinataire_id
            WHERE c.agence_depart_id = :agence
              AND ' . self::A_EXPEDIER . '
            ORDER BY a.name ASC, c.created_at ASC
        ');
        $stmt->execute(['agence' => $agenceDepartId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Parmi les colis cochés, ceux qui peuvent réellement partir de cette agence.
     *
     * @param array<int, int> $ids
     * @return array<int, array{id:int, numero_tracking:string, agence_arrivee_id:int}>
     */
    public function colisPourDepart(array $ids, int $agenceDepartId): array
    {
        if ($ids === []) {
            return [];
        }

        $marques = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.numero_tracking, c.agence_arrivee_id
            FROM lbp_colis c
            WHERE c.id IN ({$marques})
              AND c.agence_depart_id = ?
              AND " . self::A_EXPEDIER . '
        ');
        $stmt->execute([...array_values($ids), $agenceDepartId]);

        return array_map(
            static fn (array $l): array => [
                'id' => (int) $l['id'],
                'numero_tracking' => (string) $l['numero_tracking'],
                'agence_arrivee_id' => (int) $l['agence_arrivee_id'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function creerDepart(
        string $reference,
        string $transport,
        int $agenceDepartId,
        int $agenceArriveeId,
        ?int $userId,
        bool $reprise
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_expeditions
                (reference, type_transport, agence_depart_id, agence_arrivee_id, date_depart_prevue,
                 statut, date_depart_effective, parti_par_id, est_reprise, created_at)
            VALUES
                (:reference, :transport, :depart, :arrivee, CURDATE(),
                 'EN_TRANSIT', NOW(), :user, :reprise, NOW())
        ");
        $stmt->execute([
            'reference' => $reference,
            'transport' => $transport,
            'depart' => $agenceDepartId,
            'arrivee' => $agenceArriveeId,
            'user' => $userId,
            'reprise' => $reprise ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<int, int> $ids */
    public function rattacherAuDepart(array $ids, int $expeditionId): int
    {
        if ($ids === []) {
            return 0;
        }

        $marques = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET expedition_id = ?, statut = 'en_transit', statut_depart = 'PARTI',
                date_statut_depart = NOW(), updated_at = NOW()
            WHERE id IN ({$marques}) AND expedition_id IS NULL
        ");
        $stmt->execute([$expeditionId, ...array_values($ids)]);

        return $stmt->rowCount();
    }

    public function journaliser(
        int $colisId,
        ?int $expeditionId,
        ?int $agenceId,
        string $action,
        string $source,
        ?int $userId
    ): void {
        $this->pdo->prepare('
            INSERT INTO lbp_colis_pointages (colis_id, expedition_id, agence_id, action, source, user_id, created_at)
            VALUES (:colis, :expedition, :agence, :action, :source, :user, NOW())
        ')->execute([
            'colis' => $colisId,
            'expedition' => $expeditionId,
            'agence' => $agenceId,
            'action' => $action,
            'source' => $source,
            'user' => $userId,
        ]);
    }

    // ------------------------------------------------------------------
    // Départs et leurs compteurs
    // ------------------------------------------------------------------

    private function selectDeparts(string $conditions): string
    {
        return "
            SELECT e.id, e.reference, e.type_transport, e.statut, e.est_reprise,
                   e.agence_depart_id, e.agence_arrivee_id,
                   COALESCE(e.date_depart_effective, e.created_at) AS date_depart,
                   e.date_premiere_reception,
                   d.name AS agence_depart, a.name AS agence_arrivee,
                   COUNT(c.id) AS envoyes,
                   COALESCE(SUM(CASE WHEN c.date_reception IS NOT NULL OR c.statut IN ('retire', 'livre')
                                     THEN 1 ELSE 0 END), 0) AS recus
            FROM lbp_expeditions e
            LEFT JOIN company_sites d ON d.id = e.agence_depart_id
            LEFT JOIN company_sites a ON a.id = e.agence_arrivee_id
            LEFT JOIN lbp_colis c ON c.expedition_id = e.id AND c.statut <> 'annule'
            WHERE {$conditions}
            GROUP BY e.id, e.reference, e.type_transport, e.statut, e.est_reprise,
                     e.agence_depart_id, e.agence_arrivee_id, e.date_depart_effective, e.created_at,
                     e.date_premiere_reception, d.name, a.name
        ";
    }

    /**
     * Départs dont la réception n'est pas terminée.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsAttendus(?int $agenceArriveeId): array
    {
        $conditions = "e.statut IN ('EN_TRANSIT', 'ARRIVE')";
        $params = [];

        if ($agenceArriveeId !== null) {
            $conditions .= ' AND e.agence_arrivee_id = :agence';
            $params['agence'] = $agenceArriveeId;
        }

        $stmt = $this->pdo->prepare($this->selectDeparts($conditions) . ' ORDER BY date_depart DESC');
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function departAvecCompteurs(int $expeditionId): ?array
    {
        $stmt = $this->pdo->prepare($this->selectDeparts('e.id = :id'));
        $stmt->execute(['id' => $expeditionId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    /**
     * Départs d'une période, pour le tableau de suivi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsDeLaPeriode(string $du, string $au, ?int $agenceId): array
    {
        $conditions = "e.statut IN ('EN_TRANSIT', 'ARRIVE', 'CLOTURE')
            AND COALESCE(e.date_depart_effective, e.created_at) >= :du
            AND COALESCE(e.date_depart_effective, e.created_at) < :au";
        $params = [
            'du' => $du . ' 00:00:00',
            'au' => date('Y-m-d', (int) strtotime($au . ' +1 day')) . ' 00:00:00',
        ];

        if ($agenceId !== null) {
            $conditions .= ' AND (e.agence_depart_id = :agence_depart OR e.agence_arrivee_id = :agence_arrivee)';
            $params['agence_depart'] = $agenceId;
            $params['agence_arrivee'] = $agenceId;
        }

        $stmt = $this->pdo->prepare($this->selectDeparts($conditions) . ' ORDER BY date_depart DESC');
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Départs encore ouverts d'une agence d'envoi, pour lui montrer ses manquants.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsOuvertsDepuis(int $agenceDepartId): array
    {
        $stmt = $this->pdo->prepare(
            $this->selectDeparts("e.statut IN ('EN_TRANSIT', 'ARRIVE') AND e.agence_depart_id = :agence")
            . ' ORDER BY date_depart DESC'
        );
        $stmt->execute(['agence' => $agenceDepartId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function colisDuDepart(int $expeditionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.numero_tracking, c.nombre_colis, c.poids_total, c.statut,
                   c.date_reception, c.reception_hors_liste,
                   exp.name AS expediteur, dest.name AS destinataire,
                   u.full_name AS recu_par
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON exp.id = c.expediteur_id
            LEFT JOIN lbp_clients dest ON dest.id = c.destinataire_id
            LEFT JOIN users u ON u.id = c.recu_par_id
            WHERE c.expedition_id = :id AND c.statut <> 'annule'
            ORDER BY c.numero_tracking ASC
        ");
        $stmt->execute(['id' => $expeditionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, int> */
    public function colisNonRecusDuDepart(int $expeditionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM lbp_colis
            WHERE expedition_id = :id
              AND date_reception IS NULL
              AND statut NOT IN ('retire', 'livre', 'annule')
        ");
        $stmt->execute(['id' => $expeditionId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // ------------------------------------------------------------------
    // Réception
    // ------------------------------------------------------------------

    private const SELECT_COLIS = '
        SELECT c.id, c.numero_tracking, c.statut, c.expedition_id, c.date_reception,
               c.agence_depart_id, c.agence_arrivee_id,
               e.agence_arrivee_id AS depart_agence_arrivee_id,
               e.agence_depart_id AS depart_agence_depart_id
        FROM lbp_colis c
        LEFT JOIN lbp_expeditions e ON e.id = c.expedition_id
    ';

    /** @return array<string, mixed>|null */
    public function trouverColis(int $colisId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_COLIS . ' WHERE c.id = :id LIMIT 1');
        $stmt->execute(['id' => $colisId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    /** @return array<string, mixed>|null */
    public function trouverColisParCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_COLIS . ' WHERE c.numero_tracking = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    public function marquerRecu(int $colisId, ?int $userId, bool $horsListe): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET statut = 'arrive',
                date_reception = NOW(),
                recu_par_id = :user,
                reception_hors_liste = :hors_liste,
                date_arrivee_agence = COALESCE(date_arrivee_agence, NOW()),
                updated_at = NOW()
            WHERE id = :id
              AND date_reception IS NULL
              AND statut NOT IN ('retire', 'livre', 'annule')
        ");
        $stmt->execute(['user' => $userId, 'hors_liste' => $horsListe ? 1 : 0, 'id' => $colisId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Défait un pointage de réception.
     *
     * L'ordre des affectations compte : MySQL et MariaDB les évaluent de gauche à
     * droite, avec les valeurs déjà modifiées. date_arrivee_agence doit donc être
     * comparée à date_reception avant que celle-ci ne soit vidée.
     */
    public function annulerReception(int $colisId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_colis
            SET date_arrivee_agence = CASE WHEN date_arrivee_agence = date_reception THEN NULL ELSE date_arrivee_agence END,
                statut = CASE WHEN expedition_id IS NULL THEN 'enregistre' ELSE 'en_transit' END,
                date_reception = NULL,
                recu_par_id = NULL,
                reception_hors_liste = 0,
                updated_at = NOW()
            WHERE id = :id
              AND date_reception IS NOT NULL
              AND statut NOT IN ('retire', 'livre', 'annule')
        ");
        $stmt->execute(['id' => $colisId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Aligne le statut du départ sur ses compteurs.
     *
     * Le premier colis coché déclenche le délai des manquants. Si tous les
     * pointages sont défaits, l'horloge repart de zéro : un premier coup de
     * douchette par erreur ne doit pas faire courir le délai.
     */
    public function actualiserDepart(int $expeditionId, int $envoyes, int $recus): void
    {
        if ($recus === 0) {
            $sql = "UPDATE lbp_expeditions SET statut = 'EN_TRANSIT', date_premiere_reception = NULL, updated_at = NOW() WHERE id = :id";
        } elseif ($recus >= $envoyes) {
            $sql = "UPDATE lbp_expeditions SET statut = 'CLOTURE', date_premiere_reception = COALESCE(date_premiere_reception, NOW()), updated_at = NOW() WHERE id = :id";
        } else {
            $sql = "UPDATE lbp_expeditions SET statut = 'ARRIVE', date_premiere_reception = COALESCE(date_premiere_reception, NOW()), updated_at = NOW() WHERE id = :id";
        }

        $this->pdo->prepare($sql)->execute(['id' => $expeditionId]);
    }

    // ------------------------------------------------------------------
    // Hors liste et historique
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function horsListe(string $du, string $au, ?int $agenceId): array
    {
        $sql = "
            SELECT p.created_at, c.numero_tracking, u.full_name AS par,
                   r.name AS agence_reception, d.name AS agence_depart, a.name AS agence_prevue
            FROM lbp_colis_pointages p
            JOIN lbp_colis c ON c.id = p.colis_id
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN company_sites r ON r.id = p.agence_id
            LEFT JOIN company_sites d ON d.id = c.agence_depart_id
            LEFT JOIN company_sites a ON a.id = c.agence_arrivee_id
            WHERE p.action = 'HORS_LISTE'
              AND p.created_at >= :du
              AND p.created_at < :au
        ";
        $params = [
            'du' => $du . ' 00:00:00',
            'au' => date('Y-m-d', (int) strtotime($au . ' +1 day')) . ' 00:00:00',
        ];

        if ($agenceId !== null) {
            $sql .= ' AND (p.agence_id = :agence_reception OR c.agence_depart_id = :agence_depart)';
            $params['agence_reception'] = $agenceId;
            $params['agence_depart'] = $agenceId;
        }

        $stmt = $this->pdo->prepare($sql . ' ORDER BY p.created_at DESC LIMIT 200');
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function historique(int $expeditionId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.created_at, p.action, p.source, c.numero_tracking,
                   u.full_name AS par, s.name AS agence
            FROM lbp_colis_pointages p
            JOIN lbp_colis c ON c.id = p.colis_id
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN company_sites s ON s.id = p.agence_id
            WHERE p.expedition_id = :id
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT 300
        ');
        $stmt->execute(['id' => $expeditionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ------------------------------------------------------------------
    // Reprise de l'existant
    // ------------------------------------------------------------------

    public function existeUneReprise(): bool
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM lbp_expeditions WHERE est_reprise = 1')->fetchColumn() > 0;
    }

    /** @return array<int, array{agence_depart_id:int, agence_arrivee_id:int, agence_depart:string, agence_arrivee:string, nb:int}> */
    public function trajetsAReprendre(): array
    {
        $lignes = $this->pdo->query('
            SELECT c.agence_depart_id, c.agence_arrivee_id, d.name AS agence_depart, a.name AS agence_arrivee, COUNT(*) AS nb
            FROM lbp_colis c
            LEFT JOIN company_sites d ON d.id = c.agence_depart_id
            LEFT JOIN company_sites a ON a.id = c.agence_arrivee_id
            WHERE c.agence_depart_id IS NOT NULL
              AND ' . self::A_EXPEDIER . '
            GROUP BY c.agence_depart_id, c.agence_arrivee_id, d.name, a.name
            ORDER BY nb DESC
        ')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => [
            'agence_depart_id' => (int) $l['agence_depart_id'],
            'agence_arrivee_id' => (int) $l['agence_arrivee_id'],
            'agence_depart' => (string) ($l['agence_depart'] ?? ''),
            'agence_arrivee' => (string) ($l['agence_arrivee'] ?? ''),
            'nb' => (int) $l['nb'],
        ], $lignes);
    }

    /** @return array<int, int> */
    public function idsAExpedierEntre(int $agenceDepartId, int $agenceArriveeId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id FROM lbp_colis c
            WHERE c.agence_depart_id = :depart
              AND c.agence_arrivee_id = :arrivee
              AND ' . self::A_EXPEDIER . '
        ');
        $stmt->execute(['depart' => $agenceDepartId, 'arrivee' => $agenceArriveeId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return array<int, array<string, mixed>> */
    public function colisSansAgenceArrivee(): array
    {
        return $this->pdo->query("
            SELECT c.numero_tracking, c.statut, c.created_at, d.name AS agence_depart
            FROM lbp_colis c
            LEFT JOIN company_sites d ON d.id = c.agence_depart_id
            WHERE c.agence_arrivee_id IS NULL
              AND c.statut NOT IN ('retire', 'livre', 'annule')
            ORDER BY c.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
