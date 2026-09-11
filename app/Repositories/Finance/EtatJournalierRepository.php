<?php

namespace App\Repositories\Finance;

use App\Models\Finance\EtatJournalier;
use PDO;

class EtatJournalierRepository
{
    /**
     * Expression SQL du mode de règlement d'un paiement (alias de table attendu : `p`).
     *
     * `lbp_paiements.mode` est la seule colonne alimentée lors de la création d'un paiement
     * (voir PaiementRepository::create). `mode_paiement`, ajoutée par migration avec la valeur
     * par défaut 'ESPECES', n'est écrite par aucun code : elle ne sert donc que de repli pour
     * d'éventuelles lignes où `mode` serait vide.
     */
    public const MODE_SQL = "LOWER(COALESCE(NULLIF(p.mode, ''), NULLIF(p.mode_paiement, ''), 'especes'))";

    /**
     * Sous-requête donnant la nature du contenu de chaque colis.
     *
     * La nature réelle des marchandises est portée par `lbp_marchandises.description`
     * (« VÊTEMENTS ET ACCESSOIRES », « ATTIÉKÉ »…), à raison d'une ligne par nature de
     * produit. La colonne `lbp_colis.categorie_produit` existe mais n'est alimentée par
     * aucun formulaire : elle ne sert que de repli.
     */
    private const MARCHANDISES_SUBQUERY = "
                SELECT
                    m.colis_id,
                    GROUP_CONCAT(m.description ORDER BY m.id SEPARATOR ' | ') AS natures,
                    GROUP_CONCAT(
                        CONCAT(
                            m.description,
                            CASE
                                WHEN COALESCE(m.emballage, '') <> ''
                                THEN CONCAT(' (', m.qte_emballage, ' ', m.emballage, ')')
                                ELSE ''
                            END
                        )
                        ORDER BY m.id SEPARATOR ' | '
                    ) AS natures_detail,
                    COUNT(*) AS nb_lignes_marchandise
                FROM lbp_marchandises m
                GROUP BY m.colis_id
            ";

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
                soumission_retroactive, justification_retard,
                statut, date_soumission, consolide_par_id, date_consolidation, created_at
            ) VALUES (
                :agence_id, :chef_agence_id, :date_jour, :nb_colis_enregistres, :nb_factures_emises,
                :total_facture_xof, :total_facture_eur, :total_encaisse_xof, :total_encaisse_eur,
                :total_restant_du_xof, :total_restant_du_eur, :solde_caisse_agence_xof, :solde_caisse_agence_eur,
                :solde_physique_declare, :ecart_caisse, :explication_ecart, :justificatif_url, :decompte_coupures_json, :blind_count, :validation_superviseur_id,
                :soumission_retroactive, :justification_retard,
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
            'soumission_retroactive' => $etat->soumissionRetroactive ? 1 : 0,
            'justification_retard' => $etat->justificationRetard,
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
                soumission_retroactive = :soumission_retroactive,
                justification_retard = :justification_retard,
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
            'soumission_retroactive' => $etat->soumissionRetroactive ? 1 : 0,
            'justification_retard' => $etat->justificationRetard,
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

    /**
     * Totaux d'une journée pour une agence.
     *
     * $userId restreint le calcul aux seules opérations saisies par cet utilisateur
     * (factures et colis via `created_by`, encaissements via `caissiere_id`).
     * Passer null pour obtenir le cumul de toute l'agence.
     */
    public function computeTotalsForDay(int $agenceId, string $date, ?int $userId = null): array
    {
        // Portée : cumul agence (null) ou opérations d'un seul agent.
        $scopeColis = $userId !== null ? ' AND created_by = :user_id' : '';
        $scopeFacture = $userId !== null ? ' AND f.created_by = :user_id' : '';
        $scopeFactureNoAlias = $userId !== null ? ' AND created_by = :user_id' : '';
        $scopePaiement = $userId !== null ? ' AND p.caissiere_id = :user_id' : '';
        $scopeParam = $userId !== null ? ['user_id' => $userId] : [];

        // 1. Tonnage/nb colis créés le jour même
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_colis 
            WHERE agence_depart_id = :agence_id AND DATE(created_at) = :date{$scopeColis}
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date] + $scopeParam);
        $nbColis = (int) $stmt->fetchColumn();

        // 2. Factures émises le jour même
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as nb_factures,
                SUM(CASE WHEN devise = 'XOF' THEN montant_total ELSE 0 END) as total_xof,
                SUM(CASE WHEN devise = 'EUR' THEN montant_total ELSE 0 END) as total_eur
            FROM lbp_factures f
            WHERE f.agence_id = :agence_id AND DATE(f.date_emission) = :date{$scopeFacture}
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date] + $scopeParam);
        $facRow = $stmt->fetch() ?: [];
        $nbFactures = (int) ($facRow['nb_factures'] ?? 0);
        $totalFactureXof = (float) ($facRow['total_xof'] ?? 0.0);
        $totalFactureEur = (float) ($facRow['total_eur'] ?? 0.0);

        // 3. Encaissements réalisés le jour même
        //    La ventilation s'appuie sur self::MODE_SQL : c'est `lbp_paiements.mode` qui est
        //    réellement alimenté à chaque encaissement, `mode_paiement` ne servant que de repli.
        $modeSql = self::MODE_SQL;
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN p.devise = 'XOF' THEN p.montant ELSE 0 END) as encaisse_xof,
                SUM(CASE WHEN p.devise = 'EUR' THEN p.montant ELSE 0 END) as encaisse_eur,
                SUM(CASE WHEN p.devise = 'XOF' AND {$modeSql} IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) as encaisse_especes_xof,
                SUM(CASE WHEN p.devise = 'XOF' AND {$modeSql} IN ('mobile_money', 'wave', 'orange_money', 'mtn_momo', 'momo', 'carte', 'carte_bancaire') THEN p.montant ELSE 0 END) as encaisse_digital_xof,
                SUM(CASE WHEN p.devise = 'XOF' AND {$modeSql} IN ('cheque', 'virement') THEN p.montant ELSE 0 END) as encaisse_cheque_xof,
                SUM(CASE WHEN p.devise = 'XOF' AND {$modeSql} = 'portefeuille' THEN p.montant ELSE 0 END) as encaisse_portefeuille_xof,
                SUM(CASE WHEN p.devise = 'EUR' AND {$modeSql} IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) as encaisse_especes_eur
            FROM lbp_paiements p
            JOIN lbp_factures f ON p.facture_id = f.id
            WHERE f.agence_id = :agence_id AND DATE(p.date_paiement) = :date{$scopePaiement}
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date] + $scopeParam);
        $payRow = $stmt->fetch() ?: [];
        $totalEncaisseXof = (float) ($payRow['encaisse_xof'] ?? 0.0);
        $totalEncaisseEur = (float) ($payRow['encaisse_eur'] ?? 0.0);
        $encaisseEspecesXof = (float) ($payRow['encaisse_especes_xof'] ?? 0.0);
        $encaisseDigitalXof = (float) ($payRow['encaisse_digital_xof'] ?? 0.0);
        $encaisseChequeXof = (float) ($payRow['encaisse_cheque_xof'] ?? 0.0);
        $encaissePortefeuilleXof = (float) ($payRow['encaisse_portefeuille_xof'] ?? 0.0);
        $encaisseEspecesEur = (float) ($payRow['encaisse_especes_eur'] ?? 0.0);

        // Tout mode non reconnu reste visible plutôt que de disparaître silencieusement
        // de la ventilation : les quatre canaux doivent toujours totaliser l'encaissé XOF.
        $encaisseAutreXof = round(
            $totalEncaisseXof - $encaisseEspecesXof - $encaisseDigitalXof - $encaisseChequeXof - $encaissePortefeuilleXof,
            2
        );

        // 4. Reste à payer des factures émises ce jour
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE WHEN devise = 'XOF' THEN montant_restant ELSE 0 END) as restant_xof,
                SUM(CASE WHEN devise = 'EUR' THEN montant_restant ELSE 0 END) as restant_eur
            FROM lbp_factures
            WHERE agence_id = :agence_id AND DATE(date_emission) = :date{$scopeFactureNoAlias}
        ");
        $stmt->execute(['agence_id' => $agenceId, 'date' => $date] + $scopeParam);
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
                SUM(CASE WHEN f.devise = 'EUR' THEN 0 ELSE f.montant_total END) as total_facture,
                SUM(CASE WHEN f.devise = 'EUR' THEN f.montant_total ELSE 0 END) as total_facture_eur,
                SUM(COALESCE(p_sub.total_pay, 0)) as total_encaisse
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            LEFT JOIN (
                SELECT facture_id, SUM(montant) as total_pay
                FROM lbp_paiements
                WHERE DATE(date_paiement) = :date1 AND devise = 'XOF'
                GROUP BY facture_id
            ) p_sub ON p_sub.facture_id = f.id
            WHERE f.agence_id = :agence_id AND DATE(f.date_emission) = :date2{$scopeFacture}
            GROUP BY code_type
        ");
        $stmtType->execute(['agence_id' => $agenceId, 'date1' => $date, 'date2' => $date] + $scopeParam);
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
            WHERE f.agence_id = :agence_id AND DATE(f.date_emission) = :date{$scopeFacture}
            ORDER BY f.date_emission DESC
        ");
        $stmtDetails->execute(['agence_id' => $agenceId, 'date' => $date] + $scopeParam);
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
            'encaisse_portefeuille_xof' => $encaissePortefeuilleXof,
            'encaisse_especes_eur' => $encaisseEspecesEur,
            'encaisse_autre_xof' => $encaisseAutreXof > 0.009 ? $encaisseAutreXof : 0.0,
            'total_restant_du_xof' => $totalRestantDuXof,
            'total_restant_du_eur' => $totalRestantDuEur,
            // Solde theorique de la caisse = ce qui doit se trouver dans le tiroir.
            // Seules les especes y entrent : mobile money, carte, virement, cheque et
            // portefeuille client ne passent jamais par le tiroir. Les y inclure rendait
            // l'ecart de caisse mecaniquement negatif du montant encaisse hors especes,
            // au detriment de la caissiere, et declenchait de faux signalements de fraude.
            'solde_caisse_agence_xof' => $encaisseEspecesXof,
            'solde_caisse_agence_eur' => $encaisseEspecesEur,
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
            validationSuperviseurId: isset($row['validation_superviseur_id']) && is_numeric($row['validation_superviseur_id']) ? (int) $row['validation_superviseur_id'] : null,
            soumissionRetroactive: !empty($row['soumission_retroactive']),
            justificationRetard: $row['justification_retard'] ?? null
        );
    }

    /**
     * Détail ligne par ligne des opérations facturées sur une plage de dates.
     * $agenceId = 0 => toutes les agences.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDetailedOperations(int $agenceId, string $dateDebut, string $dateFin, ?int $userId = null): array
    {
        $sql = "
            SELECT
                f.id                                AS facture_id,
                f.numero_facture,
                f.date_emission,
                DATE(f.date_emission)               AS date_jour,
                f.montant_total,
                f.montant_encaisse,
                f.montant_restant,
                f.devise,
                f.statut                            AS statut_facture,
                f.agence_id,
                ag.name                             AS agence_name,
                c.numero_tracking,
                c.nombre_colis,
                c.poids_total,
                c.volume,
                c.valeur_declaree,
                c.categorie_produit,
                mar.natures,
                mar.natures_detail,
                mar.nb_lignes_marchandise,
                c.trajet,
                c.destination_ville,
                c.destination_pays,
                exp.name                            AS expediteur_nom,
                exp.phone                           AS expediteur_tel,
                dest.name                           AS destinataire_nom,
                dest.phone                          AS destinataire_tel,
                cl.name                             AS client_paye_nom,
                COALESCE(u_cb.full_name, u_cais.full_name, 'Agent') AS caissiere_nom,
                COALESCE(pay.encaisse_periode, 0)   AS encaisse_periode,
                pay.modes_reglement
            FROM lbp_factures f
            JOIN lbp_colis c            ON f.colis_id = c.id
            JOIN lbp_clients cl         ON f.client_id = cl.id
            LEFT JOIN lbp_clients exp   ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest  ON c.destinataire_id = dest.id
            LEFT JOIN company_sites ag  ON f.agence_id = ag.id
            LEFT JOIN users u_cb        ON f.created_by = u_cb.id
            LEFT JOIN users u_cais      ON f.caissiere_id = u_cais.id
            LEFT JOIN (" . self::MARCHANDISES_SUBQUERY . ") mar ON mar.colis_id = c.id
            LEFT JOIN (
                SELECT
                    p.facture_id,
                    SUM(p.montant) AS encaisse_periode,
                    GROUP_CONCAT(DISTINCT " . self::MODE_SQL . " SEPARATOR ', ') AS modes_reglement
                FROM lbp_paiements p
                WHERE DATE(p.date_paiement) BETWEEN :pay_debut AND :pay_fin
                GROUP BY p.facture_id
            ) pay ON pay.facture_id = f.id
            WHERE DATE(f.date_emission) BETWEEN :date_debut AND :date_fin
        ";

        $params = [
            'pay_debut' => $dateDebut,
            'pay_fin' => $dateFin,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ];

        if ($agenceId > 0) {
            $sql .= " AND f.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        if ($userId !== null) {
            $sql .= " AND f.created_by = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY ag.name ASC, DATE(f.date_emission) ASC, f.date_emission ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Détail des encaissements réellement passés en caisse sur la plage,
     * y compris les règlements portant sur des factures émises antérieurement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDetailedEncaissements(int $agenceId, string $dateDebut, string $dateFin, ?int $userId = null): array
    {
        $sql = "
            SELECT
                p.id                                AS paiement_id,
                p.date_paiement,
                DATE(p.date_paiement)               AS date_jour,
                p.montant,
                p.devise,
                p.type                              AS type_paiement,
                " . self::MODE_SQL . "               AS mode_reglement,
                f.id                                AS facture_id,
                f.numero_facture,
                DATE(f.date_emission)               AS date_emission,
                f.montant_total,
                f.montant_restant,
                f.agence_id,
                ag.name                             AS agence_name,
                c.numero_tracking,
                c.nombre_colis,
                c.poids_total,
                c.categorie_produit,
                mar.natures,
                cl.name                             AS client_nom,
                cl.phone                            AS client_tel,
                COALESCE(u_pay.full_name, 'Agent')  AS caissiere_nom
            FROM lbp_paiements p
            JOIN lbp_factures f         ON p.facture_id = f.id
            JOIN lbp_colis c            ON f.colis_id = c.id
            JOIN lbp_clients cl         ON f.client_id = cl.id
            LEFT JOIN company_sites ag  ON f.agence_id = ag.id
            LEFT JOIN users u_pay       ON p.caissiere_id = u_pay.id
            LEFT JOIN (" . self::MARCHANDISES_SUBQUERY . ") mar ON mar.colis_id = c.id
            WHERE DATE(p.date_paiement) BETWEEN :date_debut AND :date_fin
        ";

        $params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

        if ($agenceId > 0) {
            $sql .= " AND f.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        if ($userId !== null) {
            $sql .= " AND p.caissiere_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY ag.name ASC, DATE(p.date_paiement) ASC, p.date_paiement ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Totaux journaliers des seules operations d'un agent, sur une plage de dates,
     * indexes par "agenceId|date".
     *
     * Sert a recalculer l'historique des points de caisse dans la portee de
     * l'utilisateur : les lignes de `lbp_etats_journaliers` portent le cumul de
     * l'agence et ne conviennent donc pas a un agent restreint a ses operations.
     *
     * Deux requetes agregees seulement, quelle que soit la longueur de l'historique.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getUserDailyTotals(int $userId, string $dateDebut, string $dateFin, int $agenceId = 0): array
    {
        $totals = [];

        $ensure = static function (array &$totals, int $agId, string $date): void {
            $key = $agId . '|' . $date;
            if (!isset($totals[$key])) {
                $totals[$key] = [
                    'agence_id' => $agId,
                    'date_jour' => $date,
                    'nb_colis' => 0,
                    'nb_factures' => 0,
                    'total_facture_xof' => 0.0,
                    'total_facture_eur' => 0.0,
                    'total_encaisse_xof' => 0.0,
                    'total_encaisse_eur' => 0.0,
                    'total_restant_du_xof' => 0.0,
                    'total_restant_du_eur' => 0.0,
                ];
            }
        };

        // Factures emises par l'agent, avec les colis rattaches
        $sqlFac = "
            SELECT
                f.agence_id,
                DATE(f.date_emission) AS date_jour,
                COUNT(*) AS nb_factures,
                SUM(COALESCE(c.nombre_colis, 0)) AS nb_colis,
                SUM(CASE WHEN f.devise = 'EUR' THEN 0 ELSE f.montant_total END) AS facture_xof,
                SUM(CASE WHEN f.devise = 'EUR' THEN f.montant_total ELSE 0 END) AS facture_eur,
                SUM(CASE WHEN f.devise = 'EUR' THEN 0 ELSE f.montant_restant END) AS restant_xof,
                SUM(CASE WHEN f.devise = 'EUR' THEN f.montant_restant ELSE 0 END) AS restant_eur
            FROM lbp_factures f
            LEFT JOIN lbp_colis c ON f.colis_id = c.id
            WHERE DATE(f.date_emission) BETWEEN :date_debut AND :date_fin
              AND f.created_by = :user_id
        ";
        $paramsFac = ['date_debut' => $dateDebut, 'date_fin' => $dateFin, 'user_id' => $userId];
        if ($agenceId > 0) {
            $sqlFac .= " AND f.agence_id = :agence_id";
            $paramsFac['agence_id'] = $agenceId;
        }
        $sqlFac .= " GROUP BY f.agence_id, date_jour";

        $stmt = $this->pdo->prepare($sqlFac);
        $stmt->execute($paramsFac);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $agId = (int) $row['agence_id'];
            $date = substr((string) $row['date_jour'], 0, 10);
            $ensure($totals, $agId, $date);
            $key = $agId . '|' . $date;
            $totals[$key]['nb_factures'] = (int) $row['nb_factures'];
            $totals[$key]['nb_colis'] = (int) $row['nb_colis'];
            $totals[$key]['total_facture_xof'] = (float) $row['facture_xof'];
            $totals[$key]['total_facture_eur'] = (float) $row['facture_eur'];
            $totals[$key]['total_restant_du_xof'] = (float) $row['restant_xof'];
            $totals[$key]['total_restant_du_eur'] = (float) $row['restant_eur'];
        }

        // Encaissements passes en caisse par l'agent
        $sqlPay = "
            SELECT
                f.agence_id,
                DATE(p.date_paiement) AS date_jour,
                SUM(CASE WHEN p.devise = 'EUR' THEN 0 ELSE p.montant END) AS encaisse_xof,
                SUM(CASE WHEN p.devise = 'EUR' THEN p.montant ELSE 0 END) AS encaisse_eur
            FROM lbp_paiements p
            JOIN lbp_factures f ON p.facture_id = f.id
            WHERE DATE(p.date_paiement) BETWEEN :date_debut AND :date_fin
              AND p.caissiere_id = :user_id
        ";
        $paramsPay = ['date_debut' => $dateDebut, 'date_fin' => $dateFin, 'user_id' => $userId];
        if ($agenceId > 0) {
            $sqlPay .= " AND f.agence_id = :agence_id";
            $paramsPay['agence_id'] = $agenceId;
        }
        $sqlPay .= " GROUP BY f.agence_id, date_jour";

        $stmt = $this->pdo->prepare($sqlPay);
        $stmt->execute($paramsPay);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $agId = (int) $row['agence_id'];
            $date = substr((string) $row['date_jour'], 0, 10);
            $ensure($totals, $agId, $date);
            $key = $agId . '|' . $date;
            $totals[$key]['total_encaisse_xof'] = (float) $row['encaisse_xof'];
            $totals[$key]['total_encaisse_eur'] = (float) $row['encaisse_eur'];
        }

        return $totals;
    }

    /**
     * Répartition des expéditions par nature de marchandise sur une plage de dates.
     * Alimente la synthèse de période du rapport détaillé des points de caisse.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNatureBreakdown(int $agenceId, string $dateDebut, string $dateFin, ?int $userId = null): array
    {
        $sql = "
            SELECT
                UPPER(TRIM(m.description))      AS nature,
                COUNT(DISTINCT c.id)            AS nb_colis_distincts,
                SUM(m.nbre_colis)               AS nb_colis,
                SUM(m.poids_unitaire * m.nbre_colis) AS poids,
                SUM(m.total_ligne)              AS montant
            FROM lbp_marchandises m
            JOIN lbp_colis c    ON m.colis_id = c.id
            JOIN lbp_factures f ON f.colis_id = c.id
            WHERE DATE(f.date_emission) BETWEEN :date_debut AND :date_fin
              AND TRIM(COALESCE(m.description, '')) <> ''
        ";

        $params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

        if ($agenceId > 0) {
            $sql .= " AND f.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        if ($userId !== null) {
            $sql .= " AND f.created_by = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " GROUP BY nature ORDER BY montant DESC, nb_colis DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * États journaliers soumis/consolidés sur une plage, indexés par "agenceId|date".
     * Sert à rapprocher le détail des opérations avec le comptage physique déclaré.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEtatsForRangeIndexed(int $agenceId, string $dateDebut, string $dateFin): array
    {
        $sql = "
            SELECT e.*, u.full_name AS chef_nom, uc.full_name AS consolidateur_nom, cs.name AS agence_name
            FROM lbp_etats_journaliers e
            LEFT JOIN users u  ON e.chef_agence_id = u.id
            LEFT JOIN users uc ON e.consolide_par_id = uc.id
            LEFT JOIN company_sites cs ON e.agence_id = cs.id
            WHERE DATE(e.date_jour) BETWEEN :date_debut AND :date_fin
        ";

        $params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

        if ($agenceId > 0) {
            $sql .= " AND e.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $indexed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (int) $row['agence_id'] . '|' . substr((string) $row['date_jour'], 0, 10);
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * Retourne les dates des N derniers jours sans point de caisse soumis/consolidé pour une agence.
     * Utilisé pour alimenter le dropdown de soumission rétroactive.
     */
    public function getJoursNonSoumis(int $agenceId, int $nbJours = 4): array
    {
        $joursManquants = [];
        for ($i = 1; $i <= $nbJours; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $stmt = $this->pdo->prepare("
                SELECT id FROM lbp_etats_journaliers
                WHERE agence_id = :agence_id AND date_jour = :date_jour AND statut IN ('soumis', 'consolide')
                LIMIT 1
            ");
            $stmt->execute(['agence_id' => $agenceId, 'date_jour' => $date]);
            if (!$stmt->fetch()) {
                $joursManquants[] = $date;
            }
        }
        return $joursManquants;
    }
}
