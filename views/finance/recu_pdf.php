<?php

declare(strict_types=1);

use App\Helpers\View;

/** @var \App\Models\Finance\Facture $facture */

$numFacture = $facture->numeroFacture;
$clientNom = 'Client LBP Transit';
$montantTotal = number_format($facture->montantTotal, 0, ',', ' ');
$montantEncaisse = number_format($facture->montantEncaisse, 0, ',', ' ');
$montantRestant = number_format($facture->montantRestant, 0, ',', ' ');
$devise = $facture->devise;
$dateFacture = date('d/m/Y H:i', strtotime($facture->createdAt ?? 'now'));
$dateEcheance = !empty($facture->dateEcheanceSolde) ? date('d/m/Y', strtotime($facture->dateEcheanceSolde)) : 'Comptant';

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
        .recu-badge { background: #16a34a; color: #fff; padding: 6px 14px; font-size: 14px; font-weight: 800; border-radius: 4px; text-transform: uppercase; display: inline-block; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; }
        .card-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px; }
        .card-val { font-size: 14px; font-weight: 700; color: #0f172a; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .table th { background: #0f172a; color: #fff; padding: 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .table td { border-bottom: 1px solid #e2e8f0; padding: 12px 10px; }
        .total-box { background: #f0fdf4; border: 2px solid #16a34a; padding: 15px; text-align: right; border-radius: 6px; margin-bottom: 30px; }
        .total-label { font-size: 12px; font-weight: 700; color: #15803d; text-transform: uppercase; }
        .total-amount { font-size: 24px; font-weight: 900; color: #16a34a; }
        .footer-signatures { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; }
        .sig-box { width: 45%; border-top: 1px dashed #94a3b8; padding-top: 10px; font-size: 11px; font-weight: 700; color: #475569; }
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
        <div class="recu-badge">REÇU DE PAIEMENT OFFICIEL</div>
        <div style="font-size: 11px; margin-top: 4px; font-weight: 700; color: #64748b;">N° <?= View::e($numFacture) ?></div>
    </div>
</div>

<div class="grid">
    <div class="card">
        <div class="card-title">Informations Règlement</div>
        <div class="card-val">Date d'émission : <?= View::e($dateFacture) ?></div>
        <div class="card-val" style="margin-top: 4px;">Échéance solde : <?= View::e($dateEcheance) ?></div>
        <div class="card-val" style="margin-top: 4px;">Devise de transaction : <?= View::e($devise) ?></div>
    </div>
    <div class="card">
        <div class="card-title">Authentification Caisse & QR Code</div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="<?= $qrUrl ?>" style="width: 60px; height: 60px;" alt="QR Code Control">
            <div style="font-size: 10px; color: #475569;">
                <strong>Document Sécurisé ERP LBP</strong><br>
                Vérifié par la caisse centrale.<br>
                Conservé aux archives financières.
            </div>
        </div>
    </div>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Désignation Service / Prestation</th>
            <th>Montant Facturé</th>
            <th>Montant Encaisse</th>
            <th>Reste à Recouvrer</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Fret & Colisage International (Règlement Facture <?= View::e($numFacture) ?>)</strong></td>
            <td><?= View::e($montantTotal) ?> <?= View::e($devise) ?></td>
            <td style="color: #16a34a; font-weight: 800;"><?= View::e($montantEncaisse) ?> <?= View::e($devise) ?></td>
            <td style="color: #dc2626; font-weight: 800;"><?= View::e($montantRestant) ?> <?= View::e($devise) ?></td>
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
