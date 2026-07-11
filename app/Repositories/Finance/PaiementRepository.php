<?php

namespace App\Repositories\Finance;

use App\Models\Finance\Paiement;
use App\Models\Finance\Recu;
use App\Models\Finance\PaiementCallback;
use PDO;

class PaiementRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?Paiement
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_paiements WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapToPaiement($row) : null;
    }

    public function findByFactureId(int $factureId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_paiements WHERE facture_id = :facture_id ORDER BY date_paiement ASC");
        $stmt->execute(['facture_id' => $factureId]);
        return array_map(fn($row) => $this->mapToPaiement($row), $stmt->fetchAll() ?: []);
    }

    public function create(Paiement $paiement): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_paiements (facture_id, caissiere_id, montant, devise, mode, type, date_paiement)
            VALUES (:facture_id, :caissiere_id, :montant, :devise, :mode, :type, NOW())
        ");
        $stmt->execute([
            'facture_id' => $paiement->factureId,
            'caissiere_id' => $paiement->caissiereId,
            'montant' => $paiement->montant,
            'devise' => $paiement->devise,
            'mode' => $paiement->mode,
            'type' => $paiement->type,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // -----------------------------------------------------
    // RECUS MANAGEMENT
    // -----------------------------------------------------

    public function createRecu(Recu $recu): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_recus (paiement_id, numero_recu, pdf_url, date_emission)
            VALUES (:paiement_id, :numero_recu, :pdf_url, NOW())
        ");
        $stmt->execute([
            'paiement_id' => $recu->paiementId,
            'numero_recu' => $recu->numeroRecu,
            'pdf_url' => $recu->pdfUrl,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findRecuByPaiementId(int $paiementId): ?Recu
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_recus WHERE paiement_id = :paiement_id LIMIT 1");
        $stmt->execute(['paiement_id' => $paiementId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return new Recu(
            id: (int) $row['id'],
            paiementId: (int) $row['paiement_id'],
            numeroRecu: (string) $row['numero_recu'],
            pdfUrl: $row['pdf_url'],
            dateEmission: $row['date_emission']
        );
    }

    public function generateNextRecuNumber(int $agenceId): string
    {
        // Format: RE-AGENCEID-YEAR-COUNT
        $year = date('Y');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM lbp_recus r
            JOIN lbp_paiements p ON r.paiement_id = p.id
            JOIN lbp_factures f ON p.facture_id = f.id
            WHERE f.agence_id = :agence_id AND YEAR(r.date_emission) = :year
        ");
        $stmt->execute(['agence_id' => $agenceId, 'year' => $year]);
        $count = (int) $stmt->fetchColumn() + 1;
        return sprintf("RE-%02d-%s-%06d", $agenceId, $year, $count);
    }

    // -----------------------------------------------------
    // WEBHOOK CALLBACKS
    // -----------------------------------------------------

    public function findCallbacksByFactureId(int $factureId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_paiement_callbacks WHERE facture_id = :facture_id ORDER BY created_at DESC");
        $stmt->execute(['facture_id' => $factureId]);
        return array_map(fn($row) => $this->mapToCallback($row), $stmt->fetchAll() ?: []);
    }

    public function findCallbackByReference(string $ref): ?PaiementCallback
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_paiement_callbacks WHERE transaction_reference = :ref LIMIT 1");
        $stmt->execute(['ref' => $ref]);
        $row = $stmt->fetch();
        return $row ? $this->mapToCallback($row) : null;
    }

    public function createCallback(PaiementCallback $callback): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_paiement_callbacks (facture_id, paiement_id, provider, transaction_reference, montant, devise, statut, raw_payload, created_at)
            VALUES (:facture_id, :paiement_id, :provider, :transaction_reference, :montant, :devise, :statut, :raw_payload, NOW())
        ");
        $stmt->execute([
            'facture_id' => $callback->factureId,
            'paiement_id' => $callback->paiementId,
            'provider' => $callback->provider,
            'transaction_reference' => $callback->transactionReference,
            'montant' => $callback->montant,
            'devise' => $callback->devise,
            'statut' => $callback->statut,
            'raw_payload' => $callback->rawPayload,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCallback(PaiementCallback $callback): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_paiement_callbacks SET
                paiement_id = :paiement_id,
                statut = :statut
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $callback->id,
            'paiement_id' => $callback->paiementId,
            'statut' => $callback->statut,
        ]);
    }

    private function mapToPaiement(array $row): Paiement
    {
        return new Paiement(
            id: (int) $row['id'],
            factureId: (int) $row['facture_id'],
            caissiereId: isset($row['caissiere_id']) ? (int) $row['caissiere_id'] : null,
            montant: (float) $row['montant'],
            devise: (string) $row['devise'],
            mode: (string) $row['mode'],
            type: (string) $row['type'],
            datePaiement: $row['date_paiement']
        );
    }

    private function mapToCallback(array $row): PaiementCallback
    {
        return new PaiementCallback(
            id: (int) $row['id'],
            factureId: isset($row['facture_id']) ? (int) $row['facture_id'] : null,
            paiementId: isset($row['paiement_id']) ? (int) $row['paiement_id'] : null,
            provider: (string) $row['provider'],
            transactionReference: (string) $row['transaction_reference'],
            montant: (float) $row['montant'],
            devise: (string) $row['devise'],
            statut: (string) $row['statut'],
            rawPayload: $row['raw_payload'],
            createdAt: $row['created_at']
        );
    }
}
