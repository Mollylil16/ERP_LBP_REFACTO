<?php

namespace App\Repositories\Finance;

use App\Models\Finance\EtatJournalier;
use PDO;

class EtatJournalierRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?EtatJournalier
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_etats_journaliers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapToEtatJournalier($row) : null;
    }

    public function findByAgenceAndDate(int $agenceId, string $date): ?EtatJournalier
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_etats_journaliers 
            WHERE agence_id = :agence_id AND date_jour = :date_jour 
            LIMIT 1
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date_jour' => $date]);
        $row = $stmt->fetch();
        return $row ? $this->mapToEtatJournalier($row) : null;
    }

    public function create(EtatJournalier $etat): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_etats_journaliers (
                agence_id, chef_agence_id, date_jour, nb_colis_enregistres, nb_factures_emises,
                total_facture_xof, total_facture_eur, total_encaisse_xof, total_encaisse_eur,
                total_restant_du_xof, total_restant_du_eur, solde_caisse_agence_xof, solde_caisse_agence_eur,
                statut, date_soumission, consolide_par_id, date_consolidation, created_at
            ) VALUES (
                :agence_id, :chef_agence_id, :date_jour, :nb_colis_enregistres, :nb_factures_emises,
                :total_facture_xof, :total_facture_eur, :total_encaisse_xof, :total_encaisse_eur,
                :total_restant_du_xof, :total_restant_du_eur, :solde_caisse_agence_xof, :solde_caisse_agence_eur,
                :statut, :date_soumission, :consolide_par_id, :date_consolidation, NOW()
            )
        ");

        $stmt->execute([
            'agence_id' => $etat->agenceId,
            'chef_agence_id' => $etat->chefAgenceId,
            'date_jour' => $etat->dateJour,
            'nb_colis_enregistres' => $etat->nbColisEnregistres,
            'nb_factures_emises' => $etat->nbFacturesEmises,
            'total_facture_xof' => $etat->totalFactureXof,
            'total_facture_eur' => $etat->totalFactureEur,
            'total_encaisse_xof' => $etat->totalEncaisseXof,
            'total_encaisse_eur' => $etat->totalEncaisseEur,
            'total_restant_du_xof' => $etat->totalRestantDuXof,
            'total_restant_du_eur' => $etat->totalRestantDuEur,
            'solde_caisse_agence_xof' => $etat->soldeCaisseAgenceXof,
            'solde_caisse_agence_eur' => $etat->soldeCaisseAgenceEur,
            'statut' => $etat->statut,
            'date_soumission' => $etat->dateSoumission,
            'consolide_par_id' => $etat->consolideParId,
            'date_consolidation' => $etat->dateConsolidation,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(EtatJournalier $etat): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_etats_journaliers SET
                nb_colis_enregistres = :nb_colis_enregistres,
                nb_factures_emises = :nb_factures_emises,
                total_facture_xof = :total_facture_xof,
                total_facture_eur = :total_facture_eur,
                total_encaisse_xof = :total_encaisse_xof,
                total_encaisse_eur = :total_encaisse_eur,
                total_restant_du_xof = :total_restant_du_xof,
                total_restant_du_eur = :total_restant_du_eur,
                solde_caisse_agence_xof = :solde_caisse_agence_xof,
                solde_caisse_agence_eur = :solde_caisse_agence_eur,
                statut = :statut,
                date_soumission = :date_soumission,
                consolide_par_id = :consolide_par_id,
                date_consolidation = :date_consolidation,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $etat->id,
            'nb_colis_enregistres' => $etat->nbColisEnregistres,
            'nb_factures_emises' => $etat->nbFacturesEmises,
            'total_facture_xof' => $etat->totalFactureXof,
            'total_facture_eur' => $etat->totalFactureEur,
            'total_encaisse_xof' => $etat->totalEncaisseXof,
            'total_encaisse_eur' => $etat->totalEncaisseEur,
            'total_restant_du_xof' => $etat->totalRestantDuXof,
            'total_restant_du_eur' => $etat->totalRestantDuEur,
            'solde_caisse_agence_xof' => $etat->soldeCaisseAgenceXof,
            'solde_caisse_agence_eur' => $etat->soldeCaisseAgenceEur,
            'statut' => $etat->statut,
            'date_soumission' => $etat->dateSoumission,
            'consolide_par_id' => $etat->consolideParId,
            'date_consolidation' => $etat->dateConsolidation,
        ]);
    }

    public function getEtatsByAgence(int $agenceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_etats_journaliers 
            WHERE agence_id = :agence_id 
            ORDER BY date_jour DESC
        ");
        $stmt->execute(['agence_id' => $agenceId]);
        return array_map(fn($row) => $this->mapToEtatJournalier($row), $stmt->fetchAll() ?: []);
    }

    public function getEtatsGlobal(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM lbp_etats_journaliers ORDER BY date_jour DESC, agence_id ASC");
        return array_map(fn($row) => $this->mapToEtatJournalier($row), $stmt->fetchAll() ?: []);
    }

    /**
     * Calcule en temps réel les totaux d'une agence pour une journée donnée.
     */
    public function computeTotalsForDay(int $agenceId, string $date): array
    {
        // 1. Tonnage/nb colis créés
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_colis 
            WHERE agence_depart_id = :agence_id AND DATE(created_at) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $nbColis = (int) $stmt->fetchColumn();

        // 2. Factures émises
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as nb_factures,
                SUM(CASE WHEN devise = 'XOF' THEN montant_total ELSE 0 END) as total_xof,
                SUM(CASE WHEN devise = 'EUR' THEN montant_total ELSE 0 END) as total_eur
            FROM lbp_factures
            WHERE agence_id = :agence_id AND DATE(date_emission) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $facRow = $stmt->fetch() ?: [];
        $nbFactures = (int) ($facRow['nb_factures'] ?? 0);
        $totalFactureXof = (float) ($facRow['total_xof'] ?? 0.0);
        $totalFactureEur = (float) ($facRow['total_eur'] ?? 0.0);

        // 3. Encaissements réalisés ce jour (peu importe la date d'émission de la facture)
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) as encaisse_xof,
                SUM(CASE WHEN p.devise = 'EUR' THEN p.montant ELSE 0 END) as encaisse_eur
            FROM lbp_paiements p
            JOIN lbp_factures f ON p.facture_id = f.id
            WHERE f.agence_id = :agence_id AND DATE(p.date_paiement) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $payRow = $stmt->fetch() ?: [];
        $totalEncaisseXof = (float) ($payRow['encaisse_xof'] ?? 0.0);
        $totalEncaisseEur = (float) ($payRow['encaisse_eur'] ?? 0.0);

        // 4. Reste à payer des factures émises ce jour
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE WHEN devise = 'XOF' THEN montant_restant ELSE 0 END) as restant_xof,
                SUM(CASE WHEN devise = 'EUR' THEN montant_restant ELSE 0 END) as restant_eur
            FROM lbp_factures
            WHERE agence_id = :agence_id AND DATE(date_emission) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $restRow = $stmt->fetch() ?: [];
        $totalRestantDuXof = (float) ($restRow['restant_xof'] ?? 0.0);
        $totalRestantDuEur = (float) ($restRow['restant_eur'] ?? 0.0);

        return [
            'nb_colis' => $nbColis,
            'nb_factures' => $nbFactures,
            'total_facture_xof' => $totalFactureXof,
            'total_facture_eur' => $totalFactureEur,
            'total_encaisse_xof' => $totalEncaisseXof,
            'total_encaisse_eur' => $totalEncaisseEur,
            'total_restant_du_xof' => $totalRestantDuXof,
            'total_restant_du_eur' => $totalRestantDuEur,
            'solde_caisse_agence_xof' => $totalEncaisseXof, // Le solde physique de l'agence pour la journée est ce qui a été encaissé en espèces/etc.
            'solde_caisse_agence_eur' => $totalEncaisseEur,
        ];
    }

    private function mapToEtatJournalier(array $row): EtatJournalier
    {
        return new EtatJournalier(
            id: (int) $row['id'],
            agenceId: (int) $row['agence_id'],
            chefAgenceId: isset($row['chef_agence_id']) ? (int) $row['chef_agence_id'] : null,
            dateJour: (string) $row['date_jour'],
            nbColisEnregistres: (int) $row['nb_colis_enregistres'],
            nbFacturesEmises: (int) $row['nb_factures_emises'],
            totalFactureXof: (float) $row['total_facture_xof'],
            totalFactureEur: (float) $row['total_facture_eur'],
            totalEncaisseXof: (float) $row['total_encaisse_xof'],
            totalEncaisseEur: (float) $row['total_encaisse_eur'],
            totalRestantDuXof: (float) $row['total_restant_du_xof'],
            totalRestantDuEur: (float) $row['total_restant_du_eur'],
            soldeCaisseAgenceXof: (float) $row['solde_caisse_agence_xof'],
            soldeCaisseAgenceEur: (float) $row['solde_caisse_agence_eur'],
            statut: (string) $row['statut'],
            dateSoumission: $row['date_soumission'] ?? null,
            consolideParId: isset($row['consolide_par_id']) ? (int) $row['consolide_par_id'] : null,
            dateConsolidation: $row['date_consolidation'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null
        );
    }
}
