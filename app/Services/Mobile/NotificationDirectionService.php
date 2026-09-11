<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Helpers\View;
use App\Repositories\Mobile\AbonnementsPushInterface;
use App\Repositories\Mobile\PushSubscriptionRepository;
use App\Repositories\PilotageDg\PilotageDgDashboardRepository;
use App\Repositories\PilotageDg\SignalementTraitementRepository;
use PDO;
use Throwable;

/**
 * Alertes envoyées au téléphone du directeur.
 *
 * Deux moments distincts :
 *  - immédiat, déclenché par une action métier (un point de caisse soumis avec écart) ;
 *  - différé, par balayage périodique lancé depuis app/Console (signalements de fraude,
 *    décisions qui traînent, agences qui n'ont pas clôturé).
 *
 * Chaque alerte porte une clé d'événement unique par destinataire : une même situation
 * ne réveille jamais deux fois le même téléphone, même si le balayage tourne toutes
 * les heures. Les signalements déjà traités ou classés sans suite ne déclenchent rien.
 */
final class NotificationDirectionService
{
    /** Écart de caisse à partir duquel le directeur est réveillé. */
    public const SEUIL_ECART_XOF = 50000.0;

    /** Délai au-delà duquel une décision en attente devient une alerte. */
    public const HEURES_DECISION = 48;

    /** Heure à partir de laquelle une agence sans point de caisse est signalée. */
    public const HEURE_CLOTURE = 17;

    public function __construct(
        private PDO $pdo,
        private EnvoiPushInterface $push,
        private AbonnementsPushInterface $abonnements
    ) {}

    public static function creer(PDO $pdo): self
    {
        return new self($pdo, new WebPushService($pdo), new PushSubscriptionRepository($pdo));
    }

    // =================================================================
    // Déclencheurs immédiats
    // =================================================================

    /**
     * Appelé à la soumission d'un point de caisse. N'alerte que si l'écart dépasse
     * le seuil : un écart de monnaie de quelques centaines de francs n'a pas à
     * réveiller le directeur.
     */
    public function ecartDeCaisse(int $etatId, string $agence, string $date, float $ecart, ?string $explication): bool
    {
        if (abs($ecart) < self::SEUIL_ECART_XOF) {
            return false;
        }

        $signe = $ecart > 0 ? '+' : '';
        $corps = $agence . ' — ' . $signe . number_format($ecart, 0, ',', ' ') . ' XOF le ' . self::jour($date) . '.'
            . ' ' . ($explication !== null && $explication !== '' ? 'Motif : ' . $explication : 'Aucune justification fournie.');

        return $this->diffuser(
            'ecart-caisse-' . $etatId,
            'Écart de caisse important',
            $corps,
            'mobile/anomalies',
            true
        );
    }

    // =================================================================
    // Balayage périodique
    // =================================================================

    /**
     * Parcourt les trois familles d'alertes différées.
     *
     * @return array<string, int> nombre d'alertes émises par famille
     */
    public function balayer(): array
    {
        return [
            'fraude' => $this->alerterSignalementsGraves(),
            'decisions' => $this->alerterDecisionsEnSouffrance(),
            'clotures' => $this->alerterAgencesNonCloturees(),
        ];
    }

    /**
     * Signalements de degré « TRÈS GRAVE » non encore traités.
     */
    private function alerterSignalementsGraves(): int
    {
        try {
            $anomalies = (new PilotageDgDashboardRepository($this->pdo))->anomalies();
            $enrichis = (new SignalementTraitementRepository($this->pdo))->enrichir($anomalies['signalements'] ?? []);
        } catch (Throwable $e) {
            return 0;
        }

        $envoyees = 0;

        foreach ($enrichis['signalements'] as $signalement) {
            if ((int) ($signalement['degre'] ?? 0) < 4 || ($signalement['clos'] ?? false)) {
                continue;
            }

            $montant = (float) ($signalement['montant'] ?? 0);
            $corps = (string) $signalement['employee'] . ' · ' . (string) $signalement['agence']
                . ($montant > 0 ? ' — ' . number_format($montant, 0, ',', ' ') . ' XOF' : '');

            if ($this->diffuser(
                'signalement-' . (string) $signalement['id'],
                (string) $signalement['type'],
                $corps,
                'mobile/anomalies',
                true
            )) {
                $envoyees++;
            }
        }

        return $envoyees;
    }

    /**
     * Décisions en attente depuis plus de 48 heures.
     */
    private function alerterDecisionsEnSouffrance(): int
    {
        $enSouffrance = 0;
        $plusAncienne = null;

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS nb, MIN(created_at) AS plus_ancienne
                FROM rh_workflow_requests
                WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL :heures HOUR)
            ");
            $stmt->execute(['heures' => self::HEURES_DECISION]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $enSouffrance += (int) ($ligne['nb'] ?? 0);
            $plusAncienne = $ligne['plus_ancienne'] ?? null;
        } catch (Throwable $e) {
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS nb
                FROM employee_legal_requests
                WHERE status IN ('submitted', 'manager_approved', 'hr_approved')
                  AND submitted_at < DATE_SUB(NOW(), INTERVAL :heures HOUR)
            ");
            $stmt->execute(['heures' => self::HEURES_DECISION]);
            $enSouffrance += (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
        }

        if ($enSouffrance === 0) {
            return 0;
        }

        // La clé porte le jour : le directeur est relancé une fois par jour au plus,
        // tant que des décisions restent en souffrance.
        $corps = $enSouffrance . ' demande' . ($enSouffrance > 1 ? 's attendent' : ' attend')
            . ' votre décision depuis plus de ' . self::HEURES_DECISION . ' heures'
            . ($plusAncienne !== null ? ', la plus ancienne depuis le ' . self::jour((string) $plusAncienne) : '')
            . '.';

        return $this->diffuser(
            'decisions-souffrance-' . date('Y-m-d'),
            'Décisions en attente',
            $corps,
            'mobile/validations',
            false
        ) ? 1 : 0;
    }

