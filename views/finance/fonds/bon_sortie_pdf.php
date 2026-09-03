<?php

declare(strict_types=1);

use App\Helpers\View;

/** @var \App\Models\Finance\DemandeFonds $demande */

$numDemande = $demande->numeroDemande;
$refBon = $demande->referenceBonCaisse ?? ('BON-CS-' . date('ymd') . '-' . $demande->id);
$agenceNom = $demande->agenceNom ?? 'Agence LBP';
$cadreLabel = $demande->cadre === 'traitement_dossier' ? 'TRAITEMENT DE DOSSIER' : 'FONCTIONNEMENT GÉNÉRAL';
$dossierStr = !empty($demande->dossierNum) ? $demande->dossierNum : '—';
$montantNum = number_format($demande->montant, 0, ',', ' ');
$devise = $demande->devise;
$dateDecaissement = $demande->dateDecaissement ? date('d/m/Y H:i', strtotime($demande->dateDecaissement)) : date('d/m/Y H:i');
$demandeurNom = $demande->demandeurNom ?? '—';
$validateurNom = $demande->validateurNom ?? 'Direction Générale';
$caissiereNom = $demande->caissiereNom ?? 'Caisse Centrale';
$modePaiement = $demande->modePaiement ?? 'Espèces';

/**
 * Fonction de conversion d'un montant en toutes lettres (Français).
 */
function montantEnLettres(float $montant): string {
    $n = (int) $montant;
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
    $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];

    if ($n === 0) return 'zéro';
    if ($n < 20) return $unites[$n];

    if ($n < 100) {
        $d = (int)($n / 10);
        $u = $n % 10;
        if ($d === 7) return 'soixante-' . ($u === 1 ? 'et-onze' : $unites[10 + $u]);
        if ($d === 9) return 'quatre-vingt-' . $unites[10 + $u];
        return $dizaines[$d] . ($u === 1 ? ' et un' : ($u > 0 ? '-' . $unites[$u] : ''));
    }

    if ($n < 1000) {
        $c = (int)($n / 100);
        $r = $n % 100;
        $centStr = ($c === 1 ? 'cent' : $unites[$c] . ' cent' . ($r === 0 ? 's' : ''));
        return $centStr . ($r > 0 ? ' ' . montantEnLettres($r) : '');
    }

    if ($n < 1000000) {
        $m = (int)($n / 1000);
        $r = $n % 1000;
        $milleStr = ($m === 1 ? 'mille' : montantEnLettres($m) . ' mille');
        return $milleStr . ($r > 0 ? ' ' . montantEnLettres($r) : '');
    }

    if ($n < 1000000000) {
        $m = (int)($n / 1000000);
        $r = $n % 1000000;
        $millionStr = ($m === 1 ? 'un million' : montantEnLettres($m) . ' millions');
        return $millionStr . ($r > 0 ? ' ' . montantEnLettres($r) : '');
    }

    return (string) $n;
}

