<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Models\Database;
use App\Repositories\Colisage\PointageColisRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Règles du pointage des colis au départ et à la réception.
 *
 * Arrêtées avec la direction le 14/09/2026 :
 * - l'agence d'envoi marque ses colis « partis » : c'est ce qui les rend
 *   attendus à l'agence d'arrivée ;
 * - l'agence d'arrivée les coche un par un, tous d'un coup, ou à la douchette ;
 * - l'arrivée d'un départ, c'est son premier colis coché. 24 heures plus tard,
 *   tout colis encore décoché est manquant, et l'agence d'envoi le voit ;
 * - tout utilisateur de l'agence peut cocher et décocher : chaque geste est
 *   tracé dans lbp_colis_pointages ;
 * - cocher n'envoie aucun SMS. Une erreur de pointage ne doit pas prévenir un
 *   client à tort ; « Prévenir les clients » est un geste séparé.
 */
final class PointageColisService
{
    public const DELAI_MANQUANT_HEURES = 24;

    /** Valeurs de lbp_expeditions.type_transport, avec leur libellé. */
    public const TRANSPORTS = [
        'AÉRIEN' => 'Aérien',
        'MARITIME' => 'Maritime',
        'TERRESTRE' => 'Terrestre',
    ];

    public const ETAT_EN_ROUTE = 'EN_ROUTE';
    public const ETAT_EN_COURS = 'EN_COURS';
    public const ETAT_MANQUANTS = 'MANQUANTS';
    public const ETAT_COMPLET = 'COMPLET';

    public function __construct(private PDO $pdo, private PointageColisRepository $repo)
    {
    }

    public static function creer(): self
    {
        $pdo = Database::getConnection();

        return new self($pdo, new PointageColisRepository($pdo));
    }

    /** @return array<int, array{id:int, name:string}> */
    public function agences(): array
    {
        return $this->repo->agencesActives();
    }

    // ------------------------------------------------------------------
    // Réception
    // ------------------------------------------------------------------

    /**
     * Départs en cours de réception, les plus récents d'abord.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsAttendus(?int $agenceArriveeId): array
    {
        $maintenant = $this->maintenant();
        $departs = [];

        foreach ($this->repo->departsAttendus($agenceArriveeId) as $depart) {
            $depart = $this->enrichir($depart, $maintenant);

            if ($depart['etat'] === self::ETAT_COMPLET) {
                continue;
            }

            $depart['colis'] = $this->repo->colisDuDepart((int) $depart['id']);
            $departs[] = $depart;
        }

        return $departs;
    }

    /**
     * Coche ou décoche un colis.
     *
     * @return array{ok:bool, message:string, depart?:array<string, mixed>}
     */
    public function pointer(int $colisId, bool $recu, ?int $agencePerimetre, ?int $userId): array
    {
        $colis = $this->repo->trouverColis($colisId);

        if ($colis === null || $colis['expedition_id'] === null) {
            return ['ok' => false, 'message' => "Ce colis n'appartient à aucun départ."];
        }

        if ($agencePerimetre !== null && (int) $colis['depart_agence_arrivee_id'] !== $agencePerimetre) {
            return ['ok' => false, 'message' => "Ce colis n'est pas attendu par votre agence."];
        }

        if (in_array((string) $colis['statut'], PointageColisRepository::STATUTS_TERMINES, true)) {
            return ['ok' => false, 'message' => 'Ce colis est déjà ' . $this->libelleStatut((string) $colis['statut']) . ' : son pointage ne peut plus changer.'];
        }

        $expeditionId = (int) $colis['expedition_id'];
        $agenceReception = (int) $colis['depart_agence_arrivee_id'];

        $this->pdo->beginTransaction();

        try {
            if ($recu) {
                $change = $this->repo->marquerRecu($colisId, $userId, false);
                $action = 'RECU';
            } else {
                $change = $this->repo->annulerReception($colisId);
                $action = 'ANNULE_RECU';
            }

            if ($change) {
                $this->repo->journaliser($colisId, $expeditionId, $agenceReception, $action, 'CASE', $userId);
            }

            $depart = $this->actualiser($expeditionId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $message = $recu
            ? 'Colis ' . $colis['numero_tracking'] . ' pointé reçu.'
            : 'Pointage du colis ' . $colis['numero_tracking'] . ' annulé.';

        return ['ok' => true, 'message' => $change ? $message : 'Rien à changer : le colis était déjà dans cet état.', 'depart' => $depart];
    }

    /**
     * Coche d'un coup tous les colis encore attendus d'un départ.
     *
     * @return array{ok:bool, message:string, nb:int}
     */
    public function toutPointer(int $expeditionId, ?int $agencePerimetre, ?int $userId): array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null) {
            return ['ok' => false, 'message' => 'Départ introuvable.', 'nb' => 0];
        }

        if ($agencePerimetre !== null && (int) $depart['agence_arrivee_id'] !== $agencePerimetre) {
            return ['ok' => false, 'message' => "Ce départ n'est pas destiné à votre agence.", 'nb' => 0];
        }

        $nb = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($this->repo->colisNonRecusDuDepart($expeditionId) as $colisId) {
                if ($this->repo->marquerRecu($colisId, $userId, false)) {
                    $this->repo->journaliser($colisId, $expeditionId, (int) $depart['agence_arrivee_id'], 'RECU', 'TOUT', $userId);
                    $nb++;
                }
            }

            $this->actualiser($expeditionId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'message' => $nb . ' colis pointé(s) reçu(s) pour le départ ' . $depart['reference'] . '.', 'nb' => $nb];
    }