    /**
     * Agences dont le point de caisse n'est pas soumis passé l'heure de clôture.
     */
    private function alerterAgencesNonCloturees(): int
    {
        if ((int) date('H') < self::HEURE_CLOTURE) {
            return 0;
        }

        try {
            $stmt = $this->pdo->query("
                SELECT s.name
                FROM company_sites s
                LEFT JOIN lbp_etats_journaliers e
                       ON e.agence_id = s.id
                      AND e.date_jour = CURDATE()
                      AND e.statut IN ('soumis', 'consolide')
                WHERE s.is_active = 1 AND e.id IS NULL
                ORDER BY s.name ASC
            ");
            $agences = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (Throwable $e) {
            return 0;
        }

        if ($agences === []) {
            return 0;
        }

        $nombre = count($agences);
        $liste = implode(', ', array_slice($agences, 0, 4));
        if ($nombre > 4) {
            $liste .= ' et ' . ($nombre - 4) . ' autre' . ($nombre - 4 > 1 ? 's' : '');
        }

        return $this->diffuser(
            'clotures-manquantes-' . date('Y-m-d'),
            $nombre . ' agence' . ($nombre > 1 ? 's n\'ont' : ' n\'a') . ' pas clôturé',
            'Point de caisse non soumis à ' . date('H\hi') . ' : ' . $liste . '.',
            'mobile/tableau-de-bord',
            false
        ) ? 1 : 0;
    }

    // =================================================================
    // Diffusion
    // =================================================================

    /**
     * Envoie une alerte à tous les appareils de la direction, en ignorant ceux qui
     * l'ont déjà reçue pour cet événement.
     */
    private function diffuser(string $cleEvenement, string $titre, string $corps, string $chemin, bool $urgent): bool
    {
        try {
            $abonnements = $this->abonnements->pourDirection();
        } catch (Throwable $e) {
            return false;
        }

        if ($abonnements === []) {
            return false;
        }

        $url = View::url($chemin);
        $aEnvoye = false;

        // Regrouper par destinataire : la clé d'événement est unique par utilisateur,
        // pas par appareil, pour qu'un directeur à trois téléphones ne soit pas
        // considéré comme déjà prévenu après le premier.
        $parUtilisateur = [];
        foreach ($abonnements as $abonnement) {
            $parUtilisateur[(int) $abonnement['user_id']][] = $abonnement;
        }

        foreach ($parUtilisateur as $userId => $appareils) {
            if (!$this->reserverEvenement($userId, $cleEvenement, $titre, $corps, $url)) {
                continue;
            }

            $succes = 0;
            foreach ($appareils as $abonnement) {
                $resultat = $this->push->envoyer($abonnement, [
                    'titre' => $titre,
                    'corps' => $corps,
                    'url' => $url,
                    'tag' => $cleEvenement,
                    'urgent' => $urgent,
                ]);

                if ($resultat['ok']) {
                    $this->abonnements->marquerSucces((int) $abonnement['id']);
                    $succes++;
                    continue;
                }

                if ($resultat['expire']) {
                    $this->abonnements->supprimer((int) $abonnement['id']);
                } else {
                    $this->abonnements->marquerEchec((int) $abonnement['id']);
                }
            }

            if ($succes > 0) {
                $this->marquerEnvoye($userId, $cleEvenement);
                $aEnvoye = true;
            }
        }

        return $aEnvoye;
    }

    /**
     * Pose la clé d'événement. Retourne false si elle existait déjà : l'alerte a
     * donc déjà été émise pour ce destinataire et ne doit pas repartir.
     */
    private function reserverEvenement(int $userId, string $cle, string $titre, string $corps, string $url): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_mobile_notifications (user_id, event_key, categorie, titre, corps, url, created_at)
                VALUES (:user_id, :cle, 'alerte', :titre, :corps, :url, NOW())
            ");
            $stmt->execute([
                'user_id' => $userId,
                'cle' => $cle,
                'titre' => mb_substr($titre, 0, 190),
                'corps' => $corps,
                'url' => mb_substr($url, 0, 255),
            ]);

            return true;
        } catch (Throwable $e) {
            // La contrainte d'unicité (user_id, event_key) a joué : déjà notifié.
            return false;
        }
    }

    private function marquerEnvoye(int $userId, string $cle): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE lbp_mobile_notifications
                SET envoye_at = NOW()
                WHERE user_id = :user_id AND event_key = :cle
            ");
            $stmt->execute(['user_id' => $userId, 'cle' => $cle]);
        } catch (Throwable $e) {
        }
    }

    private static function jour(string $date): string
    {
        $ts = strtotime($date);

        return $ts === false ? $date : date('d/m/Y', $ts);
    }
}