$montantLettres = ucfirst(montantEnLettres($demande->montant)) . ' Francs CFA';
$qrData = "BON-LBP-{$refBon}-{$numDemande}-{$demande->montant}XOF";
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=' . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon_De_Caisse_<?= View::e($refBon) ?></title>
    <style>
        @page { size: A4 portrait; margin: 12mm 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0f172a; background: #fff; line-height: 1.45; font-size: 13px; margin: 0; padding: 0; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 15px; }
        .logo-title { font-size: 22px; font-weight: 900; color: #0f172a; letter-spacing: 0.5px; }
        .logo-sub { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-top: 2px; }
        
        .doc-badge { background: #0f172a; color: #fff; padding: 6px 14px; font-size: 14px; font-weight: 800; border-radius: 4px; text-transform: uppercase; text-align: center; }
        .doc-sub { font-size: 11px; color: #475569; font-weight: 700; margin-top: 4px; text-align: right; }

        .meta-box { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; }
        .meta-item-label { font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta-item-val { font-size: 12px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        .content-card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; margin-bottom: 15px; background: #fff; }
        .motif-box { background: #f1f5f9; padding: 10px 14px; border-radius: 4px; font-weight: 600; font-size: 13px; color: #0f172a; margin-top: 4px; line-height: 1.5; border-left: 4px solid #0284c7; }

        .amount-box { background: #f0fdf4; border: 2px solid #16a34a; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .amount-words { font-size: 12px; font-weight: 700; color: #15803d; font-style: italic; }
        .amount-number { font-size: 24px; font-weight: 900; color: #15803d; text-align: right; }

        .signatures-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 25px; }
        .sig-card { border: 1px dashed #94a3b8; border-radius: 6px; padding: 10px; height: 100px; display: flex; flex-direction: column; justify-content: space-between; text-align: center; background: #fafafa; }
        .sig-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #475569; }
        .sig-name { font-size: 11px; font-weight: 700; color: #0f172a; }

        .btn-print { position: fixed; top: 15px; right: 15px; background: #0284c7; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); }
        .btn-print:hover { background: #0369a1; }
        
        .footer-note { font-size: 9px; color: #94a3b8; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; }

        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Imprimer le Bon de Sortie (PDF)</button>

<!-- Header -->
<div class="header">
    <div>
        <div class="logo-title">LA BELLE PORTE TRANSIT</div>
        <div class="logo-sub">Transport International & Logistique • Fret Aérien & Maritime</div>
        <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Agence : <strong><?= View::e($agenceNom) ?></strong></div>
    </div>
    <div>
        <div class="doc-badge">BON DE SORTIE DE CAISSE</div>
        <div class="doc-sub">Réf : <?= View::e($refBon) ?></div>
        <div class="doc-sub">Demande : <?= View::e($numDemande) ?></div>
    </div>
</div>

<!-- Meta Information Strip -->
<div class="meta-box">
    <div>
        <div class="meta-item-label">Date Décaissement</div>
        <div class="meta-item-val"><?= View::e($dateDecaissement) ?></div>
    </div>
    <div>
        <div class="meta-item-label">Cadre Dépense</div>
        <div class="meta-item-val"><?= View::e($cadreLabel) ?></div>
    </div>
    <div>
        <div class="meta-item-label">N° Dossier / Transit</div>
        <div class="meta-item-val" style="font-family: monospace;"><?= View::e($dossierStr) ?></div>
    </div>
</div>

<!-- Main Details -->
<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">
            Bénéficiaire / Demandeur
        </div>
        <div style="font-size: 11px; font-weight: 700; color: #0284c7;">
            Mode de Règlement : <?= View::e($modePaiement) ?>
        </div>
    </div>
    <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">
        👤 <?= View::e($demandeurNom) ?>
    </div>

    <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 2px;">
        Objet / Motif précis de la dépense
    </div>
    <div class="motif-box">
        <?= nl2br(View::e($demande->motif)) ?>
    </div>
</div>

<!-- Amount Box -->
<div class="amount-box">
    <div>
        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #166534; margin-bottom: 2px;">
            Montant Décaissé en toutes lettres
        </div>
        <div class="amount-words">« <?= View::e($montantLettres) ?> »</div>
    </div>
    <div>
        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #166534; text-align: right; margin-bottom: 2px;">
            Montant Total
        </div>
        <div class="amount-number"><?= View::e($montantNum) ?> <?= View::e($devise) ?></div>
    </div>
</div>

<!-- Signatures Box -->
<div class="signatures-grid">
    <div class="sig-card">
        <div class="sig-title">Le Bénéficiaire / Demandeur</div>
        <div style="font-size: 9px; color: #94a3b8;">« Reçu conforme »</div>
        <div class="sig-name"><?= View::e($demandeurNom) ?></div>
    </div>

    <div class="sig-card">
        <div class="sig-title">La Caisse / Payeur</div>
        <div style="font-size: 9px; color: #94a3b8;">« Décaissement effectué »</div>
        <div class="sig-name"><?= View::e($caissiereNom) ?></div>
    </div>

    <div class="sig-card">
        <div class="sig-title">La Direction Générale</div>
        <div style="font-size: 9px; color: #16a34a; font-weight: 700;">« Bon pour Décaissement »</div>
        <div class="sig-name"><?= View::e($validateurNom) ?></div>
    </div>
</div>

<!-- Footer QR & Legal -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
    <div style="font-size: 9px; color: #64748b; max-width: 75%;">
        <strong>Rappel Procédure Comptable LBP :</strong> Le bénéficiaire s'engage à rapporter sous 48h toutes les pièces justificatives originales (quittances douanières, factures prestataires, reçus) et à reverser l'éventuel reliquat à la caisse pour l'imputation finale du dossier.
    </div>
    <div>
        <img src="<?= $qrUrl ?>" alt="QR Verification" style="width: 55px; height: 55px; border: 1px solid #cbd5e1; border-radius: 4px;">
    </div>
</div>

<div class="footer-note">
    Document officiel généré électroniquement par le système ERP La Belle Porte • Ref: <?= View::e($refBon) ?> • Date: <?= date('d/m/Y H:i:s') ?>
</div>

</body>
</html>
