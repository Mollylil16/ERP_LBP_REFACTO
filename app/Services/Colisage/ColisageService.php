<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Repositories\Colisage\ColisageRepository;
use App\Repositories\Logistique\RayonRepository;
use App\Repositories\Logistique\LogistiqueSettingsRepository;
use App\Repositories\Shared\NotificationRepository;
use App\Services\Logistique\RayonService;
use App\Services\Logistique\GardiennageService;
use App\Services\Shared\NotificationService;
use App\Services\Shared\AuditLogService;
use App\Models\Database;

final class ColisageService
{
    private ?RayonService $rayonService = null;
    private ?GardiennageService $gardiennageService = null;
    private ?RayonRepository $rayonRepository = null;
    private ?NotificationService $notificationService = null;

    public function __construct(private ColisageRepository $repository)
    {
        $pdo = Database::getConnection();
        $settingsRepo = new LogistiqueSettingsRepository($pdo);
        $this->rayonRepository = new RayonRepository($pdo);
        $notificationRepo = new NotificationRepository($pdo);

        $this->rayonService = new RayonService($this->rayonRepository, $settingsRepo);
        $this->gardiennageService = new GardiennageService($settingsRepo);
        $this->notificationService = new NotificationService($notificationRepo);
    }

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

    public function deleteParcel(int $id): void
    {
        $this->repository->deleteParcel($id);
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
        // Determine tracking prefix code based on parcel type and route
        $type = $data['type_expediteur'] ?? '';
        $code = 'LB-CI'; // default fallback

        // Determine countries based on departure and arrival agencies
        $from = $this->getCountryFromAgencyId(!empty($data['agence_depart_id']) ? (int) $data['agence_depart_id'] : null);
        $to = $this->getCountryFromAgencyId(!empty($data['agence_arrivee_id']) ? (int) $data['agence_arrivee_id'] : null);

        // If trajet is explicitly passed in data
        if (!empty($data['trajet'])) {
            $parts = explode('_', $data['trajet']);
            if (count($parts) === 2) {
                $from = $parts[0] === 'CIV' ? 'CIV' : ($parts[0] === 'FR' ? 'FR' : ($parts[0] === 'SEN' ? 'SEN' : ($parts[0] === 'CAN' ? 'CAN' : $from)));
                $to = $parts[1] === 'CIV' ? 'CIV' : ($parts[1] === 'FR' ? 'FR' : ($parts[1] === 'SEN' ? 'SEN' : ($parts[1] === 'CAN' ? 'CAN' : $to)));
            }
        }

        if ($type === 'export_maritime') {
            $code = 'MP-CI';
        } elseif ($type === 'import_maritime') {
            $code = 'MP-FR';
        } elseif ($type === 'dhl') {
            $code = 'DL-CI';
        } else {
            // Air Cargo and Express (Colis Rapide) routing rules
            if ($type === 'colis_rapide_export' || $type === 'colis_rapide_import') {
                if ($from === 'FR' && $to === 'SEN') {
                    $code = 'F-SN';
                } elseif ($from === 'CIV' && $to === 'FR') {
                    $code = 'CA-CI';
                } elseif ($from === 'FR' && $to === 'CIV') {
                    $code = 'CA-FR';
                } elseif ($from === 'SEN' && $to === 'CIV') {
                    $code = 'CA-SN';
                } elseif ($from === 'CIV' && $to === 'SEN') {
                    $code = 'CA-IS';
                } elseif ($from === 'CIV' && $to === 'CAN') {
                    $code = 'CA-IC';
                } elseif ($from === 'CAN' && $to === 'CIV') {
                    $code = 'CA-CC';
                } else {
                    $code = ($from === 'FR' || $to === 'CIV') ? 'CA-FR' : 'CA-CI';
                }
            } elseif ($type === 'export_aerien' || $type === 'import_aerien') {
                if ($from === 'CIV' && $to === 'FR') {
                    $code = 'LB-CI';
                } elseif ($from === 'FR' && $to === 'CIV') {
                    $code = 'LB-FR';
                } elseif ($from === 'SEN' && $to === 'FR') {
                    $code = 'S-FR';
                } elseif ($from === 'SEN' && $to === 'CIV') {
                    $code = 'S-CI';
                } elseif ($from === 'CIV' && $to === 'CAN') {
                    $code = 'LB-CA';
                } else {
                    if ($from === 'FR' || $to === 'CIV') {
                        $code = 'LB-FR';
                    } else {
                        $code = 'LB-CI';
                    }
                }
            }
        }

        // Trajet Opération verrouillé (sous-menu à trajet fixe) : le code de trajet connu
        // prime sur toute inférence type/agence, l'agent n'ayant jamais pu le modifier.
        if (!empty($data['trajet_code_locked'])) {
            $code = (string) $data['trajet_code_locked'];
        }

        // Format court "CODE-SEQ" (ex: LB-FR-001), sans le segment mois/année de l'ancien
        // format ; la séquence repart à 001 pour chaque code sous ce nouveau format.
        $seq = $this->repository->countParcelsWithNewFormatCode($code) + 1;
        $data['numero_tracking'] = $code . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        // Determine trafic label from type_expediteur
        $traficMap = [
            'export_aerien' => 'Groupage Aérien',
            'export_maritime' => 'Groupage Maritime',
            'import_aerien' => 'Import Aérien',
            'import_maritime' => 'Import Maritime',
        ];
        // Determine insurance premium if subscribed
        if (!empty($data['assurance_souscrite'])) {
            $valeurDeclaree = (float) ($data['valeur_declaree'] ?? 0.0);
            $tauxAssurance = 2.0; // 2% default rate
            $montantAssurance = round($valeurDeclaree * ($tauxAssurance / 100.0), 2);
            $data['montant_assurance'] = $montantAssurance;
            $data['assurance_souscrite'] = 1;
            // Add insurance premium to montant_total
            $data['montant_total'] = ((float) ($data['montant_total'] ?? $valeurDeclaree)) + $montantAssurance;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $parcelId = $this->repository->createParcel($data);

            // Affectation automatique du rayon et calcul de la date limite de retrait
            $agenceArriveeId = !empty($data['agence_arrivee_id']) ? (int) $data['agence_arrivee_id'] : 1;
            if ($this->rayonService !== null) {
                $assignResult = $this->rayonService->autoAssignRayonForColis($agenceArriveeId, $data);
                $stmt = $pdo->prepare("
                    UPDATE lbp_colis
                    SET rayon_id = :rayon_id,
                        date_arrivee_agence = :date_arrivee,
                        date_limite_retrait = :date_limite
                    WHERE id = :id
                ");
                $stmt->execute([
                    'id' => $parcelId,
                    'rayon_id' => $assignResult['rayonId'],
                    'date_arrivee' => $assignResult['dateArrivee'],
                    'date_limite' => $assignResult['dateLimiteRetrait'],
                ]);

                if ($this->rayonRepository !== null && $assignResult['rayonId'] !== null) {
                    $this->rayonRepository->recordMouvement($parcelId, $assignResult['rayonId'], 'ENTREE', null, 'Affectation automatique à la réception');
                }

                if ($this->notificationService !== null) {
                    $colis = $this->getParcelDetails($parcelId);
                    if ($colis !== null) {
                        $rayonNom = null;
                        if (!empty($assignResult['rayonId']) && $this->rayonRepository !== null) {
                            $rObj = $this->rayonRepository->findRayonById($assignResult['rayonId']);
                            if ($rObj) {
                                $rayonNom = $rObj->codeRayon;
                            }
                        }
                        $this->notificationService->notifyParcelArrival($colis, $rayonNom);
                    }
                }
            }

            // Save marchandises details if present
            if (!empty($data['marchandises']) && is_array($data['marchandises'])) {
                foreach ($data['marchandises'] as $m) {
                    $prodIds = !empty($m['product_ids']) ? (array) $m['product_ids'] : (!empty($m['product_id']) ? [$m['product_id']] : []);
                    $customName = isset($m['custom_name']) ? trim((string) $m['custom_name']) : '';

                    $description = '';
                    if ($customName !== '') {
                        $description = mb_strtoupper($customName, 'UTF-8');
                        // Enregistrer dans le référentiel produit si nouveau
                        try {
                            $existing = $this->repository->findProductByName($customName);
                            if ($existing === null) {
                                $this->repository->createProduct([
                                    'nom' => $customName,
                                    'prix_unitaire' => (float) ($m['custom_price'] ?? 0.0),
                                    'description' => 'Créé à la volée depuis colisage',
                                ]);
                            }
                        } catch (\Throwable $e) {}
                    } elseif (!empty($prodIds)) {
                        $names = [];
                        foreach ($prodIds as $pid) {
                            $name = $this->repository->getProductNameById((int) $pid);
                            if ($name) {
                                $names[] = mb_strtoupper($name, 'UTF-8');
                            }
                        }
                        $description = implode(' + ', array_unique($names));
                    }

                    if ($description === '') {
                        $description = 'MARCHANDISES DIVERSES';
                    }

                    if ($description !== '') {
                        $embName = isset($m['emballage']) ? trim((string) $m['emballage']) : '';
                        $qteEmb = (int) ($m['qte_emballage'] ?? 1);

                        $this->repository->createMarchandise([
                            'colis_id' => $parcelId,
                            'description' => $description,
                            'emballage' => $embName,
                            'quantite' => (int) ($m['quantite'] ?? 1),
                            'nbre_colis' => (int) ($m['nbre_colis'] ?? 1),
                            'qte_emballage' => $qteEmb,
                            'prix_emballage' => (float) ($m['prix_emballage'] ?? 0.0),
                            'poids_unitaire' => (float) ($m['poids_unitaire'] ?? 0.0),
                            'prix_kg' => (float) ($m['prix_kg'] ?? 0.0),
                        ]);

                        if ($embName !== '' && $agenceDepartId > 0) {
                            $this->repository->deductEmballageStock(
                                $embName,
                                (int) $agenceDepartId,
                                $qteEmb,
                                $tracking,
                                $userId
                            );
                    }
                }
            }
        }

            // Update final montant_total for the colis based on the inserted marchandises
            $stmtSum = $pdo->prepare("SELECT SUM(total_ligne) FROM lbp_marchandises WHERE colis_id = ?");
            $stmtSum->execute([$parcelId]);
            $sumLines = (float) $stmtSum->fetchColumn();
            
            $finalMontant = $sumLines;
            if (!empty($data['assurance_souscrite'])) {
                $finalMontant += (float) ($data['montant_assurance'] ?? 0.0);
            }
            
            // Calculate EUR conversion if devise is XOF
            $finalMontantEur = null;
            $devise = trim((string) ($data['devise'] ?? 'XOF'));
            if ($devise === 'XOF' && $finalMontant > 0) {
                $tauxChangeEur = 655.957; // fallback
                try {
                    $rateStmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
                    if ($rateStmt) {
                        $rateRow = $rateStmt->fetch(\PDO::FETCH_ASSOC);
                        if ($rateRow && is_numeric($rateRow['setting_value'])) {
                            $tauxChangeEur = (float) $rateRow['setting_value'];
                        }
                    }
                } catch (\Exception $e) {}
                $finalMontantEur = round($finalMontant / $tauxChangeEur, 2);
            } elseif ($devise === 'EUR' && $finalMontant > 0) {
                $finalMontantEur = $finalMontant;
            }
            
            $stmtUp = $pdo->prepare("
                UPDATE lbp_colis 
                SET montant_total = ?, 
                    montant_total_eur = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->execute([$finalMontant, $finalMontantEur, $parcelId]);

            $pdo->commit();

            // Auto-génération immédiate de la facture dans le module Finance pour que la caissière la retrouve instantanément
            try {
                $factureRepo = new \App\Repositories\Finance\FactureRepository($pdo);
                $factureRepo->createAutoInvoiceFromParcel($parcelId, (int) ($data['created_by'] ?? 1));
            } catch (\Throwable $e) {
                error_log('[AUTO_INVOICE_CREATION_ERROR] ' . $e->getMessage());
            }

            // Envoi des notifications e-mail si des adresses e-mail ont été renseignées
            if ($this->notificationService !== null) {
                try {
                    $colisDetails = $this->getParcelDetails($parcelId);
                    if ($colisDetails !== null) {
                        $this->notificationService->notifyParcelCreation($colisDetails);
                    }
                } catch (\Throwable $e) {
                    error_log('[NOTIF_PARCEL_CREATION_ERROR] ' . $e->getMessage());
                }
            }

            return $parcelId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function withdrawParcel(int $id, array $data): void
    {
        $frais = 0.0;
        $colis = $this->getParcelDetails($id);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            if ($this->gardiennageService !== null && $colis !== null) {
                $gardiennage = $this->gardiennageService->calculateGardiennage($colis);
                $frais = $gardiennage['totalFraisGardiennage'];
                $data['frais_gardiennage_appliques'] = $frais;
            }

            $this->repository->recordWithdrawal($id, $data);

            if ($colis !== null) {
                $rayonId = isset($colis['rayon_id']) ? (int) $colis['rayon_id'] : null;
                if ($this->rayonRepository !== null && $rayonId !== null) {
                    $this->rayonRepository->recordMouvement($id, $rayonId, 'SORTIE', null, 'Retrait effectué au comptoir');
                }

                if ($this->notificationService !== null) {
                    $this->notificationService->notifyParcelWithdrawal($colis, $data, $frais);
                }
            }

            AuditLogService::log('withdraw_parcel', 'lbp_colis', $id, $colis, $data);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transfert un colis vers un autre rayon et consigne le mouvement DEPLACEMENT.
     */
    public function transferParcelToRayon(int $parcelId, int $newRayonId, ?string $commentaires = null): bool
    {
        $colis = $this->getParcelDetails($parcelId);
        if ($colis === null) {
            return false;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE lbp_colis SET rayon_id = :rayon_id, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['rayon_id' => $newRayonId, 'id' => $parcelId]);

            if ($this->rayonRepository !== null) {
                $note = 'Transfert inter-rayons' . ($commentaires ? ' : ' . $commentaires : '');
                $this->rayonRepository->recordMouvement($parcelId, $newRayonId, 'DEPLACEMENT', null, $note);
            }

            AuditLogService::log(
                'transfer_parcel_rayon',
                'lbp_colis',
                $parcelId,
                ['rayon_id' => $colis['rayon_id'] ?? null],
                ['rayon_id' => $newRayonId, 'commentaires' => $commentaires]
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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

    public function updateParcelStatutDepart(int $parcelId, string $statutDepart, ?string $motifReste = null): void
    {
        $this->repository->updateParcelStatutDepart($parcelId, $statutDepart, $motifReste);
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
                $etapeText = 'Départ de l\'expédition ' . $exp['reference'] . ' via transport ' . $exp['type_transport'];
                $stmt = $pdo->prepare("
                    INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                    VALUES (:colis_id, :etape, NOW())
                ");
                $stmt->execute([
                    'colis_id' => $p['id'],
                    'etape' => $etapeText,
                ]);

                if ($this->notificationService !== null) {
                    $pFull = $this->getParcelDetails((int) $p['id']);
                    if ($pFull !== null) {
                        $this->notificationService->notifyParcelStatusChange($pFull, 'EN_TRANSIT', $etapeText);
                    }
                }
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
                $etapeText = 'Arrivée à l\'agence de destination ' . ($exp['agence_arrivee_name'] ?? '');
                $stmt = $pdo->prepare("
                    INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                    VALUES (:colis_id, :etape, NOW())
                ");
                $stmt->execute([
                    'colis_id' => $p['id'],
                    'etape' => $etapeText,
                ]);

                if ($this->notificationService !== null) {
                    $pFull = $this->getParcelDetails((int) $p['id']);
                    if ($pFull !== null) {
                        $this->notificationService->notifyParcelStatusChange($pFull, 'ARRIVÉ', $etapeText);
                    }
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getParcelsAvailableForGroupage(int $agencyId): array
    {
        return $this->repository->getParcelsAvailableForGroupage($agencyId);
    }

    private function getCountryFromAgencyId(?int $id): string
    {
        if ($id === null) {
            return '';
        }
        $name = $this->repository->getAgencyNameById($id);
        if (!$name) {
            return '';
        }
        $name = mb_strtolower($name);
        if (str_contains($name, 'abidjan') || str_contains($name, 'san pedro') || str_contains($name, 'dokui') || str_contains($name, 'adjamé') || str_contains($name, 'bouët') || str_contains($name, 'siege') || str_contains($name, 'côte d\'ivoire') || str_contains($name, 'civ') || str_contains($name, 'abj')) {
            return 'CIV';
        }
        if (str_contains($name, 'france') || str_contains($name, 'paris') || str_contains($name, 'bobigny') || str_contains($name, 'fr')) {
            return 'FR';
        }
        if (str_contains($name, 'sénégal') || str_contains($name, 'senegal') || str_contains($name, 'dakar') || str_contains($name, 'sen')) {
            return 'SEN';
        }
        if (str_contains($name, 'canada') || str_contains($name, 'montreal') || str_contains($name, 'toronto') || str_contains($name, 'can')) {
            return 'CAN';
        }
        return '';
    }
}
