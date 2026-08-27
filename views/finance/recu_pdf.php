<?php

declare(strict_types=1);

use App\Helpers\View;

/** @var \App\Models\Finance\Facture $facture */

$numFacture = $facture->numeroFacture;
$clientNom = !empty($client['name']) ? $client['name'] : 'Client LBP Transit';
$clientPhone = !empty($client['phone']) ? $client['phone'] : '—';
$trackingNum = !empty($colis['numero_tracking']) ? $colis['numero_tracking'] : '—';
$montantTotal = number_format($facture->montantTotal, 0, ',', ' ');
$montantEncaisse = number_format($facture->montantEncaisse, 0, ',', ' ');
$montantRestant = number_format($facture->montantRestant, 0, ',', ' ');
$devise = $facture->devise;
$dateFacture = date('d/m/Y H:i', strtotime($facture->createdAt ?? 'now'));
$dateEcheance = !empty($facture->dateEcheanceSolde) ? date('d/m/Y', strtotime($facture->dateEcheanceSolde)) : 'Comptant';

$statutLibelle = match($facture->statut) {
    'payee' => 'REÇU DE PAIEMENT TOTAL (PAYÉ)',
    'partiellement_payee' => 'REÇU DE PAIEMENT PARTIEL',
    default => 'FACTURE & BON D\'ENCAISSEMENT'
};

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode("RECU-LBP-{$numFacture}-{$montantEncaisse}{$devise}");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu_Paiement_<?= View::e($numFacture) ?></title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e293b; background: #fff; line-height: 1.5; font-size: 13px; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .logo-title { font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: 0.5px; }
        .logo-sub { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .recu-badge { background: #16a34a; color: #fff; padding: 6px 14px; font-size: 13px; font-weight: 800; border-radius: 4px; text-transform: uppercase; display: inline-block; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 15px; border-radius: 6px; }
        .card-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px; }
        .card-val { font-size: 13px; font-weight: 700; color: #0f172a; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #0f172a; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .table td { border-bottom: 1px solid #e2e8f0; padding: 10px; }
        .total-box { background: #f0fdf4; border: 2px solid #16a34a; padding: 12px 15px; text-align: right; border-radius: 6px; margin-bottom: 25px; }
        .total-label { font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase; }
        .total-amount { font-size: 22px; font-weight: 900; color: #16a34a; }
        .footer-signatures { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; }
        .sig-box { width: 45%; border-top: 1px dashed #94a3b8; padding-top: 8px; font-size: 11px; font-weight: 700; color: #475569; }
        .btn-print { position: fixed; top: 15px; right: 15px; background: #0f172a; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">Imprimer le Reçu Officiel (PDF)</button>

<div class="header">
    <div>
        <div class="logo-title">LBP LOGISTICS & TRANSIT</div>
        <div class="logo-sub">Fret & Colisage International • Siège Abidjan / Paris / Dakar</div>
    </div>
    <div style="text-align: right;">
        <div class="recu-badge"><?= View::e($statutLibelle) ?></div>
        <div style="font-size: 11px; margin-top: 4px; font-weight: 700; color: #64748b;">N° <?= View::e($numFacture) ?></div>
    </div>
</div>

<div class="grid">
    <div class="card">
        <div class="card-title">Informations Client & Expédition</div>
        <div class="card-val">Client : <?= View::e($clientNom) ?></div>
        <div class="card-val" style="margin-top: 4px;">Téléphone : <?= View::e($clientPhone) ?></div>
        <div class="card-val" style="margin-top: 4px;">N° Tracking Colis : <span style="color:#0284c7;"><?= View::e($trackingNum) ?></span></div>
    </div>
    <div class="card">
        <div class="card-title">Détails Règlement & Horodatage</div>
        <div class="card-val">Date d'émission : <?= View::e($dateFacture) ?></div>
        <div class="card-val" style="margin-top: 4px;">Échéance solde : <?= View::e($dateEcheance) ?></div>
        <div class="card-val" style="margin-top: 4px;">Devise : <?= View::e($devise) ?></div>
    </div>
</div>

<!-- Tableau des Emballages et Contenu du Colis -->
<div style="margin-bottom: 15px;">
    <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #0f172a; margin-bottom: 6px;">Types d'emballage & Contenu facturé</div>
    <table class="table" style="margin-bottom: 10px;">
        <thead>
            <tr>
                <th>Désignation Produit</th>
                <th>Type d'Emballage</th>
                <th style="text-align: center;">Quantité</th>
                <th style="text-align: right;">Prix Emballage</th>
                <th style="text-align: right;">Sous-Total Ligne</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($marchandises)): ?>
                <?php foreach ($marchandises as $m): ?>
                    <tr>
                        <td><strong><?= View::e($m['produit_libelle'] ?? 'Marchandise') ?></strong></td>
                        <td><?= View::e(!empty($m['emballage']) ? $m['emballage'] : 'Carton standard / Propre emballage') ?></td>
                        <td style="text-align: center;"><?= View::e((string)($m['qte_emballage'] ?? 1)) ?></td>
                        <td style="text-align: right;"><?= !empty($m['prix_emballage']) && (float)$m['prix_emballage'] > 0 ? number_format((float)$m['prix_emballage'], 0, ',', ' ') . ' XOF' : 'Inclus (0 XOF)' ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format((float)($m['total_ligne'] ?? 0), 0, ',', ' ') ?> <?= View::e($devise) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><strong>Fret & Colisage International (Règlement Facture <?= View::e($numFacture) ?>)</strong></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Synthèse Financière</th>
            <th style="text-align: right;">Montant Total Facturé</th>
            <th style="text-align: right;">Total Déjà Encaissé</th>
            <th style="text-align: right;">Solde Reste à Payer</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Règlement Facture <?= View::e($numFacture) ?></strong></td>
            <td style="text-align: right; font-weight: 700;"><?= View::e($montantTotal) ?> <?= View::e($devise) ?></td>
            <td style="text-align: right; color: #16a34a; font-weight: 800;"><?= View::e($montantEncaisse) ?> <?= View::e($devise) ?></td>
            <td style="text-align: right; color: #dc2626; font-weight: 800;"><?= View::e($montantRestant) ?> <?= View::e($devise) ?></td>
        </tr>
    </tbody>
</table>

<div class="total-box">
    <div class="total-label">SOMME NETTE REÇUE EN CAISSE :</div>
    <div class="total-amount"><?= View::e($montantEncaisse) ?> <?= View::e($devise) ?></div>
</div>

<div class="footer-signatures">
    <div class="sig-box">Cachet & Signature du Caissier / Agent</div>
    <div class="sig-box">Signature du Client / Réceptionnaire</div>
</div>

</body>
</html>
