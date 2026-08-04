<?php

namespace App\Repositories\Finance;

use App\Models\Finance\Facture;
use App\Helpers\Auth;
use App\Security\PermissionEntityRegistry;
use App\Services\Shared\AuditLogService;
use PDO;

class FactureRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapToFacture($row) : null;
    }

    public function findByNumero(string $numero): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE numero_facture = :num LIMIT 1");
        $stmt->execute(['num' => $numero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapToFacture($row) : null;
    }

    public function findByColisId(int $colisId): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE colis_id = :colis_id LIMIT 1");
        $stmt->execute(['colis_id' => $colisId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapToFacture($row) : null;
    }

    public function create(Facture $facture): int
    {
        $currentUserId = Auth::id() ?: $facture->createdBy ?: $facture->caissiereId;

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_factures (
                numero_facture, colis_id, client_id, caissiere_id, agence_id,
                montant_total, montant_encaisse, montant_restant, devise, taux_change,
                statut, qr_code_paiement, date_expiration_qr, date_echeance_solde, date_emission,
                trajet_id, agent_id, created_by, locked, locked_at
            ) VALUES (
                :numero_facture, :colis_id, :client_id, :caissiere_id, :agence_id,
                :montant_total, :montant_encaisse, :montant_restant, :devise, :taux_change,
                :statut, :qr_code_paiement, :date_expiration_qr, :date_echeance_solde, NOW(),
                :trajet_id, :agent_id, :created_by, :locked, NOW()
            )
        ");

        $stmt->execute([
            'numero_facture' => $facture->numeroFacture,
            'colis_id' => $facture->colisId,
            'client_id' => $facture->clientId,
            'caissiere_id' => $facture->caissiereId,
            'agence_id' => $facture->agenceId,
            'montant_total' => $facture->montantTotal,
            'montant_encaisse' => $facture->montantEncaisse,
            'montant_restant' => $facture->montantRestant,
            'devise' => $facture->devise,
            'taux_change' => $facture->tauxChange,
            'statut' => $facture->statut,
            'qr_code_paiement' => $facture->qrCodePaiement,
            'date_expiration_qr' => $facture->dateExpirationQr,
            'date_echeance_solde' => $facture->dateEcheanceSolde,
            'trajet_id' => $facture->trajetId,
            'agent_id' => $facture->agentId ?: $currentUserId,
            'created_by' => $currentUserId,
            'locked' => $facture->locked ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Facture $facture): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_factures SET
                montant_encaisse = :montant_encaisse,
                montant_restant = :montant_restant,
                statut = :statut,
                qr_code_paiement = :qr_code_paiement,
                date_expiration_qr = :date_expiration_qr,
                date_echeance_solde = :date_echeance_solde,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $facture->id,
            'montant_encaisse' => $facture->montantEncaisse,
            'montant_restant' => $facture->montantRestant,
            'statut' => $facture->statut,
            'qr_code_paiement' => $facture->qrCodePaiement,
            'date_expiration_qr' => $facture->dateExpirationQr,
            'date_echeance_solde' => $facture->dateEcheanceSolde,
        ]);
    }

    /**
     * Récupère une facture avec toutes ses informations jointes (client, agence, colis, trajet, agents)
     * pour l'écran de consultation / modification.
     */
    public function getDetailedById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                f.*,
                cl.name AS client_name,
                cl.phone AS client_phone,
                s.name AS agence_name,
                s.code AS agence_code,
                c.numero_tracking,
                c.poids_total,
                c.nombre_colis,
                c.type_expediteur,
                c.trajet AS col_trajet,
                COALESCE(u.full_name, 'Agent') AS agent_name,
                cu.full_name AS created_by_name,
                t.code AS trajet_code,
                t.libelle AS trajet_libelle,
                t.type_transport AS trajet_type_transport
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            JOIN lbp_clients cl ON f.client_id = cl.id
            JOIN company_sites s ON f.agence_id = s.id
            LEFT JOIN users u ON COALESCE(f.agent_id, f.caissiere_id) = u.id
            LEFT JOIN users cu ON f.created_by = cu.id
            LEFT JOIN trajets t ON COALESCE(f.trajet_id, c.trajet_id) = t.id
            WHERE f.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Historique d'audit d'une facture (traçabilité qui/quand/ancienne valeur -> nouvelle valeur).
     */
    public function getAuditLog(int $factureId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fal.*, COALESCE(u.full_name, CONCAT('Utilisateur #', fal.modifie_par)) AS modifie_par_name
            FROM factures_audit_log fal
            LEFT JOIN users u ON fal.modifie_par = u.id
            WHERE fal.facture_id = :facture_id
            ORDER BY fal.date_modification DESC
        ");
        $stmt->execute(['facture_id' => $factureId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vérifie si un utilisateur a le droit de modifier une facture verrouillée.
     * Un agent standard créateur NE PEUT PLUS modifier la facture une fois créée.
     * Seul le rôle Responsable / Admin / Chef d'agence ou utilisateur avec la permission dédiée le peut.
     */
    public function canModify(int $factureId, ?int $userId = null): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        if (Auth::can(PermissionEntityRegistry::MODIFIER_FACTURE_APRES_CREATION)) {
            return true;
        }

        if (Auth::isFacturationPrivileged()) {
            return true;
        }

        // Sinon, si la facture est verrouillée (Locked), l'agent standard ne peut PAS modifier
        $stmt = $this->pdo->prepare("SELECT locked FROM lbp_factures WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $factureId]);
        $locked = (int) $stmt->fetchColumn();

        if ($locked === 1) {
            return false;
        }

        return true;
    }

    /**
     * Met à jour une facture avec enregistrement obligatoire dans l'audit log (traçabilité ancienne -> nouvelle valeur).
     */
    public function updateWithAudit(int $factureId, array $newFields, int $modifiedByUserId): bool
    {
        $oldRowStmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE id = :id");
        $oldRowStmt->execute(['id' => $factureId]);
        $oldRow = $oldRowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            return false;
        }

        $setClauses = [];
        $params = ['id' => $factureId];
        $auditLogs = [];

        foreach ($newFields as $field => $newValue) {
            if (array_key_exists($field, $oldRow) && (string)$oldRow[$field] !== (string)$newValue) {
                $setClauses[] = "{$field} = :{$field}";
                $params[$field] = $newValue;

                $auditLogs[] = [
                    'facture_id' => $factureId,
                    'modifie_par' => $modifiedByUserId,
                    'champ_modifie' => $field,
                    'ancienne_valeur' => (string) $oldRow[$field],
                    'nouvelle_valeur' => (string) $newValue,
                ];
            }
        }

        if (empty($setClauses)) {
            return true; // pas de modification
        }

        $setClauses[] = "updated_at = NOW()";
        $sql = "UPDATE lbp_factures SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Record in factures_audit_log (seule table lue par getAuditLog())
        $logStmt = $this->pdo->prepare("
            INSERT INTO factures_audit_log (facture_id, modifie_par, date_modification, champ_modifie, ancienne_valeur, nouvelle_valeur)
            VALUES (:facture_id, :modifie_par, NOW(), :champ_modifie, :ancienne_valeur, :nouvelle_valeur)
        ");

        foreach ($auditLogs as $log) {
            $logStmt->execute($log);
        }

        AuditLogService::log('update_invoice_locked', 'lbp_factures', $factureId, $oldRow, $newFields);

        return true;
    }

    public function getFacturesByAgence(int $agenceId, array $filters = []): array
    {
        $conditions = ['agence_id = :agence_id'];
        $params = ['agence_id' => $agenceId];

        if (($filters['statut'] ?? '') !== '') {
            $conditions[] = 'statut = :statut';
            $params['statut'] = $filters['statut'];
        }
        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(numero_facture LIKE :q OR client_id IN (SELECT id FROM lbp_clients WHERE name LIKE :q))';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_factures 
            {$where} 
            ORDER BY date_emission DESC
        ");
        $stmt->execute($params);
        return array_map(fn($row) => $this->mapToFacture($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getFacturesGlobal(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (($filters['agence_id'] ?? '') !== '' && (int)$filters['agence_id'] > 0) {
            $conditions[] = 'agence_id = :agence_id';
            $params['agence_id'] = (int)$filters['agence_id'];
        }
        if (($filters['statut'] ?? '') !== '') {
            $conditions[] = 'statut = :statut';
            $params['statut'] = $filters['statut'];
        }
        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(numero_facture LIKE :q OR client_id IN (SELECT id FROM lbp_clients WHERE name LIKE :q))';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_factures 
            {$where} 
            ORDER BY date_emission DESC
        ");
        $stmt->execute($params);
        return array_map(fn($row) => $this->mapToFacture($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function generateNextInvoiceNumber(int $agenceId): string
    {
        $year = date('Y');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_factures 
            WHERE agence_id = :agence_id AND YEAR(date_emission) = :year
        ");
        $stmt->execute(['agence_id' => $agenceId, 'year' => $year]);
        $count = (int) $stmt->fetchColumn() + 1;
        return sprintf("FA-%02d-%s-%06d", $agenceId, $year, $count);
    }

    public function createAutoInvoiceFromParcel(int $parcelId, int $caissiereId = 1): int
    {
        $existing = $this->findByColisId($parcelId);
        if ($existing) {
            return $existing->id;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM lbp_colis WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $parcelId]);
        $colis = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colis) {
            throw new \InvalidArgumentException("Colis introuvable ID: {$parcelId}");
        }

        $agenceId = !empty($colis['agence_depart_id']) ? (int) $colis['agence_depart_id'] : 1;
        $numFacture = $this->generateNextInvoiceNumber($agenceId);
        $userId = Auth::id() ?: $caissiereId;

        $facture = new Facture(
            id: null,
            numeroFacture: $numFacture,
            colisId: $parcelId,
            clientId: (int) $colis['expediteur_id'],
            caissiereId: $caissiereId,
            agenceId: $agenceId,
            montantTotal: (float) $colis['montant_total'],
            montantEncaisse: 0.0,
            montantRestant: (float) $colis['montant_total'],
            devise: (string) ($colis['devise'] ?? 'XOF'),
            tauxChange: null,
            statut: 'emise',
            qrCodePaiement: null,
            dateExpirationQr: date('Y-m-d H:i:s', strtotime('+7 days')),
            dateEcheanceSolde: date('Y-m-d H:i:s', strtotime('+7 days')),
            trajetId: !empty($colis['trajet_id']) ? (int) $colis['trajet_id'] : null,
            agentId: !empty($colis['agent_groupage_id']) ? (int) $colis['agent_groupage_id'] : $userId,
            createdBy: $userId,
            locked: true,
            lockedAt: date('Y-m-d H:i:s')
        );

        return $this->create($facture);
    }

    public function getUnpaidFacturesForRelance(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.name AS client_name, c.phone AS client_phone
            FROM lbp_factures f
            JOIN lbp_clients c ON f.client_id = c.id
            WHERE f.statut IN ('emise', 'partiellement_payee') AND f.montant_restant > 0
            ORDER BY f.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function mapToFacture(array $row): Facture
    {
        return new Facture(
            id: (int) $row['id'],
            numeroFacture: (string) $row['numero_facture'],
            colisId: (int) $row['colis_id'],
            clientId: (int) $row['client_id'],
            caissiereId: (int) $row['caissiere_id'],
            agenceId: (int) $row['agence_id'],
            montantTotal: (float) $row['montant_total'],
            montantEncaisse: (float) $row['montant_encaisse'],
            montantRestant: (float) $row['montant_restant'],
            devise: (string) $row['devise'],
            tauxChange: isset($row['taux_change']) ? (float) $row['taux_change'] : null,
            statut: (string) $row['statut'],
            qrCodePaiement: $row['qr_code_paiement'] ?? null,
            dateExpirationQr: $row['date_expiration_qr'] ?? null,
            dateEmission: $row['date_emission'] ?? null,
            dateEcheanceSolde: $row['date_echeance_solde'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            trajetId: isset($row['trajet_id']) ? (int) $row['trajet_id'] : null,
            agentId: isset($row['agent_id']) ? (int) $row['agent_id'] : null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            locked: isset($row['locked']) ? ((int)$row['locked'] === 1) : true,
            lockedAt: $row['locked_at'] ?? null
        );
    }
}
