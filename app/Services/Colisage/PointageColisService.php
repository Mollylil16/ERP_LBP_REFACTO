<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Models\Database;
use App\Repositories\Colisage\PointageColisRepository;
use App\Services\Colisage\ReceptionColisRegles as Regles;
use App\Support\HeureAgence;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Règles du pointage des colis au départ et à la réception.
 *
 * Arrêtées avec la direction le 14/09/2026 :
 * - l'agence d'envoi marque ses colis « partis » : c'est ce qui les rend
 *   attendus à l'agence d'arrivée ;
 * - l'agence d'arrivée les pointe un par un, tous d'un coup, ou à la douchette ;
 * - l'arrivée d'un départ, c'est son premier colis pointé. 24 heures plus tard,
 *   tout colis encore jamais pointé est manquant, et l'agence d'envoi le voit ;
 * - tout utilisateur de l'agence peut pointer et corriger : chaque geste est
 *   tracé dans lbp_colis_pointages ;
 * - pointer n'envoie aucun SMS. Une erreur de pointage ne doit pas prévenir un
 *   client à tort ; « Prévenir les clients » est un geste séparé.
 *
 * Complétées en octobre 2026 (ReceptionColisRegles) : trois choix par colis,
 * « Reçu », « Reçu endommagé », « Non reçu », avec un commentaire. « Non
 * reçu » rend le colis manquant tout de suite. « Tout cocher » et la douchette
 * marquent « Reçu ».
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

    /** Périodes du rapport de réception. */
    public const PERIODES = ['jour' => 'Jour', 'semaine' => 'Semaine', 'periode' => 'Période choisie'];

    public function __construct(private PDO $pdo, private PointageColisRepository $repo)
    {
    }

    public static function creer(): self
    {
        $pdo = Database::getConnection();

        return new self($pdo, new PointageColisRepository($pdo));
    }

    /** @return array<int, array{id:int, name:string, fuseau:string}> */
    public function agences(): array
    {
        return $this->repo->agencesActives();
    }

    /** @return array{id:int, name:string, fuseau:string}|null */
    public function agence(int $id): ?array
    {
        return $id > 0 ? $this->repo->agence($id) : null;
    }

    // ------------------------------------------------------------------
    // Réception
    // ------------------------------------------------------------------

    /**
     * Départs en cours de réception, les plus récents d'abord. Un départ dont
     * des colis sont dits « non reçus » reste ici : l'erreur doit pouvoir se
     * corriger quand le colis réapparaît.
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

            $depart['colis'] = $this->colisAvecEtat($depart);
            $departs[] = $depart;
        }

        return $departs;
    }

    /**
     * Donne un état à un colis : Reçu, Reçu endommagé, Non reçu, ou En attente
     * (pointage défait). $etat accepte encore l'ancien booléen de la case à
     * cocher : vrai pour reçu, faux pour défaire.
     *
     * @return array{ok:bool, message:string, depart?:array<string, mixed>|null, colis?:array<string, mixed>}
     */
    public function pointer(int $colisId, string|bool $etat, ?int $agencePerimetre, ?int $userId, mixed $commentaire = null): array
    {
        if (is_bool($etat)) {
            $etat = $etat ? Regles::RECU : Regles::EN_ATTENTE;
        }

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

        $avant = Regles::etatDe($colis);
        $transition = Regles::transition($avant, $etat, $commentaire, $colis['commentaire_reception'] ?? null);
        $tracking = (string) $colis['numero_tracking'];

        if (!$transition['ok']) {
            return ['ok' => false, 'message' => $transition['message'], 'colis' => $this->etatRendu($colisId, $avant, $colis['commentaire_reception'] ?? null)];
        }

        $expeditionId = (int) $colis['expedition_id'];

        if (!$transition['change']) {
            return [
                'ok' => true,
                'message' => $transition['message'],
                'depart' => $this->departEnrichi($expeditionId),
                'colis' => $this->etatRendu($colisId, $avant, $colis['commentaire_reception'] ?? null),
            ];
        }

        $agenceReception = (int) $colis['depart_agence_arrivee_id'];
        $apres = $transition['apres'];

        $this->pdo->beginTransaction();

        try {
            if (!$this->repo->appliquerEtat($colisId, $avant, $apres, $transition['commentaire'], $userId)) {
                $this->pdo->rollBack();

                return ['ok' => false, 'message' => "Le colis {$tracking} vient d'être pointé par quelqu'un d'autre : rechargez la page pour voir son état."];
            }

            $this->repo->journaliser($colisId, $expeditionId, $agenceReception, $transition['action'], 'CASE', $userId, $avant, $apres, $transition['commentaire']);
            $depart = $this->actualiser($expeditionId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $message = match ($apres) {
            Regles::RECU => "Colis {$tracking} pointé reçu.",
            Regles::ENDOMMAGE => "Colis {$tracking} pointé reçu endommagé.",
            Regles::NON_RECU => "Colis {$tracking} déclaré non reçu : l'agence d'envoi le voit manquant.",
            default => "Pointage du colis {$tracking} annulé : il est de nouveau en attente.",
        };

        if ($avant === $apres) {
            $message = "Commentaire du colis {$tracking} enregistré.";
        }

        return ['ok' => true, 'message' => $message, 'depart' => $depart, 'colis' => $this->etatRendu($colisId, $apres, $transition['commentaire'])];
    }

    /**
     * Marque « Reçu » d'un coup tous les colis encore jamais pointés d'un
     * départ. Ceux déjà dits endommagés ou non reçus ne bougent pas.
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
            foreach ($this->repo->colisEnAttenteDuDepart($expeditionId) as $colisId) {
                if ($this->repo->appliquerEtat($colisId, Regles::EN_ATTENTE, Regles::RECU, null, $userId)) {
                    $this->repo->journaliser($colisId, $expeditionId, (int) $depart['agence_arrivee_id'], 'RECU', 'TOUT', $userId, Regles::EN_ATTENTE, Regles::RECU);
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
     * Un colis attendu par l'agence est marqué « Reçu » — même s'il avait été
     * dit « non reçu » : le scanner, c'est l'avoir en main. Un colis connu mais
     * qui n'est attendu nulle part ici est enregistré « reçu hors liste ». Un
     * code inconnu est refusé : on ne peut pas pointer un colis qui n'a jamais
     * été saisi.
     *
     * @return array{ok:bool, message:string, tracking?:string, hors_liste?:bool, colis_id?:int, depart?:array<string, mixed>|null, colis?:array<string, mixed>}
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

        $avant = Regles::etatDe($colis);

        if (Regles::estEnMain($avant)) {
            return [
                'ok' => true,
                'message' => "Le colis {$tracking} était déjà pointé " . mb_strtolower(Regles::LIBELLES[$avant]) . '.',
                'tracking' => $tracking,
                'hors_liste' => false,
                'colis_id' => (int) $colis['id'],
            ];
        }

        $attenduIci = $colis['expedition_id'] !== null && (int) $colis['depart_agence_arrivee_id'] === $agenceReception;
        $expeditionId = $colis['expedition_id'] !== null ? (int) $colis['expedition_id'] : null;

        $this->pdo->beginTransaction();

        try {
            if (!$this->repo->appliquerEtat((int) $colis['id'], $avant, Regles::RECU, null, $userId, !$attenduIci)) {
                $this->pdo->rollBack();

                return ['ok' => false, 'message' => "Le colis {$tracking} vient d'être pointé par quelqu'un d'autre. Scannez-le de nouveau.", 'tracking' => $tracking];
            }

            $this->repo->journaliser((int) $colis['id'], $expeditionId, $agenceReception, $attenduIci ? 'RECU' : 'HORS_LISTE', 'DOUCHETTE', $userId, $avant, Regles::RECU);
            $depart = $expeditionId !== null ? $this->actualiser($expeditionId) : null;
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'ok' => true,
            'message' => $attenduIci
                ? "Colis {$tracking} pointé reçu." . ($avant === Regles::NON_RECU ? ' Il avait été dit non reçu : c’est corrigé.' : '')
                : "Colis {$tracking} enregistré reçu hors liste : il n'était attendu dans aucun départ vers cette agence.",
            'tracking' => $tracking,
            'hors_liste' => !$attenduIci,
            'colis_id' => (int) $colis['id'],
            'depart' => $depart,
            'colis' => $this->etatRendu((int) $colis['id'], Regles::RECU, null),
        ];
    }

    /**
     * « Prévenir les clients » : expédie les SMS d'arrivée aux destinataires des colis reçus.
     *
     * @return array{ok:bool, message:string}
     */
    public function prevenirClients(int $expeditionId, ?int $agencePerimetre): array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null || ($agencePerimetre !== null && (int) $depart['agence_arrivee_id'] !== $agencePerimetre)) {
            return ['ok' => false, 'message' => 'Départ introuvable pour votre agence.'];
        }

        $colisList = $this->repo->colisDuDepart($expeditionId);
        $infobip = new \App\Services\Shared\InfobipSmsService();
        $signature = $infobip->getCallCenterSignature();

        $envoyes = 0;
        $ignores = 0;

        foreach ($colisList as $colis) {
            // Seuls les colis pointés comme reçus
            if (empty($colis['date_reception']) && !in_array($colis['statut'], ['arrive', 'retire', 'livre'], true)) {
                continue;
            }

            $phone = $colis['destinataire_phone'] ?? null;
            if (empty($phone)) {
                $ignores++;
                continue;
            }

            $tracking = $colis['numero_tracking'] ?? '';
            $msg = "LBP : Bonjour, votre colis N° {$tracking} est arrive et disponible en agence. Contact Call Center : {$signature}.";

            $res = $infobip->sendSms((string) $phone, $msg);
            if ($res['success']) {
                $envoyes++;
            } else {
                $ignores++;
            }
        }

        if ($envoyes === 0 && $ignores === 0) {
            return [
                'ok' => false,
                'message' => "Aucun colis pointé comme reçu à notifier pour ce départ.",
            ];
        }

        return [
            'ok' => $envoyes > 0,
            'message' => "Notification SMS : {$envoyes} client(s) notifié(s) avec succès" . ($ignores > 0 ? " ({$ignores} en échec ou sans téléphone)." : "."),
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

        $totaux = ['departs' => count($departs), 'envoyes' => 0, 'recus' => 0, 'endommages' => 0, 'non_recus' => 0, 'manquants' => 0, 'en_attente' => 0];
        $trajets = [];

        foreach ($departs as $d) {
            $totaux['envoyes'] += $d['envoyes'];
            $totaux['recus'] += $d['recus'];
            $totaux['endommages'] += $d['endommages'];
            $totaux['non_recus'] += $d['non_recus'];
            $totaux['manquants'] += $d['manquants'];
            $totaux['en_attente'] += $d['delai_depasse'] ? 0 : $d['en_attente'];

            $cle = $d['agence_depart_id'] . '-' . $d['agence_arrivee_id'];
            $trajets[$cle] ??= [
                'agence_depart' => (string) ($d['agence_depart'] ?? ''),
                'agence_arrivee' => (string) ($d['agence_arrivee'] ?? ''),
                'departs' => 0, 'envoyes' => 0, 'recus' => 0, 'endommages' => 0, 'manquants' => 0,
            ];
            $trajets[$cle]['departs']++;
            $trajets[$cle]['envoyes'] += $d['envoyes'];
            $trajets[$cle]['recus'] += $d['recus'];
            $trajets[$cle]['endommages'] += $d['endommages'];
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

        if ($depart === null || !self::visiblePar($depart, $agencePerimetre)) {
            return null;
        }

        $depart = $this->enrichir($depart, $this->maintenant());

        return ['depart' => $depart, 'colis' => $this->colisAvecEtat($depart), 'historique' => $this->repo->historique($expeditionId)];
    }

    // ------------------------------------------------------------------
    // Rapport de réception
    // ------------------------------------------------------------------

    /**
     * Les filtres du rapport, tels que lus dans la requête : une période (un
     * jour, la semaine du lundi au dimanche qui contient ce jour, ou du … au
     * …), l'agence d'envoi, l'agence d'arrivée, un départ.
     *
     * @param array<string, mixed> $entree
     * @return array{periode:string, date:string, du:string, au:string, agence_envoi:?int, agence_arrivee:?int, depart:?int}
     */
    public static function filtresRapport(array $entree, string $aujourdhui): array
    {
        $date = static fn (mixed $v, string $defaut): string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && strtotime($v) !== false ? $v : $defaut;
        $entier = static fn (mixed $v): ?int => is_scalar($v) && (int) $v > 0 ? (int) $v : null;

        $periode = (string) ($entree['periode'] ?? 'jour');
        if (!isset(self::PERIODES[$periode])) {
            $periode = 'jour';
        }

        $jour = $date($entree['date'] ?? '', $aujourdhui);

        if ($periode === 'jour') {
            $du = $au = $jour;
        } elseif ($periode === 'semaine') {
            $lundi = new DateTimeImmutable($jour);
            $lundi = $lundi->modify('-' . ((int) $lundi->format('N') - 1) . ' days');
            $du = $lundi->format('Y-m-d');
            $au = $lundi->modify('+6 days')->format('Y-m-d');
        } else {
            $au = $date($entree['au'] ?? '', $aujourdhui);
            $du = $date($entree['du'] ?? '', (new DateTimeImmutable($au))->modify('-6 days')->format('Y-m-d'));
            if ($du > $au) {
                [$du, $au] = [$au, $du];
            }
        }

        return [
            'periode' => $periode,
            'date' => $jour,
            'du' => $du,
            'au' => $au,
            'agence_envoi' => $entier($entree['agence_envoi'] ?? null),
            'agence_arrivee' => $entier($entree['agence_arrivee'] ?? null),
            'depart' => $entier($entree['depart'] ?? null),
        ];
    }

    /**
     * Le rapport de réception : pour chaque départ, colis attendus, reçus,
     * reçus endommagés, non reçus, en attente, manquants, et le taux de
     * réception ; le détail des colis si $avecColis.
     *
     * Les comptes sont l'état au moment où l'on tire le rapport. Les jours
     * s'entendent dans le fuseau de l'agence d'arrivée choisie (ou de
     * l'agence de l'utilisateur) : la journée de Paris va de minuit à minuit
     * à Paris.
     *
     * $perimetre : l'agence de l'utilisateur, qui ne voit que les départs
     * qu'elle a envoyés ou reçus ; null pour la Direction.
     *
     * @param array{periode:string, date:string, du:string, au:string, agence_envoi:?int, agence_arrivee:?int, depart:?int} $filtres
     * @return array<string, mixed>
     */
    public function rapport(array $filtres, ?int $perimetre, bool $avecColis): array
    {
        $agenceFuseau = $filtres['agence_arrivee'] ?? $perimetre;
        $agence = $agenceFuseau !== null ? $this->repo->agence($agenceFuseau) : null;
        $fuseau = HeureAgence::fuseau($agence['fuseau'] ?? null);

        $du = HeureAgence::versServeur($filtres['du'] . ' 00:00:00', $fuseau);
        $au = HeureAgence::versServeur((new DateTimeImmutable($filtres['au']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00', $fuseau);

        $maintenant = $this->maintenant();
        $departs = array_map(
            fn (array $d): array => $this->enrichir($d, $maintenant),
            $this->repo->departsDuRapport($du, $au, $perimetre, $filtres['agence_envoi'], $filtres['agence_arrivee'])
        );

        $options = array_map(static fn (array $d): array => [
            'id' => (int) $d['id'],
            'libelle' => (string) $d['reference'] . ' · ' . ($d['agence_depart'] ?? '—') . ' → ' . ($d['agence_arrivee'] ?? '—'),
        ], $departs);

        // Un départ choisi se montre même hors de la période (le lien du
        // résumé du soir l'ouvre ainsi), mais jamais hors du périmètre.
        if ($filtres['depart'] !== null) {
            $choisi = $this->repo->departAvecCompteurs($filtres['depart']);
            $departs = $choisi !== null && self::visiblePar($choisi, $perimetre)
                ? [$this->enrichir($choisi, $maintenant)]
                : [];
        }

        if ($avecColis) {
            foreach ($departs as $i => $d) {
                $departs[$i]['colis'] = $this->colisAvecEtat($d);
            }
        }

        return [
            'filtres' => $filtres,
            'fuseau' => $fuseau,
            'departs' => $departs,
            'options_departs' => $options,
            'totaux' => self::totauxRapport($departs),
            'edite_le' => $maintenant->format('Y-m-d H:i:s'),
            'nom_fichier' => self::nomFichierRapport($this->nomAgenceDuRapport($filtres, $perimetre), $filtres['du'], $filtres['au']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $departs
     * @return array{departs:int, attendus:int, recus:int, endommages:int, non_recus:int, en_attente:int, manquants:int, taux:float}
     */
    public static function totauxRapport(array $departs): array
    {
        $t = ['departs' => count($departs), 'attendus' => 0, 'recus' => 0, 'endommages' => 0, 'non_recus' => 0, 'en_attente' => 0, 'manquants' => 0, 'taux' => 0.0];

        foreach ($departs as $d) {
            $t['attendus'] += (int) $d['envoyes'];
            $t['recus'] += (int) $d['recus'];
            $t['endommages'] += (int) $d['endommages'];
            $t['non_recus'] += (int) $d['non_recus'];
            $t['en_attente'] += (int) $d['en_attente'];
            $t['manquants'] += (int) $d['manquants'];
        }

        $t['taux'] = self::taux($t['recus'], $t['attendus']);

        return $t;
    }

    /**
     * Le taux de réception, en pour cent, à une décimale, arrondi vers le bas :
     * 999 colis sur 1 000 font 99,9 %, jamais 100 %.
     */
    public static function taux(int $recus, int $attendus): float
    {
        if ($attendus <= 0) {
            return 0.0;
        }

        return floor(min($recus, $attendus) * 1000 / $attendus) / 10;
    }

    /** « rapport_reception_paris-17-chemin-des-vignes_2026-10-19_2026-10-25 » */
    public static function nomFichierRapport(string $agence, string $du, string $au): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', self::sansAccents($agence)), '-'));

        return 'rapport_reception_' . ($slug !== '' ? mb_substr($slug, 0, 60) : 'toutes-agences') . '_' . $du . ($au !== $du ? '_' . $au : '');
    }

    // ------------------------------------------------------------------
    // Règles communes
    // ------------------------------------------------------------------

    /** Un départ se lit depuis ses deux agences ; la Direction ($perimetre null) les lit tous. */
    public static function visiblePar(array $depart, ?int $perimetre): bool
    {
        return $perimetre === null
            || (int) ($depart['agence_depart_id'] ?? 0) === $perimetre
            || (int) ($depart['agence_arrivee_id'] ?? 0) === $perimetre;
    }

    /**
     * Ajoute à un départ ses compteurs et son état.
     *
     * - reçus : en main, en bon état ou endommagés (et les colis déjà retirés) ;
     * - non reçus : dits explicitement « non reçu » : manquants tout de suite ;
     * - en attente : jamais pointés ; manquants 24 h après l'arrivée du départ.
     *
     * @param array<string, mixed> $depart
     * @return array<string, mixed>
     */
    public function enrichir(array $depart, DateTimeImmutable $maintenant): array
    {
        $envoyes = (int) ($depart['envoyes'] ?? 0);
        $recus = min((int) ($depart['recus'] ?? 0), $envoyes);
        $endommages = min((int) ($depart['endommages'] ?? 0), $recus);
        $restants = $envoyes - $recus;
        $nonRecus = min((int) ($depart['non_recus'] ?? 0), $restants);
        $enAttente = $restants - $nonRecus;

        $echeance = null;
        $delaiDepasse = false;

        if (!empty($depart['date_premiere_reception'])) {
            $echeance = (new DateTimeImmutable((string) $depart['date_premiere_reception']))
                ->modify('+' . self::DELAI_MANQUANT_HEURES . ' hours');
            $delaiDepasse = $maintenant >= $echeance;
        }

        $etat = match (true) {
            $envoyes > 0 && $restants === 0 => self::ETAT_COMPLET,
            $recus === 0 && $nonRecus === 0 => self::ETAT_EN_ROUTE,
            $nonRecus > 0 || $delaiDepasse => self::ETAT_MANQUANTS,
            default => self::ETAT_EN_COURS,
        };

        $manquants = $etat === self::ETAT_COMPLET ? 0 : $nonRecus + ($delaiDepasse ? $enAttente : 0);

        return array_merge($depart, [
            'envoyes' => $envoyes,
            'recus' => $recus,
            'recus_intacts' => $recus - $endommages,
            'endommages' => $endommages,
            'non_recus' => $nonRecus,
            'en_attente' => $enAttente,
            'restants' => $restants,
            'manquants' => $manquants,
            'taux' => self::taux($recus, $envoyes),
            'delai_depasse' => $delaiDepasse,
            'echeance' => $echeance?->format('Y-m-d H:i:s'),
            'etat' => $etat,
            'fuseau_depart' => HeureAgence::fuseau($depart['fuseau_depart'] ?? null),
            'fuseau_arrivee' => HeureAgence::fuseau($depart['fuseau_arrivee'] ?? null),
        ]);
    }

    /**
     * L'état affiché d'un colis du départ : RECU, ENDOMMAGE, NON_RECU,
     * HORS_LISTE, RETIRE, ATTENDU (jamais pointé), ou MANQUANT (jamais pointé,
     * 24 h après l'arrivée).
     *
     * @param array<string, mixed> $colis
     * @param array<string, mixed> $depart enrichi
     */
    public static function etatColis(array $colis, array $depart): string
    {
        if (in_array((string) ($colis['statut'] ?? ''), ['retire', 'livre'], true)) {
            return 'RETIRE';
        }

        $etat = Regles::etatDe($colis);

        if ($etat === Regles::RECU && (int) ($colis['reception_hors_liste'] ?? 0) === 1) {
            return 'HORS_LISTE';
        }

        if ($etat === Regles::EN_ATTENTE) {
            return !empty($depart['delai_depasse']) ? 'MANQUANT' : 'ATTENDU';
        }

        return $etat;
    }

    /**
     * @param array<string, mixed> $depart enrichi
     * @return array<int, array<string, mixed>>
     */
    private function colisAvecEtat(array $depart): array
    {
        $colis = [];

        foreach ($this->repo->colisDuDepart((int) $depart['id']) as $c) {
            $c['etat'] = self::etatColis($c, $depart);
            // Le dernier qui a pointé ; pour un colis pointé avant les trois
            // choix, celui qui l'a reçu.
            $c['pointe_le'] = $c['pointe_le'] ?? $c['date_reception'] ?? null;
            $c['pointe_par'] = $c['pointe_par'] ?? $c['recu_par'] ?? null;
            $colis[] = $c;
        }

        return $colis;
    }

    /** @return array{id:int, etat:string, libelle:string, commentaire:?string} */
    private function etatRendu(int $colisId, string $etat, mixed $commentaire): array
    {
        return ['id' => $colisId, 'etat' => $etat, 'libelle' => Regles::LIBELLES[$etat] ?? $etat, 'commentaire' => Regles::commentaire($commentaire)];
    }

    /** @return array<string, mixed>|null */
    private function departEnrichi(int $expeditionId): ?array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        return $depart === null ? null : $this->enrichir($depart, $this->maintenant());
    }

    /** @return array<string, mixed>|null */
    private function actualiser(int $expeditionId): ?array
    {
        $depart = $this->repo->departAvecCompteurs($expeditionId);

        if ($depart === null) {
            return null;
        }

        $this->repo->actualiserDepart($expeditionId, (int) $depart['envoyes'], (int) $depart['recus'], (int) $depart['recus'] + (int) $depart['non_recus']);

        return $this->enrichir($this->repo->departAvecCompteurs($expeditionId) ?? $depart, $this->maintenant());
    }

    /** @param array<string, mixed> $filtres */
    private function nomAgenceDuRapport(array $filtres, ?int $perimetre): string
    {
        foreach ([$perimetre, $filtres['agence_arrivee'] ?? null, $filtres['agence_envoi'] ?? null] as $id) {
            if ($id !== null && ($agence = $this->repo->agence((int) $id)) !== null) {
                return $agence['name'];
            }
        }

        return '';
    }

    /** Sans iconv : sa translittération dépend du système (des « ? » sous Windows). */
    private static function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'À' => 'A', 'Â' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E',
            'Î' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Œ' => 'OE',
        ]);
    }

    public function maintenant(): DateTimeImmutable
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
