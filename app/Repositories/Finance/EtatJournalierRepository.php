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
                solde_physique_declare, ecart_caisse, explication_ecart, justificatif_url, decompte_coupures_json, blind_count, validation_superviseur_id,
                statut, date_soumission, consolide_par_id, date_consolidation, created_at
            ) VALUES (
                :agence_id, :chef_agence_id, :date_jour, :nb_colis_enregistres, :nb_factures_emises,
                :total_facture_xof, :total_facture_eur, :total_encaisse_xof, :total_encaisse_eur,
                :total_restant_du_xof, :total_restant_du_eur, :solde_caisse_agence_xof, :solde_caisse_agence_eur,
                :solde_physique_declare, :ecart_caisse, :explication_ecart, :justificatif_url, :decompte_coupures_json, :blind_count, :validation_superviseur_id,
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
            'solde_physique_declare' => $etat->soldePhysiqueDeclare,
            'ecart_caisse' => $etat->ecartCaisse,
            'explication_ecart' => $etat->explicationEcart,
            'justificatif_url' => $etat->justificatifUrl,
            'decompte_coupures_json' => $etat->decompteCoupuresJson,
            'blind_count' => $etat->blindCount ? 1 : 0,
            'validation_superviseur_id' => $etat->validationSuperviseurId,
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

    public function getEtatsByAgence(int $agenceId, array $filters = []): array
    {
        $sql = "SELECT * FROM lbp_etats_journaliers WHERE agence_id = :agence_id";
        $params = ['agence_id' => $agenceId];

        if (!empty($filters['date_exacte'])) {
            $sql .= " AND DATE(date_jour) = :date_exacte";
            $params['date_exacte'] = $filters['date_exacte'];
        }
        if (!empty($filters['mois'])) {
            $sql .= " AND DATE_FORMAT(date_jour, '%Y-%m') = :mois";
            $params['mois'] = $filters['mois'];
        }
        if (!empty($filters['semaine'])) {
            $sql .= " AND YEARWEEK(date_jour, 1) = YEARWEEK(:semaine, 1)";
            $params['semaine'] = $filters['semaine'];
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        $sql .= " ORDER BY date_jour DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($row) => $this->mapToEtatJournalier($row), $stmt->fetchAll() ?: []);
    }

    public function getEtatsGlobal(array $filters = []): array
    {
        $sql = "SELECT * FROM lbp_etats_journaliers WHERE 1=1";
        $params = [];

        if (!empty($filters['agence_id'])) {
            $sql .= " AND agence_id = :agence_id";
            $params['agence_id'] = (int) $filters['agence_id'];
        }
        if (!empty($filters['date_exacte'])) {
            $sql .= " AND DATE(date_jour) = :date_exacte";
            $params['date_exacte'] = $filters['date_exacte'];
        }
        if (!empty($filters['mois'])) {
            $sql .= " AND DATE_FORMAT(date_jour, '%Y-%m') = :mois";
            $params['mois'] = $filters['mois'];
        }
        if (!empty($filters['semaine'])) {
            $sql .= " AND YEARWEEK(date_jour, 1) = YEARWEEK(:semaine, 1)";
            $params['semaine'] = $filters['semaine'];
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        $sql .= " ORDER BY date_jour DESC, agence_id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($row) => $this->mapToEtatJournalier($row), $stmt->fetchAll() ?: []);
    }

    /**
     * Calcule en temps réel les totaux d'une agence pour une journée donnée.
     */
    public function getTrendDataForAgence(int $agenceId, int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(date_jour) as date_j, 
                   SUM(total_encaisse_xof) as total_encaisse,
                   SUM(total_facture_xof) as total_facture
            FROM lbp_etats_journaliers
            WHERE agence_id = :agence_id AND date_jour >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(date_jour)
            ORDER BY date_j ASC
        ");
        $stmt->execute(['agence_id' => $agenceId, 'days' => $days]);
        return $stmt->fetchAll() ?: [];
    }

    public function computeTotalsForDay(int $agenceId, string $date): array
    {
        // 1. Tonnage/nb colis créés le jour même
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_colis 
            WHERE agence_depart_id = :agence_id AND DATE(created_at) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $nbColis = (int) $stmt->fetchColumn();

        // 2. Factures émises le jour même
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

        // 3. Encaissements réalisés le jour même
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) as encaisse_xof,
                SUM(CASE WHEN p.devise = 'EUR' THEN p.montant ELSE 0 END) as encaisse_eur,
                SUM(CASE WHEN p.devise = 'XOF' AND (LOWER(COALESCE(p.mode, 'especes')) = 'especes' OR LOWER(COALESCE(p.mode, 'especes')) = '') THEN p.montant ELSE 0 END) as encaisse_especes_xof,
                SUM(CASE WHEN p.devise = 'XOF' AND LOWER(COALESCE(p.mode, '')) IN ('wave', 'orange_money', 'mtn_momo', 'mobile_money', 'virement', 'carte') THEN p.montant ELSE 0 END) as encaisse_digital_xof,
                SUM(CASE WHEN p.devise = 'XOF' AND LOWER(COALESCE(p.mode, '')) = 'cheque' THEN p.montant ELSE 0 END) as encaisse_cheque_xof
            FROM lbp_paiements p
            JOIN lbp_factures f ON p.facture_id = f.id
            WHERE f.agence_id = :agence_id AND DATE(p.date_paiement) = :date
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date]);
        $payRow = $stmt->fetch() ?: [];
        $totalEncaisseXof = (float) ($payRow['encaisse_xof'] ?? 0.0);
        $totalEncaisseEur = (float) ($payRow['encaisse_eur'] ?? 0.0);
        $encaisseEspecesXof = (float) ($payRow['encaisse_especes_xof'] ?? 0.0);
        $encaisseDigitalXof = (float) ($payRow['encaisse_digital_xof'] ?? 0.0);
        $encaisseChequeXof = (float) ($payRow['encaisse_cheque_xof'] ?? 0.0);

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

        // 5. Ventilation par type d'envoi pour la date spécifique
        $stmtType = $this->pdo->prepare("
            SELECT 
                UPPER(COALESCE(
                    NULLIF(SUBSTRING_INDEX(c.numero_tracking, '-', 2), ''),
                    NULLIF(SUBSTRING_INDEX(c.trajet, ' ', 1), ''),
                    'AUTRES'
                )) as code_type,
                COUNT(DISTINCT f.id) as nb_factures,
                SUM(f.montant_total) as total_facture,
                SUM(COALESCE(p_sub.total_pay, 0)) as total_encaisse
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            LEFT JOIN (
                SELECT facture_id, SUM(montant) as total_pay 
                FROM lbp_paiements 
                WHERE DATE(date_paiement) = :date1
                GROUP BY facture_id
            ) p_sub ON p_sub.facture_id = f.id
            WHERE f.agence_id = :agence_id AND DATE(f.date_emission) = :date2
            GROUP BY code_type
        ");
        $stmtType->execute(['agence_id' => $agenceId, 'date1' => $date, 'date2' => $date]);
        $breakdownByType = $stmtType->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch detailed invoice records for daily operations traceability
        $stmtDetails = $this->pdo->prepare("
            SELECT 
                f.id,
                f.numero_facture,
                f.montant_total,
                f.montant_encaisse,
                f.date_emission,
                c.numero_tracking,
                c.nombre_colis,
                cl.name AS client_name,
                COALESCE(u.full_name, 'Agent') AS agent_name
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            JOIN lbp_clients cl ON f.client_id = cl.id
            LEFT JOIN users u ON f.created_by = u.id
            WHERE f.agence_id = :agence_id AND DATE(f.date_emission) = :date
            ORDER BY f.date_emission DESC
        ");
        $stmtDetails->execute(['agence_id' => $agenceId, 'date' => $date]);
        $invoicesDetails = $stmtDetails->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'nb_colis' => $nbColis,
            'nb_factures' => $nbFactures,
            'total_facture_xof' => $totalFactureXof,
            'total_facture_eur' => $totalFactureEur,
            'total_encaisse_xof' => $totalEncaisseXof,
            'total_encaisse_eur' => $totalEncaisseEur,
            'encaisse_especes_xof' => $encaisseEspecesXof,
            'encaisse_digital_xof' => $encaisseDigitalXof,
            'encaisse_cheque_xof' => $encaisseChequeXof,
            'total_restant_du_xof' => $totalRestantDuXof,
            'total_restant_du_eur' => $totalRestantDuEur,
            'solde_caisse_agence_xof' => $totalEncaisseXof,
            'solde_caisse_agence_eur' => $totalEncaisseEur,
            'breakdown_by_type' => $breakdownByType,
            'invoices_details' => $invoicesDetails,
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
            updatedAt: $row['updated_at'] ?? null,
            soldePhysiqueDeclare: isset($row['solde_physique_declare']) && is_numeric($row['solde_physique_declare']) ? (float) $row['solde_physique_declare'] : null,
            ecartCaisse: (float) ($row['ecart_caisse'] ?? 0.0),
            explicationEcart: $row['explication_ecart'] ?? null,
            justificatifUrl: $row['justificatif_url'] ?? null,
            decompteCoupuresJson: $row['decompte_coupures_json'] ?? null,
            blindCount: !empty($row['blind_count']),
            validationSuperviseurId: isset($row['validation_superviseur_id']) && is_numeric($row['validation_superviseur_id']) ? (int) $row['validation_superviseur_id'] : null
        );
    }
}
