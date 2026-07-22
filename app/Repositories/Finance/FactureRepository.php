<?php

namespace App\Repositories\Finance;

use App\Models\Finance\Facture;
use PDO;

class FactureRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapToFacture($row) : null;
    }

    public function findByNumero(string $numero): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE numero_facture = :num LIMIT 1");
        $stmt->execute(['num' => $numero]);
        $row = $stmt->fetch();
        return $row ? $this->mapToFacture($row) : null;
    }

    public function findByColisId(int $colisId): ?Facture
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_factures WHERE colis_id = :colis_id LIMIT 1");
        $stmt->execute(['colis_id' => $colisId]);
        $row = $stmt->fetch();
        return $row ? $this->mapToFacture($row) : null;
    }

    public function create(Facture $facture): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_factures (
                numero_facture, colis_id, client_id, caissiere_id, agence_id,
                montant_total, montant_encaisse, montant_restant, devise, taux_change,
                statut, qr_code_paiement, date_expiration_qr, date_echeance_solde, date_emission
            ) VALUES (
                :numero_facture, :colis_id, :client_id, :caissiere_id, :agence_id,
                :montant_total, :montant_encaisse, :montant_restant, :devise, :taux_change,
                :statut, :qr_code_paiement, :date_expiration_qr, :date_echeance_solde, NOW()
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
        return array_map(fn($row) => $this->mapToFacture($row), $stmt->fetchAll() ?: []);
    }

    public function getFacturesGlobal(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (($filters['agence_id'] ?? '') !== '') {
            $conditions[] = 'agence_id = :agence_id';
            $params['agence_id'] = $filters['agence_id'];
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
        return array_map(fn($row) => $this->mapToFacture($row), $stmt->fetchAll() ?: []);
    }

    public function generateNextInvoiceNumber(int $agenceId): string
    {
        // Format: FA-AGENCEID-YEAR-COUNT
        $year = date('Y');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_factures 
            WHERE agence_id = :agence_id AND YEAR(date_emission) = :year
        ");
        $stmt->execute(['agence_id' => $agenceId, 'year' => $year]);
        $count = (int) $stmt->fetchColumn() + 1;
        return sprintf("FA-%02d-%s-%06d", $agenceId, $year, $count);
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
            updatedAt: $row['updated_at'] ?? null
        );
    }
}