    /**
     * Un coup de douchette à la réception.
     *
     * Un colis attendu par l'agence est coché. Un colis connu mais qui n'est
     * attendu nulle part ici est enregistré « reçu hors liste ». Un code inconnu
     * est refusé : on ne peut pas pointer un colis qui n'a jamais été saisi.
     *
     * @return array{ok:bool, message:string, tracking?:string, hors_liste?:bool, colis_id?:int, depart?:array<string, mixed>|null}
     */
    public function scanner(string $code, ?int $agenceReception, ?int $userId): array
    {
        $code = trim($code);

        if ($code === '') {
            return ['ok' => false, 'message' => 'Scannez ou saisissez un code colis.'];
        }

        if ($agenceReception === null || $agenceReception <= 0) {
            return ['ok' => false, 'message' => "Choisissez d'abord l'agence qui réceptionne."];
        }

        $colis = $this->repo->trouverColisParCode($code);

        if ($colis === null) {
            return ['ok' => false, 'message' => "Code {$code} inconnu : ce colis n'a jamais été enregistré."];
        }

        $tracking = (string) $colis['numero_tracking'];

        if (in_array((string) $colis['statut'], PointageColisRepository::STATUTS_TERMINES, true)) {
            return ['ok' => false, 'message' => "Le colis {$tracking} est déjà " . $this->libelleStatut((string) $colis['statut']) . '.', 'tracking' => $tracking];
        }

        if ($colis['date_reception'] !== null) {
            return ['ok' => true, 'message' => "Le colis {$tracking} était déjà pointé reçu.", 'tracking' => $tracking, 'hors_liste' => false, 'colis_id' => (int) $colis['id']];
        }

        $attenduIci = $colis['expedition_id'] !== null && (int) $colis['depart_agence_arrivee_id'] === $agenceReception;
        $expeditionId = $colis['expedition_id'] !== null ? (int) $colis['expedition_id'] : null;

        $this->pdo->beginTransaction();

        try {
            $this->repo->marquerRecu((int) $colis['id'], $userId, !$attenduIci);
            $this->repo->journaliser((int) $colis['id'], $expeditionId, $agenceReception, $attenduIci ? 'RECU' : 'HORS_LISTE', 'DOUCHETTE', $userId);
            $depart = $expeditionId !== null ? $this->actualiser($expeditionId) : null;
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'ok' => true,
            'message' => $attenduIci
                ? "Colis {$tracking} pointé reçu."
                : "Colis {$tracking} enregistré reçu hors liste : il n'était attendu dans aucun départ vers cette agence.",
            'tracking' => $tracking,
            'hors_liste' => !$attenduIci,
            'colis_id' => (int) $colis['id'],
            'depart' => $depart,
        ];
    }

