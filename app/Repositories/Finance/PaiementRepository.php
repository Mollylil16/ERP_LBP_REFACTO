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
        // L'argent est compte le jour ou il entre en caisse. Une facture reglee
        // a 17h appartient au jour J : c'est ce que la caissiere a dans son tiroir
        // le soir, et c'est ce qu'elle declare a son point.
        //
        // Ce code decalait autrefois au lendemain tout reglement passe 15h. La
        // bascule de 15h ne concerne que l'enregistrement des colis pour envoi
        // (ColisageRepository::create) ; l'appliquer a l'argent faisait
        // disparaitre du jour J des sommes reellement encaissees, et creait des
        // ecarts de caisse que personne ne savait expliquer.
        $datePaiement = $paiement->datePaiement;
        if (empty($datePaiement)) {
            $datePaiement = (new \DateTime('now', new \DateTimeZone('Africa/Abidjan')))
                ->format('Y-m-d H:i:s');
        }

        /*
         * Agence ou le billet est pris. Celle de la personne qui encaisse, et
         * a defaut celle de la facture : sans ce repli, un encaissement fait
         * par un compte sans agence — direction, administrateur — ne serait
         * compte nulle part. Deux encaissements de ce genre, 15 300 FCFA,
         * flottaient ainsi en septembre 2026.
         */
        $agenceId = $paiement->agenceId;

        if (empty($agenceId)) {
            $agenceId = \App\Helpers\Auth::agenceId();
        }

        if (empty($agenceId)) {
            $stmtAgence = $this->pdo->prepare('SELECT agence_id FROM lbp_factures WHERE id = :id LIMIT 1');
            $stmtAgence->execute(['id' => $paiement->factureId]);
            $agenceId = (int) $stmtAgence->fetchColumn() ?: null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_paiements (facture_id, caissiere_id, agence_id, montant, devise, mode, type, date_paiement)
            VALUES (:facture_id, :caissiere_id, :agence_id, :montant, :devise, :mode, :type, :date_paiement)
        ");
        $stmt->execute([
            'facture_id' => $paiement->factureId,
            'caissiere_id' => $paiement->caissiereId,
            'agence_id' => $agenceId,
            'montant' => $paiement->montant,
            'devise' => $paiement->devise,
            'mode' => $paiement->mode,
            'type' => $paiement->type,
            'date_paiement' => $datePaiement,
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
        $prefix = sprintf("RE-%02d-%s-", $agenceId, $year);

        // 1. Chercher le numéro max existant directement sur la table lbp_recus
        $stmt = $this->pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(numero_recu, LENGTH(:prefix) + 1) AS UNSIGNED))
            FROM lbp_recus
            WHERE numero_recu LIKE :prefix_like
        ");
        $stmt->execute([
            'prefix' => $prefix,
            'prefix_like' => $prefix . '%',
        ]);

        $maxSeq = (int) $stmt->fetchColumn();
        $nextSeq = $maxSeq + 1;

        // 2. Boucle de vérification anti-collision
        do {
            $candidate = sprintf("RE-%02d-%s-%06d", $agenceId, $year, $nextSeq);
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM lbp_recus WHERE numero_recu = :candidate");
            $checkStmt->execute(['candidate' => $candidate]);
            $exists = (int) $checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
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
            datePaiement: $row['date_paiement'],
            agenceId: isset($row['agence_id']) ? (int) $row['agence_id'] : null
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
