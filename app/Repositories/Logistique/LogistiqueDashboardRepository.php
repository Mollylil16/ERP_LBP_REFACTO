<?php

declare(strict_types=1);

namespace App\Repositories\Logistique;

use PDO;
use Throwable;

/**
 * Le tableau de bord de la logistique, sur les chiffres du magasin.
 *
 * Il affichait quatre zéros écrits en dur — « Mouvements », « Transporteurs »,
 * « Véhicules », « Incidents » — qui n'ont jamais rien mesuré. Un écran de
 * pilotage qui annonce zéro pendant que le magasin travaille fait douter du
 * reste du logiciel.
 *
 * Ce qu'il montre désormais est ce que la logistique surveille vraiment : les
 * colis qui dorment en magasin, l'argent qui n'est pas rentré, l'emballage qui
 * va manquer, et les voyages en cours.
 */
final class LogistiqueDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /** Au-delà, un colis arrivé n'attend plus : il dort. */
    private const JOURS_SOUFFRANCE = 7;

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('logistique');

        $enMagasin = $this->compteurColis("c.statut = 'arrive'");
        $souffrance = $this->compteurColis(
            "c.statut = 'arrive' AND c.created_at < DATE_SUB(NOW(), INTERVAL " . self::JOURS_SOUFFRANCE . ' DAY)'
        );
        $impayes = $this->impayes();
        $alertes = $this->emballagesSousLeSeuil();
        $voyages = (float) $this->valeur("SELECT COUNT(*) FROM lbp_expeditions WHERE statut IN ('EN_PREPARATION', 'EN_TRANSIT')");

        $data['kpis'] = [
            [
                'label' => 'Colis en magasin',
                'value' => $this->nombre($enMagasin['colis']),
                'meta' => $this->nombre($enMagasin['poids'], 0) . ' kg en attente de retrait',
                'href' => 'logistique/colisage',
            ],
            [
                'label' => 'Colis en souffrance',
                'value' => $this->nombre($souffrance['colis']),
                'meta' => 'Arrivés depuis plus de ' . self::JOURS_SOUFFRANCE . ' jours',
                'tone' => $souffrance['colis'] > 0 ? 'warning' : 'success',
                'href' => 'entrepots/souffrance',
            ],
            [
                'label' => 'Reste à encaisser',
                'value' => $this->nombre($impayes['montant']) . ' F',
                'meta' => $this->nombre($impayes['factures']) . ' facture(s) non soldée(s)',
                'tone' => $impayes['montant'] > 0 ? 'danger' : 'success',
                'href' => 'logistique/codes-non-payes',
            ],
            [
                'label' => 'Emballages sous le seuil',
                'value' => $this->nombre((float) count($alertes)),
                'meta' => $voyages > 0 ? $this->nombre($voyages) . ' voyage(s) en cours' : 'Stock à réapprovisionner',
                'tone' => $alertes === [] ? 'success' : 'warning',
                'href' => 'logistique/emballages',
            ],
        ];

        $data['colisParAgence'] = $this->colisParAgence();
        $data['emballagesAlertes'] = $alertes;

        return $data;
    }

    // ------------------------------------------------------------------

    /**
     * Colis et poids d'un sous-ensemble du magasin.
     *
     * @return array{colis:float, poids:float}
     */
    private function compteurColis(string $condition): array
    {
        $ligne = $this->ligne("
            SELECT COALESCE(SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)), 0) AS colis,
                   COALESCE(SUM(c.poids_total), 0) AS poids
            FROM lbp_colis c
            WHERE {$condition}
        ");

        return [
            'colis' => (float) ($ligne['colis'] ?? 0),
            'poids' => (float) ($ligne['poids'] ?? 0),
        ];
    }

    /**
     * Ce que les clients doivent encore : les codes non payés.
     *
     * @return array{montant:float, factures:float}
     */
    private function impayes(): array
    {
        $ligne = $this->ligne("
            SELECT COALESCE(SUM(f.montant_restant), 0) AS montant,
                   COUNT(*) AS factures
            FROM lbp_factures f
            WHERE f.montant_restant > 0 AND f.devise = 'XOF'
        ");

        return [
            'montant' => (float) ($ligne['montant'] ?? 0),
            'factures' => (float) ($ligne['factures'] ?? 0),
        ];
    }

    /**
     * Les emballages dont le stock est passé sous le seuil d'alerte, agence par
     * agence : c'est là que la logistique doit agir avant la rupture.
     *
     * @return array<int, array<string, mixed>>
     */
    private function emballagesSousLeSeuil(): array
    {
        $lignes = $this->lignes('
            SELECT c.libelle, c.type, c.min_stock_alerte, s.quantite_disponible, cs.name AS agence
            FROM lbp_emballages_stocks s
            JOIN lbp_emballages_catalogue c ON c.id = s.emballage_id
            LEFT JOIN company_sites cs ON cs.id = s.agence_id
            WHERE s.quantite_disponible <= c.min_stock_alerte
            ORDER BY s.quantite_disponible ASC, c.libelle
            LIMIT 12
        ');

        return array_map(static fn (array $l): array => [
            'libelle' => (string) $l['libelle'],
            'type' => (string) $l['type'],
            'agence' => (string) ($l['agence'] ?? '—'),
            'disponible' => (int) $l['quantite_disponible'],
            'seuil' => (int) $l['min_stock_alerte'],
        ], $lignes);
    }

    /**
     * Le magasin, agence par agence.
     *
     * @return array<int, array<string, mixed>>
     */
    private function colisParAgence(): array
    {
        $lignes = $this->lignes("
            SELECT s.name AS agence,
                   COALESCE(SUM(CASE WHEN c.statut = 'arrive' THEN COALESCE(NULLIF(c.nombre_colis, 0), 1) ELSE 0 END), 0) AS en_magasin,
                   COALESCE(SUM(CASE WHEN c.statut = 'arrive' AND c.created_at < DATE_SUB(NOW(), INTERVAL " . self::JOURS_SOUFFRANCE . " DAY)
                                     THEN COALESCE(NULLIF(c.nombre_colis, 0), 1) ELSE 0 END), 0) AS souffrance,
                   COALESCE(SUM(CASE WHEN c.statut = 'en_transit' THEN COALESCE(NULLIF(c.nombre_colis, 0), 1) ELSE 0 END), 0) AS en_transit
            FROM lbp_colis c
            JOIN company_sites s ON s.id = c.agence_arrivee_id
            WHERE c.statut IN ('arrive', 'en_transit')
            GROUP BY s.id, s.name
            ORDER BY en_magasin DESC
        ");

        return array_map(static fn (array $l): array => [
            'agence' => (string) $l['agence'],
            'en_magasin' => (int) $l['en_magasin'],
            'souffrance' => (int) $l['souffrance'],
            'en_transit' => (int) $l['en_transit'],
        ], $lignes);
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function ligne(string $sql): array
    {
        $lignes = $this->lignes($sql);

        return $lignes[0] ?? [];
    }

    /**
     * @param array<string, mixed> $parametres
     * @return array<int, array<string, mixed>>
     */
    private function lignes(string $sql, array $parametres = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // Un tableau de bord ne tombe pas : il montre ce qu'il peut.
            return [];
        }
    }

    private function valeur(string $sql): float
    {
        try {
            return (float) $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function nombre(float $valeur, int $decimales = 0): string
    {
        return number_format($valeur, $decimales, ',', ' ');
    }
}
