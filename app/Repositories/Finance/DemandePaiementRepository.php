<?php

namespace App\Repositories\Finance;

use App\Models\Finance\DemandePaiement;
use PDO;

class DemandePaiementRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?DemandePaiement
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_demandes_paiement_prestataires WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapToDemande($row) : null;
    }

    public function create(DemandePaiement $demande): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_demandes_paiement_prestataires (
                prestataire_id, superviseur_regional_id, montant, devise, motif,
                justificatif_url, statut, date_demande
            ) VALUES (
                :prestataire_id, :superviseur_regional_id, :montant, :devise, :motif,
                :justificatif_url, :statut, NOW()
            )
        ");
        $stmt->execute([
            'prestataire_id' => $demande->prestataireId,
            'superviseur_regional_id' => $demande->superviseurRegionalId,
            'montant' => $demande->montant,
            'devise' => $demande->devise,
            'motif' => $demande->motif,
            'justificatif_url' => $demande->justificatifUrl,
            'statut' => $demande->statut,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(DemandePaiement $demande): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_demandes_paiement_prestataires SET
                statut = :statut,
                caissiere_principale_id = :caissiere_principale_id,
                date_traitement = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $demande->id,
            'statut' => $demande->statut,
            'caissiere_principale_id' => $demande->caissierePrincipaleId,
        ]);
    }

    public function getDemandesBySuperviseur(int $superviseurId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_demandes_paiement_prestataires 
            WHERE superviseur_regional_id = :sup_id 
            ORDER BY date_demande DESC
        ");
        $stmt->execute(['sup_id' => $superviseurId]);
        return array_map(fn($row) => $this->mapToDemande($row), $stmt->fetchAll() ?: []);
    }

    public function getDemandesPending(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM lbp_demandes_paiement_prestataires 
            WHERE statut = 'en_attente' 
            ORDER BY date_demande ASC
        ");
        return array_map(fn($row) => $this->mapToDemande($row), $stmt->fetchAll() ?: []);
    }

    public function getDemandesGlobal(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM lbp_demandes_paiement_prestataires 
            ORDER BY date_demande DESC
        ");
        return array_map(fn($row) => $this->mapToDemande($row), $stmt->fetchAll() ?: []);
    }

    // -----------------------------------------------------
    // PRESTATAIRES
    // -----------------------------------------------------

    public function createPrestataire(string $nom, string $type, ?string $contact, ?int $zoneId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_prestataires (nom, type_prestation, contact, zone_regionale_id)
            VALUES (:nom, :type, :contact, :zone_id)
        ");
        $stmt->execute([
            'nom' => $nom,
            'type' => $type,
            'contact' => $contact,
            'zone_id' => $zoneId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getPrestataires(?int $zoneId = null): array
    {
        if ($zoneId !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM lbp_prestataires WHERE zone_regionale_id = :zone_id OR zone_regionale_id IS NULL ORDER BY nom ASC");
            $stmt->execute(['zone_id' => $zoneId]);
            return $stmt->fetchAll() ?: [];
        }
        return $this->pdo->query("SELECT * FROM lbp_prestataires ORDER BY nom ASC")->fetchAll() ?: [];
    }

    private function mapToDemande(array $row): DemandePaiement
    {
        return new DemandePaiement(
            id: (int) $row['id'],
            prestataireId: (int) $row['prestataire_id'],
            superviseurRegionalId: (int) $row['superviseur_regional_id'],
            montant: (float) $row['montant'],
            devise: (string) $row['devise'],
            motif: (string) $row['motif'],
            justificatifUrl: $row['justificatif_url'] ?? null,
            statut: (string) $row['statut'],
            caissierePrincipaleId: isset($row['caissiere_principale_id']) ? (int) $row['caissiere_principale_id'] : null,
            dateDemande: $row['date_demande'],
            dateTraitement: $row['date_traitement'] ?? null,
            updatedAt: $row['updated_at'] ?? null
        );
    }
}
