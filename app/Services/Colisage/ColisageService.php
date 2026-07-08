<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Repositories\Colisage\ColisageRepository;

final class ColisageService
{
    public function __construct(private ColisageRepository $repository) {}

    // ==========================================
    // CLIENTS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function listClients(): array
    {
        return $this->repository->getClients();
    }

    /** @param array<string, mixed> $data */
    public function registerClient(array $data): int
    {
        return $this->repository->createClient($data);
    }

    // ==========================================
    // COLIS / PARCELS
    // ==========================================

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listParcels(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $items = $this->repository->getParcels($filters, $limit, $offset);
        $total = $this->repository->getParcelsCount($filters);
        $totalPages = (int) ceil($total / $limit);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'totalPages' => max(1, $totalPages),
        ];
    }

    /** @return array<string, mixed>|null */
    public function getParcelDetails(int $id): ?array
    {
        $colis = $this->repository->findParcelById($id);
        if ($colis !== null) {
            $colis['marchandises'] = $this->repository->getMarchandisesForParcel($id);
        }
        return $colis;
    }

    /** @param array<string, mixed> $data */
    public function registerParcel(array $data): int
    {
        // Auto-generate unique tracking number: LB-CI-MMYY-NNN
        $mmyy = date('my'); // e.g. 0726 for July 2026
        $prefix = 'LB-CI-' . $mmyy;
        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lbp_colis WHERE numero_tracking LIKE :prefix");
        $stmt->execute(['prefix' => $prefix . '%']);
        $seq = ((int) $stmt->fetchColumn()) + 1;
        $data['numero_tracking'] = $prefix . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        // Determine trafic label from type_expediteur
        $traficMap = [
            'export_aerien' => 'Groupage Aérien',
            'export_maritime' => 'Groupage Maritime',
            'import_aerien' => 'Import Aérien',
            'import_maritime' => 'Import Maritime',
        ];
        $data['trafic'] = $traficMap[$data['type_expediteur'] ?? ''] ?? 'Groupage Aérien';

        $parcelId = $this->repository->createParcel($data);

        // Save marchandises details if present
        if (!empty($data['marchandises']) && is_array($data['marchandises'])) {
            foreach ($data['marchandises'] as $m) {
                $description = '';

                if (!empty($m['product_id'])) {
                    // Get name from selected product
                    $stmt = \App\Models\Database::getConnection()->prepare("SELECT nom FROM lbp_produits WHERE id = :id");
                    $stmt->execute(['id' => (int) $m['product_id']]);
                    $description = (string) $stmt->fetchColumn() ?: 'Produit Inconnu';
                } elseif (!empty($m['custom_name'])) {
                    $customName = trim((string) $m['custom_name']);
                    // Check if it already exists
                    $existing = $this->repository->findProductByName($customName);
                    if ($existing === null) {
                        $this->repository->createProduct([
                            'nom' => $customName,
                            'prix_unitaire' => (float) ($m['custom_price'] ?? 0.0),
                            'description' => 'Créé à la volée depuis colisage',
                        ]);
                    }
                    $description = strtoupper($customName);
                }

                if ($description !== '') {
                    $this->repository->createMarchandise([
                        'colis_id' => $parcelId,
                        'description' => $description,
                        'emballage' => isset($m['emballage']) ? trim((string) $m['emballage']) : null,
                        'quantite' => (int) ($m['quantite'] ?? 1),
                        'nbre_colis' => (int) ($m['nbre_colis'] ?? 1),
                        'qte_emballage' => (int) ($m['qte_emballage'] ?? 1),
                        'poids_unitaire' => (float) ($m['poids_unitaire'] ?? 0.0),
                        'prix_kg' => (float) ($m['prix_kg'] ?? 0.0),
                    ]);
                }
            }
        }

        return $parcelId;
    }

    /** @param array<string, mixed> $data */
    public function withdrawParcel(int $id, array $data): void
    {
        $this->repository->recordWithdrawal($id, $data);
    }



    // ==========================================
    // LIVREURS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function listLivreurs(): array
    {
        return $this->repository->getLivreurs();
    }

    // ==========================================
    // INVENTAIRES
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function listInventories(): array
    {
        return $this->repository->getInventories();
    }

    /** @param array<string, mixed> $data */
    public function registerInventory(array $data): int
    {
        $inventoryId = $this->repository->createInventory($data);

        // Add scanned lines if present
        if (!empty($data['lignes']) && is_array($data['lignes'])) {
            foreach ($data['lignes'] as $ligne) {
                if (!empty($ligne['colis_id'])) {
                    $this->repository->addInventoryLine([
                        'inventaire_id' => $inventoryId,
                        'colis_id' => (int) $ligne['colis_id'],
                        'etat' => (string) ($ligne['etat'] ?? 'PRÉSENT'),
                        'commentaires' => isset($ligne['commentaires']) ? (string) $ligne['commentaires'] : null,
                    ]);
                }
            }
        }

        return $inventoryId;
    }

    // ==========================================
    // PRODUCTS CATALOG
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function listProducts(): array
    {
        return $this->repository->getProducts();
    }

    // ==========================================
    // GROUPAGE / EXPEDITIONS
    // ==========================================

    /** @return array<int, array<string, mixed>> */
    public function listExpeditions(): array
    {
        return $this->repository->getExpeditions();
    }

    /** @return array<string, mixed>|null */
    public function getExpeditionDetails(int $id): ?array
    {
        $exp = $this->repository->findExpeditionById($id);
        if ($exp !== null) {
            $exp['parcels'] = $this->repository->getParcelsForExpedition($id);
        }
        return $exp;
    }

    /** @param array<string, mixed> $data */
    public function createExpedition(array $data): int
    {
        $data['reference'] = 'EXP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        return $this->repository->createExpedition($data);
    }

    public function addParcelToExpedition(int $parcelId, int $expeditionId): void
    {
        $this->repository->assignParcelToExpedition($parcelId, $expeditionId);
    }

    public function startExpedition(int $id): void
    {
        $userId = \App\Helpers\Auth::id();
        $this->repository->updateExpeditionStatus($id, 'EN_TRANSIT');
        $this->repository->updateParcelsStatusForExpedition($id, 'EN_TRANSIT');

        $pdo = \App\Models\Database::getConnection();
        // Record status change history
        $historyStmt = $pdo->prepare("
            INSERT INTO lbp_expedition_status_history (expedition_id, statut_depart, statut_arrive, changed_by_user_id, created_at)
            VALUES (:exp_id, 'EN_PREPARATION', 'EN_TRANSIT', :user_id, NOW())
        ");
        $historyStmt->execute(['exp_id' => $id, 'user_id' => $userId]);

        $exp = $this->repository->findExpeditionById($id);
        if ($exp !== null) {
            $parcels = $this->repository->getParcelsForExpedition($id);
            foreach ($parcels as $p) {
                $stmt = $pdo->prepare("
                    INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                    VALUES (:colis_id, :etape, NOW())
                ");
                $stmt->execute([
                    'colis_id' => $p['id'],
                    'etape' => 'Départ de l\'expédition ' . $exp['reference'] . ' via transport ' . $exp['type_transport'],
                ]);
            }
        }
    }

    public function arriveExpedition(int $id): void
    {
        $userId = \App\Helpers\Auth::id();
        $this->repository->updateExpeditionStatus($id, 'ARRIVÉ');
        $this->repository->updateParcelsStatusForExpedition($id, 'ARRIVÉ');

        $pdo = \App\Models\Database::getConnection();
        // Record status change history
        $historyStmt = $pdo->prepare("
            INSERT INTO lbp_expedition_status_history (expedition_id, statut_depart, statut_arrive, changed_by_user_id, created_at)
            VALUES (:exp_id, 'EN_TRANSIT', 'ARRIVÉ', :user_id, NOW())
        ");
        $historyStmt->execute(['exp_id' => $id, 'user_id' => $userId]);

        $exp = $this->repository->findExpeditionById($id);
        if ($exp !== null) {
            $parcels = $this->repository->getParcelsForExpedition($id);
            foreach ($parcels as $p) {
                $stmt = $pdo->prepare("
                    INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                    VALUES (:colis_id, :etape, NOW())
                ");
                $stmt->execute([
                    'colis_id' => $p['id'],
                    'etape' => 'Arrivée à l\'agence de destination ' . $exp['agence_arrivee_name'],
                ]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getParcelsAvailableForGroupage(int $agencyId): array
    {
        return $this->repository->getParcelsAvailableForGroupage($agencyId);
    }
}
