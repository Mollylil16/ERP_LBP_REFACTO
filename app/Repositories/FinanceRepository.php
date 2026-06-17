<?php

namespace App\Repositories;

use PDO;
use Exception;

final class FinanceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Obtenir ou créer la caisse pour une agence.
     */
    public function getOrCreateCaisse(int $agencyId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_caisses WHERE agency_id = :agency_id");
        $stmt->execute(['agency_id' => $agencyId]);
        $caisse = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$caisse) {
            $stmtInsert = $this->pdo->prepare("INSERT INTO lbp_caisses (agency_id, balance, status) VALUES (:agency_id, 0.00, 'FERMEE')");
            $stmtInsert->execute(['agency_id' => $agencyId]);

            $stmt->execute(['agency_id' => $agencyId]);
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $caisse;
    }

    /**
     * Mouvements de caisse récents.
     */
    public function getMouvements(int $caisseId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.full_name as recorder_name
            FROM lbp_mouvements_caisse m
            LEFT JOIN users u ON m.recorded_by = u.id
            WHERE m.caisse_id = :caisse_id
            ORDER BY m.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':caisse_id', $caisseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer un mouvement et mettre à jour le solde.
     */
    public function storeMouvement(int $caisseId, string $type, float $amount, ?string $justification, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Insérer le mouvement
            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (caisse_id, type, amount, justification, recorded_by)
                VALUES (:caisse_id, :type, :amount, :justification, :recorded_by)
            ");
            $stmt->execute([
                'caisse_id' => $caisseId,
                'type' => $type,
                'amount' => $amount,
                'justification' => $justification,
                'recorded_by' => $userId
            ]);

            // Mettre à jour la caisse
            $modifier = ($type === 'ENTREE' || $type === 'APPRO') ? '+' : '-';
            $stmtUpdate = $this->pdo->prepare("
                UPDATE lbp_caisses
                SET balance = balance {$modifier} :amount, updated_at = NOW()
                WHERE id = :caisse_id
            ");
            $stmtUpdate->execute([
                'amount' => $amount,
                'caisse_id' => $caisseId
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Récupérer les factures clients (en attente ou payées) pour l'agence.
     */
    public function getClientInvoices(?int $agencyId = null): array
    {
        $sql = "
            SELECT i.*, c.name as client_name, s.name as site_name,
                   COALESCE((SELECT SUM(amount) FROM lbp_paiements WHERE invoice_id = i.id), 0.00) as paid_amount
            FROM invoices i
            LEFT JOIN crm_clients c ON i.client_id = c.id
            LEFT JOIN company_sites s ON i.site_id = s.id
        ";
        if ($agencyId !== null) {
            $sql .= " WHERE i.site_id = :site_id";
        }
        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        if ($agencyId !== null) {
            $stmt->execute(['site_id' => $agencyId]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer un paiement client.
     */
    public function storePaiement(int $invoiceId, float $amount, string $method, ?string $reference, int $userId, int $caisseId): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Récupérer la facture
            $stmtInvoice = $this->pdo->prepare("SELECT * FROM invoices WHERE id = :id");
            $stmtInvoice->execute(['id' => $invoiceId]);
            $invoice = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) {
                throw new Exception("Facture introuvable.");
            }

            // Insérer le paiement
            $stmtPay = $this->pdo->prepare("
                INSERT INTO lbp_paiements (invoice_id, amount, payment_method, reference, recorded_by)
                VALUES (:invoice_id, :amount, :payment_method, :reference, :recorded_by)
            ");
            $stmtPay->execute([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => $method,
                'reference' => $reference,
                'recorded_by' => $userId
            ]);

            // Mettre à jour le solde de la caisse
            $stmtMvt = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (caisse_id, type, amount, justification, recorded_by)
                VALUES (:caisse_id, 'ENTREE', :amount, :justification, :recorded_by)
            ");
            $stmtMvt->execute([
                'caisse_id' => $caisseId,
                'amount' => $amount,
                'justification' => "Règlement Facture #" . $invoice['reference'],
                'recorded_by' => $userId
            ]);

            $stmtUpdateCaisse = $this->pdo->prepare("
                UPDATE lbp_caisses
                SET balance = balance + :amount, updated_at = NOW()
                WHERE id = :caisse_id
            ");
            $stmtUpdateCaisse->execute([
                'amount' => $amount,
                'caisse_id' => $caisseId
            ]);

            // Calculer le total payé
            $stmtSum = $this->pdo->prepare("SELECT SUM(amount) FROM lbp_paiements WHERE invoice_id = :invoice_id");
            $stmtSum->execute(['invoice_id' => $invoiceId]);
            $totalPaid = (float) $stmtSum->fetchColumn();

            // Mettre à jour le statut de la facture
            $status = ($totalPaid >= (float) $invoice['amount_ttc']) ? 'PAYEE' : 'PARTIELLEMENT_PAYEE';
            $stmtUpdateInvoice = $this->pdo->prepare("UPDATE invoices SET status = :status WHERE id = :id");
            $stmtUpdateInvoice->execute(['status' => $status, 'id' => $invoiceId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Factures prestataires à décaisser.
     */
    public function getFacturesPrestataires(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.name as prestataire_name
            FROM lbp_factures_prestataires f
            LEFT JOIN lbp_prestataires p ON f.prestataire_id = p.id
            ORDER BY f.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer un décaissement prestataire.
     */
    public function storeDecaissementPrestataire(int $factureId, float $amount, ?string $reference, int $userId, int $caisseId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmtFacture = $this->pdo->prepare("SELECT * FROM lbp_factures_prestataires WHERE id = :id");
            $stmtFacture->execute(['id' => $factureId]);
            $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);
            if (!$facture) {
                throw new Exception("Facture prestataire introuvable.");
            }

            // Insérer dans lbp_retraits_prestataires
            $stmtRetrait = $this->pdo->prepare("
                INSERT INTO lbp_retraits_prestataires (facture_id, amount_paid, recorded_by, reference_transaction, status, approved_by, approved_at)
                VALUES (:facture_id, :amount_paid, :recorded_by, :ref, 'APPROUVE', :approved_by, NOW())
            ");
            $stmtRetrait->execute([
                'facture_id' => $factureId,
                'amount_paid' => $amount,
                'recorded_by' => $userId,
                'ref' => $reference,
                'approved_by' => $userId
            ]);

            // Mouvement de caisse
            $stmtMvt = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (caisse_id, type, amount, justification, recorded_by)
                VALUES (:caisse_id, 'DECAISSEMENT', :amount, :justification, :recorded_by)
            ");
            $stmtMvt->execute([
                'caisse_id' => $caisseId,
                'amount' => $amount,
                'justification' => "Décaissement Facture Prestataire #" . $facture['invoice_number'],
                'recorded_by' => $userId
            ]);

            $stmtUpdateCaisse = $this->pdo->prepare("
                UPDATE lbp_caisses
                SET balance = balance - :amount, updated_at = NOW()
                WHERE id = :caisse_id
            ");
            $stmtUpdateCaisse->execute([
                'amount' => $amount,
                'caisse_id' => $caisseId
            ]);

            // Mettre à jour la facture
            $stmtUpdateFacture = $this->pdo->prepare("
                UPDATE lbp_factures_prestataires
                SET status = 'PAYEE', amount_paid = amount_paid + :amount
                WHERE id = :id
            ");
            $stmtUpdateFacture->execute([
                'amount' => $amount,
                'id' => $factureId
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Crédits inter-agences.
     */
    public function getCredits(int $agencyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s1.name as from_agency_name, s2.name as to_agency_name
            FROM lbp_credits_inter_agences c
            LEFT JOIN company_sites s1 ON c.from_agency_id = s1.id
            LEFT JOIN company_sites s2 ON c.to_agency_id = s2.id
            WHERE c.from_agency_id = :agency_id_1 OR c.to_agency_id = :agency_id_2
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['agency_id_1' => $agencyId, 'agency_id_2' => $agencyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer un crédit.
     */
    public function storeCredit(int $fromAgencyId, int $toAgencyId, float $amount, ?string $reason, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_credits_inter_agences (from_agency_id, to_agency_id, amount, reason, status)
                VALUES (:from, :to, :amount, :reason, 'EN_ATTENTE')
            ");
            $stmt->execute([
                'from' => $fromAgencyId,
                'to' => $toAgencyId,
                'amount' => $amount,
                'reason' => $reason
            ]);

            // Décaissement sur l'agence émettrice
            $caisseFrom = $this->getOrCreateCaisse($fromAgencyId);
            $stmtMvt = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (caisse_id, type, amount, justification, recorded_by)
                VALUES (:caisse_id, 'DECAISSEMENT', :amount, :justification, :recorded_by)
            ");
            $stmtMvt->execute([
                'caisse_id' => $caisseFrom['id'],
                'amount' => $amount,
                'justification' => "Crédit Inter-Agence versé",
                'recorded_by' => $userId
            ]);

            $stmtUpdateFrom = $this->pdo->prepare("
                UPDATE lbp_caisses SET balance = balance - :amount WHERE id = :id
            ");
            $stmtUpdateFrom->execute(['amount' => $amount, 'id' => $caisseFrom['id']]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Apurer/Valider un crédit inter-agence (réception des fonds).
     */
    public function apurerCredit(int $creditId, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM lbp_credits_inter_agences WHERE id = :id");
            $stmt->execute(['id' => $creditId]);
            $credit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$credit || $credit['status'] === 'VALIDE') {
                throw new Exception("Crédit invalide ou déjà validé.");
            }

            // Mettre à jour statut
            $stmtUpdate = $this->pdo->prepare("
                UPDATE lbp_credits_inter_agences
                SET status = 'VALIDE', settled_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpdate->execute(['id' => $creditId]);

            // Entrée de caisse sur l'agence destinatrice
            $caisseTo = $this->getOrCreateCaisse((int) $credit['to_agency_id']);
            $stmtMvt = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (caisse_id, type, amount, justification, recorded_by)
                VALUES (:caisse_id, 'ENTREE', :amount, :justification, :recorded_by)
            ");
            $stmtMvt->execute([
                'caisse_id' => $caisseTo['id'],
                'amount' => $credit['amount'],
                'justification' => "Apurement Crédit Inter-Agence reçu",
                'recorded_by' => $userId
            ]);

            $stmtUpdateTo = $this->pdo->prepare("
                UPDATE lbp_caisses SET balance = balance + :amount WHERE id = :id
            ");
            $stmtUpdateTo->execute(['amount' => $credit['amount'], 'id' => $caisseTo['id']]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Historique des clôtures.
     */
    public function getClotures(int $caisseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.full_name as creator_name, uv.full_name as validator_name
            FROM lbp_points_caisse p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN users uv ON p.validated_by = uv.id
            WHERE p.caisse_id = :caisse_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute(['caisse_id' => $caisseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Soumettre une clôture de caisse.
     */
    public function storeCloture(int $caisseId, float $declared, float $theoretical, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_points_caisse (caisse_id, declared_balance, theoretical_balance, status, created_by)
                VALUES (:caisse_id, :declared, :theoretical, 'EN_ATTENTE', :user_id)
            ");
            $stmt->execute([
                'caisse_id' => $caisseId,
                'declared' => $declared,
                'theoretical' => $theoretical,
                'user_id' => $userId
            ]);

            // Mettre la caisse en état FERMEE
            $stmtCaisse = $this->pdo->prepare("UPDATE lbp_caisses SET status = 'FERMEE' WHERE id = :id");
            $stmtCaisse->execute(['id' => $caisseId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Valider ou rejeter une clôture de caisse.
     */
    public function validerCloture(int $clotureId, string $status, ?string $reason, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmtCloture = $this->pdo->prepare("SELECT * FROM lbp_points_caisse WHERE id = :id");
            $stmtCloture->execute(['id' => $clotureId]);
            $cloture = $stmtCloture->fetch(PDO::FETCH_ASSOC);
            if (!$cloture) {
                throw new Exception("Fiche de clôture introuvable.");
            }

            $stmt = $this->pdo->prepare("
                UPDATE lbp_points_caisse
                SET status = :status, rejection_reason = :reason, validated_by = :validated_by, validated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'reason' => $reason,
                'validated_by' => $userId,
                'id' => $clotureId
            ]);

            // Si validée, rouvrir la caisse et ajuster le solde si nécessaire (ou laisser tel quel)
            if ($status === 'VALIDE') {
                $stmtCaisse = $this->pdo->prepare("
                    UPDATE lbp_caisses
                    SET status = 'OUVERTE', balance = :balance, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtCaisse->execute([
                    'balance' => $cloture['declared_balance'],
                    'id' => $cloture['caisse_id']
                ]);
            } else {
                // Si rejetée, la caisse reste FERMEE
                $stmtCaisse = $this->pdo->prepare("UPDATE lbp_caisses SET status = 'FERMEE' WHERE id = :id");
                $stmtCaisse->execute(['id' => $cloture['caisse_id']]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Récupérer toutes les agences de company_sites.
     */
    public function getAgencies(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, code FROM company_sites WHERE is_active = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
