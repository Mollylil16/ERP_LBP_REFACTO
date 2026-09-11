<?php

declare(strict_types=1);

namespace App\Repositories\PilotageDg;

use PDO;
use Throwable;

/**
 * Cycle de vie des signalements anti-fraude.
 *
 * Les signalements n'existent pas en base : ils sont recalculés à chaque affichage
 * à partir des écarts de caisse, des modifications de factures et des rapprochements.
 * Leur clé est en revanche déterministe — « EC-12 » pour l'écart de l'état 12,
 * « CS-7 » pour l'agent 7 — ce qui permet de leur attacher un traitement durable.
 *
 * Sans ce suivi, un écart expliqué et régularisé il y a six mois continue de remonter
 * en « TRÈS GRAVE » à chaque ouverture, et harcèlerait le directeur par notification.
 */
final class SignalementTraitementRepository
{
    public const STATUTS = ['vu', 'traite', 'classe'];

    public function __construct(private PDO $pdo) {}

    /**
     * Traitements existants, indexés par clé de signalement.
     *
     * @param array<int, string> $cles
     * @return array<string, array<string, mixed>>
     */
    public function pourCles(array $cles): array
    {
        $cles = array_values(array_filter(array_unique($cles), static fn($c) => is_string($c) && $c !== ''));
        if ($cles === []) {
            return [];
        }

        $marqueurs = implode(',', array_fill(0, count($cles), '?'));

        try {
            $stmt = $this->pdo->prepare("
                SELECT t.signalement_key, t.statut, t.commentaire, t.updated_at, t.created_at,
                       u.full_name AS traite_par_nom
                FROM lbp_signalements_traitement t
                LEFT JOIN users u ON t.traite_par = u.id
                WHERE t.signalement_key IN ($marqueurs)
            ");
            $stmt->execute($cles);
        } catch (Throwable $e) {
            return [];
        }

        $indexes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $indexes[(string) $ligne['signalement_key']] = $ligne;
        }

        return $indexes;
    }

    /**
     * Enregistre ou met à jour le traitement d'un signalement.
     */
    public function marquer(string $cle, string $statut, ?int $utilisateurId, ?string $commentaire = null): void
    {
        if (!in_array($statut, self::STATUTS, true)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_signalements_traitement (signalement_key, statut, commentaire, traite_par, created_at, updated_at)
            VALUES (:cle, :statut, :commentaire, :par, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                statut = VALUES(statut),
                commentaire = VALUES(commentaire),
                traite_par = VALUES(traite_par),
                updated_at = NOW()
        ");

        $stmt->execute([
            'cle' => $cle,
            'statut' => $statut,
            'commentaire' => $commentaire !== null && $commentaire !== '' ? mb_substr($commentaire, 0, 500) : null,
            'par' => $utilisateurId,
        ]);
    }

    /**
     * Remet un signalement à l'état non traité.
     */
    public function rouvrir(string $cle): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM lbp_signalements_traitement WHERE signalement_key = :cle");
        $stmt->execute(['cle' => $cle]);
    }

    /**
     * Enrichit une liste de signalements de leur traitement, et indique lesquels
     * restent à traiter.
     *
     * @param array<int, array<string, mixed>> $signalements
     * @return array{signalements: array<int, array<string, mixed>>, aTraiter: int, traites: int}
     */
    public function enrichir(array $signalements): array
    {
        $traitements = $this->pourCles(array_map(
            static fn(array $s): string => (string) ($s['id'] ?? ''),
            $signalements
        ));

        $aTraiter = 0;
        $traites = 0;

        foreach ($signalements as &$signalement) {
            $cle = (string) ($signalement['id'] ?? '');
            $traitement = $traitements[$cle] ?? null;

            $signalement['statut_traitement'] = $traitement !== null ? (string) $traitement['statut'] : 'nouveau';
            $signalement['traitement_commentaire'] = $traitement['commentaire'] ?? null;
            $signalement['traitement_par'] = $traitement['traite_par_nom'] ?? null;
            $signalement['traitement_date'] = $traitement['updated_at'] ?? null;

            // « Vu » ne clôt rien : le signalement reste à traiter tant qu'il n'est pas
            // régularisé ou explicitement classé sans suite.
            $clos = in_array($signalement['statut_traitement'], ['traite', 'classe'], true);
            $signalement['clos'] = $clos;

            if ($clos) {
                $traites++;
            } else {
                $aTraiter++;
            }
        }
        unset($signalement);

        return ['signalements' => $signalements, 'aTraiter' => $aTraiter, 'traites' => $traites];
    }
}
