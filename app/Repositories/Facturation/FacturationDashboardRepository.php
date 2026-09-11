<?php

declare(strict_types=1);

namespace App\Repositories\Facturation;

use Throwable;

final class FacturationDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * Indicateurs du module Facturation.
     *
     * Les montants sont ventiles par devise : additionner des euros a des francs
     * compterait 100 EUR pour 100 XOF. L equivalent en euros est annonce a part.
     *
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('facturation');

        $emises = $this->compter("SELECT COUNT(*) FROM lbp_factures WHERE statut <> 'annulee'");
        $duJour = $this->compter("SELECT COUNT(*) FROM lbp_factures WHERE DATE(date_emission) = CURDATE()");
        $impayees = $this->compter("SELECT COUNT(*) FROM lbp_factures WHERE statut IN ('emise', 'partiellement_payee', 'en_retard')");
        $enRetard = $this->compter("SELECT COUNT(*) FROM lbp_factures WHERE statut = 'en_retard' OR (date_echeance_solde IS NOT NULL AND date_echeance_solde < NOW() AND montant_restant > 0)");

        $montants = $this->montants();

        $data['kpis'] = [
            [
                'label' => 'Factures émises',
                'value' => self::afficher($emises),
                'meta' => $duJour !== null ? $duJour . ' aujourd\'hui' : 'Toutes agences',
            ],
            [
                'label' => 'Montant facturé',
                'value' => $montants === null ? '—' : number_format($montants['facture_xof'], 0, ',', ' ') . ' XOF',
                'meta' => self::mentionEuros($montants, 'facture_eur', 'Hors factures annulées'),
            ],
            [
                'label' => 'Restant dû',
                'value' => $montants === null ? '—' : number_format($montants['restant_xof'], 0, ',', ' ') . ' XOF',
                'meta' => self::mentionEuros($montants, 'restant_eur', 'Sur factures non soldées'),
                'tone' => ($montants['restant_xof'] ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'En retard',
                'value' => self::afficher($enRetard),
                'meta' => $impayees !== null ? 'Sur ' . $impayees . ' facture(s) non soldée(s)' : 'Échéance dépassée',
                'tone' => ($enRetard ?? 0) > 0 ? 'danger' : 'success',
            ],
        ];

        return $data;
    }

    /**
     * @return array<string, float>|null
     */
    private function montants(): ?array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN devise = 'EUR' THEN 0 ELSE montant_total END), 0) AS facture_xof,
                    COALESCE(SUM(CASE WHEN devise = 'EUR' THEN montant_total ELSE 0 END), 0) AS facture_eur,
                    COALESCE(SUM(CASE WHEN devise = 'EUR' THEN 0 ELSE montant_restant END), 0) AS restant_xof,
                    COALESCE(SUM(CASE WHEN devise = 'EUR' THEN montant_restant ELSE 0 END), 0) AS restant_eur
                FROM lbp_factures
                WHERE statut <> 'annulee'
            ");

            if ($stmt === false) {
                return null;
            }

            $ligne = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            return [
                'facture_xof' => (float) ($ligne['facture_xof'] ?? 0),
                'facture_eur' => (float) ($ligne['facture_eur'] ?? 0),
                'restant_xof' => (float) ($ligne['restant_xof'] ?? 0),
                'restant_eur' => (float) ($ligne['restant_eur'] ?? 0),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function compter(string $sql): ?int
    {
        try {
            $stmt = $this->pdo->query($sql);

            return $stmt === false ? null : (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function afficher(?int $valeur): string
    {
        return $valeur === null ? '—' : (string) $valeur;
    }

    /**
     * @param array<string, float>|null $montants
     */
    private static function mentionEuros(?array $montants, string $cle, string $defaut): string
    {
        if ($montants === null || ($montants[$cle] ?? 0.0) <= 0.0) {
            return $defaut;
        }

        return $defaut . ' + ' . number_format($montants[$cle], 2, ',', ' ') . ' EUR';
    }
}
