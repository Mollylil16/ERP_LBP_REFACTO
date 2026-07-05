<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Helpers\Response;
use App\Helpers\View;
use App\Models\Database;
use App\Models\Finance\Paiement;
use App\Models\Finance\Recu;
use App\Models\Finance\PaiementCallback;
use App\Models\Finance\EcritureComptable;
use App\Repositories\Finance\FactureRepository;
use App\Repositories\Finance\PaiementRepository;
use App\Repositories\Finance\ComptabiliteRepository;
use App\Services\Shared\AuditLogService;
use PDO;

final class PaymentApiController extends BaseController
{
    private PDO $db;
    private FactureRepository $factureRepo;
    private PaiementRepository $paiementRepo;
    private ComptabiliteRepository $comptabiliteRepo;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->factureRepo = new FactureRepository($this->db);
        $this->paiementRepo = new PaiementRepository($this->db);
        $this->comptabiliteRepo = new ComptabiliteRepository($this->db);
    }

    /**
     * Affiche la page de paiement publique pour le client.
     */
    public function pay(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            (new \App\Controllers\Error\ErrorController())->show(404, 'Facture introuvable.');
            exit;
        }

        // Charger colis et client
        $stmt = $this->db->prepare("SELECT * FROM lbp_colis WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->colisId]);
        $colis = $stmt->fetch() ?: [];

        $stmt = $this->db->prepare("SELECT name, phone FROM lbp_clients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->clientId]);
        $client = $stmt->fetch() ?: [];

        // Rendre la vue de paiement publique
        require BASE_PATH . '/views/api/pay.php';
    }

    /**
     * Génère et redirige vers un QR code de paiement.
     */
    public function qrcode(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Response::json(['error' => 'Facture non trouvee'], 404);
            exit;
        }

        $paymentUrl = View::url('api/paiements/pay/' . $facture->id);
        $qrServerUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($paymentUrl);

        header('Location: ' . $qrServerUrl);
        exit;
    }

    /**
     * Webhook/Callback de l'opérateur Mobile Money.
     */
    public function callback(): void
    {
        // Lire le JSON posté
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true) ?: [];

        $factureId = (int) ($data['facture_id'] ?? 0);
        $reference = (string) ($data['transaction_reference'] ?? '');
        $montant = (float) ($data['montant'] ?? 0.0);
        $devise = (string) ($data['devise'] ?? 'XOF');
        $statutPay = (string) ($data['statut'] ?? 'failed'); // 'success' ou 'failed'
        $provider = (string) ($data['provider'] ?? 'mobile_money');

        if ($factureId <= 0 || $reference === '' || $montant <= 0) {
            Response::json(['ok' => false, 'message' => 'Parametres invalides'], 400);
            exit;
        }

        // Vérifier si la transaction a déjà été traitée pour éviter le double encaissement
        $existingCallback = $this->paiementRepo->findCallbackByReference($reference);
        if ($existingCallback && $existingCallback->statut === 'success') {
            Response::json(['ok' => true, 'message' => 'Transaction deja traitee (idempotente)']);
            exit;
        }

        $facture = $this->factureRepo->findById($factureId);
        if (!$facture) {
            Response::json(['ok' => false, 'message' => 'Facture associee introuvable'], 404);
            exit;
        }

        $this->db->beginTransaction();
        try {
            if ($statutPay === 'success') {
                // 1. Enregistrer le paiement dans le grand livre
                $paiement = new Paiement(
                    id: null,
                    factureId: $facture->id,
                    caissiereId: null, // Paiement automatique en ligne, pas de caissière physique
                    montant: $montant,
                    devise: $devise,
                    mode: 'mobile_money',
                    type: 'solde'
                );
                $paiementId = $this->paiementRepo->create($paiement);

                // 2. Générer le reçu
                $numeroRecu = $this->paiementRepo->generateNextRecuNumber($facture->agenceId);
                $recu = new Recu(
                    id: null,
                    paiementId: $paiementId,
                    numeroRecu: $numeroRecu,
                    pdfUrl: null
                );
                $this->paiementRepo->createRecu($recu);

                // 3. Mettre à jour le solde de la facture
                $oldFacture = (array) $facture;
                $facture->montantEncaisse += $montant;
                $facture->montantRestant = max(0.0, $facture->montantTotal - $facture->montantEncaisse);
                if ($facture->montantRestant <= 0.01) {
                    $facture->statut = 'payee';
                    $facture->montantRestant = 0.0;
                } else {
                    $facture->statut = 'partiellement_payee';
                }
                $this->factureRepo->update($facture);

                // 4. Enregistrer l'écriture comptable automatique
                // Débit Caisse (571100) et Crédit Clients (411100 ou 411200)
                $compteCredit = $facture->devise === 'EUR' ? '411200' : '411100';
                $ecriture = new EcritureComptable(
                    id: null,
                    dateEcriture: date('Y-m-d'),
                    journal: 'caisses',
                    compteDebit: '571100',
                    compteCredit: $compteCredit,
                    montant: $montant,
                    devise: $facture->devise,
                    tauxChange: $facture->tauxChange,
                    pieceJustificativeId: $numeroRecu,
                    libelle: "Encaissement en ligne Webhook (ID: {$reference})"
                );
                $this->comptabiliteRepo->createEcriture($ecriture);

                // 5. Enregistrer ou mettre à jour le callback
                $callback = new PaiementCallback(
                    id: null,
                    factureId: $facture->id,
                    paiementId: $paiementId,
                    provider: $provider,
                    transactionReference: $reference,
                    montant: $montant,
                    devise: $devise,
                    statut: 'success',
                    rawPayload: $rawPayload
                );
                $this->paiementRepo->createCallback($callback);

                // Audit Log
                AuditLogService::log('online_payment', 'lbp_factures', $facture->id, $oldFacture, (array) $facture);
            } else {
                // Échec du paiement
                $callback = new PaiementCallback(
                    id: null,
                    factureId: $facture->id,
                    paiementId: null,
                    provider: $provider,
                    transactionReference: $reference,
                    montant: $montant,
                    devise: $devise,
                    statut: 'failed',
                    rawPayload: $rawPayload
                );
                $this->paiementRepo->createCallback($callback);
            }

            $this->db->commit();
            Response::json(['ok' => true, 'message' => 'Webhook traite avec succes']);
        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::json(['ok' => false, 'message' => 'Erreur interne de traitement : ' . $e->getMessage()], 500);
        }
    }
}