    /**
     * « Prévenir les clients » : l'envoi des SMS n'est pas encore branché.
     *
     * @return array{ok:bool, message:string}
     */
    public function prevenirClients(int $expeditionId, ?int $agencePerimetre): array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null || ($agencePerimetre !== null && (int) $depart['agence_arrivee_id'] !== $agencePerimetre)) {
            return ['ok' => false, 'message' => 'Départ introuvable pour votre agence.'];
        }

        $recus = (int) $depart['recus'];

        return [
            'ok' => false,
            'message' => "L'envoi des SMS n'est pas encore activé. {$recus} colis reçu(s) sur ce départ : leurs clients seront prévenus dès qu'il le sera.",
        ];
    }

    // ------------------------------------------------------------------
    // Suivi
    // ------------------------------------------------------------------

    /**
     * @return array{departs:array<int, array<string, mixed>>, totaux:array<string, int>, trajets:array<int, array<string, mixed>>, hors_liste:array<int, array<string, mixed>>}
     */
    public function suivi(string $du, string $au, ?int $agenceId): array
    {
        $maintenant = $this->maintenant();
        $departs = array_map(fn (array $d): array => $this->enrichir($d, $maintenant), $this->repo->departsDeLaPeriode($du, $au, $agenceId));

        $totaux = ['departs' => count($departs), 'envoyes' => 0, 'recus' => 0, 'manquants' => 0, 'en_attente' => 0];
        $trajets = [];

        foreach ($departs as $d) {
            $totaux['envoyes'] += $d['envoyes'];
            $totaux['recus'] += $d['recus'];
            $totaux['manquants'] += $d['manquants'];
            $totaux['en_attente'] += $d['restants'] - $d['manquants'];

            $cle = $d['agence_depart_id'] . '-' . $d['agence_arrivee_id'];
            $trajets[$cle] ??= [
                'agence_depart' => (string) ($d['agence_depart'] ?? ''),
                'agence_arrivee' => (string) ($d['agence_arrivee'] ?? ''),
                'departs' => 0, 'envoyes' => 0, 'recus' => 0, 'manquants' => 0,
            ];
            $trajets[$cle]['departs']++;
            $trajets[$cle]['envoyes'] += $d['envoyes'];
            $trajets[$cle]['recus'] += $d['recus'];
            $trajets[$cle]['manquants'] += $d['manquants'];
        }

        return [
            'departs' => $departs,
            'totaux' => $totaux,
            'trajets' => array_values($trajets),
            'hors_liste' => $this->repo->horsListe($du, $au, $agenceId),
        ];
    }

    /**
     * Détail d'un départ, visible par les deux agences concernées et le réseau.
     *
     * @return array{depart:array<string, mixed>, colis:array<int, array<string, mixed>>, historique:array<int, array<string, mixed>>}|null
     */
    public function detailDepart(int $expeditionId, ?int $agencePerimetre): ?array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null) {
            return null;
        }

        if ($agencePerimetre !== null
            && (int) $depart['agence_depart_id'] !== $agencePerimetre
            && (int) $depart['agence_arrivee_id'] !== $agencePerimetre) {
            return null;
        }

        $depart = $this->enrichir($depart, $this->maintenant());
        $colis = [];

        foreach ($this->repo->colisDuDepart($expeditionId) as $c) {
            $c['etat'] = match (true) {
                in_array((string) $c['statut'], ['retire', 'livre'], true) => 'RETIRE',
                $c['date_reception'] !== null => ((int) $c['reception_hors_liste'] === 1 ? 'HORS_LISTE' : 'RECU'),
                $depart['etat'] === self::ETAT_MANQUANTS => 'MANQUANT',
                default => 'ATTENDU',
            };
            $colis[] = $c;
        }

        return ['depart' => $depart, 'colis' => $colis, 'historique' => $this->repo->historique($expeditionId)];
    }

    // ------------------------------------------------------------------
    // Règles communes
    // ------------------------------------------------------------------

    /**
     * Ajoute à un départ ses compteurs et son état.
     *
     * @param array<string, mixed> $depart
     * @return array<string, mixed>
     */
    public function enrichir(array $depart, DateTimeImmutable $maintenant): array
    {
        $envoyes = (int) ($depart['envoyes'] ?? 0);
        $recus = min((int) ($depart['recus'] ?? 0), $envoyes);
        $restants = $envoyes - $recus;

        $echeance = null;
        $delaiDepasse = false;

        if (!empty($depart['date_premiere_reception'])) {
            $echeance = (new DateTimeImmutable((string) $depart['date_premiere_reception']))
                ->modify('+' . self::DELAI_MANQUANT_HEURES . ' hours');
            $delaiDepasse = $maintenant >= $echeance;
        }

        $etat = match (true) {
            $envoyes > 0 && $restants === 0 => self::ETAT_COMPLET,
            $recus === 0 => self::ETAT_EN_ROUTE,
            $delaiDepasse => self::ETAT_MANQUANTS,
            default => self::ETAT_EN_COURS,
        };

        return array_merge($depart, [
            'envoyes' => $envoyes,
            'recus' => $recus,
            'restants' => $restants,
            'manquants' => $etat === self::ETAT_MANQUANTS ? $restants : 0,
            'echeance' => $echeance?->format('Y-m-d H:i:s'),
            'etat' => $etat,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function actualiser(int $expeditionId): ?array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null) {
            return null;
        }

        $this->repo->actualiserDepart($expeditionId, (int) $depart['envoyes'], (int) $depart['recus']);

        return $this->enrichir($this->repo->departAvecCompteurs($expeditionId) ?? $depart, $this->maintenant());
    }

    private function maintenant(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->repo->maintenant());
    }

    private function libelleStatut(string $statut): string
    {
        return match ($statut) {
            'retire' => 'retiré',
            'livre' => 'livré',
            'annule' => 'annulé',
            default => $statut,
        };
    }
}
