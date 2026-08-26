<?php
use App\Helpers\View;

/** @var array<string, mixed> $report */
/** @var string $agenceName */
/** @var string $chefName */
/** @var string $consolideParName */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bordereau de Remise & Transfert de Caisse - LBP Transit</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0f172a; }
        .sub-logo { font-size: 10px; color: #64748b; margin-top: 2px; }
        .title { font-size: 13px; font-weight: bold; text-align: right; color: #0f172a; }
        .box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .amount-card { background: #0f172a; color: #ffffff; padding: 14px; border-radius: 6px; text-align: center; margin-bottom: 16px; }
        .amount-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
        .amount-val { font-size: 20px; font-weight: 800; color: #4ade80; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1e293b; color: #ffffff; padding: 7px 10px; font-weight: bold; text-align: left; font-size: 10px; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 30px; }
        .sig-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; height: 100px; }
        .sig-title { font-weight: bold; font-size: 10px; text-transform: uppercase; color: #475569; margin-bottom: 50px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer le Bordereau de Transfert (PDF)
        </button>
    </div>

    <div class="header">
        <div>
            <div class="logo">LA BELLE PORTE TRANSIT</div>
            <div class="sub-logo">Bordereau de Transfert & Décharge de Fonds Inter-Caisse</div>
        </div>
        <div class="title">
            BORDEREAU N° BREF-<?= date('Ymd', strtotime((string)$report['date_jour'])) ?>-<?= (int)$report['id'] ?><br>
            <span style="font-size:10px; font-weight:normal; color:#64748b;">Émis le <?= date('d/m/Y H:i') ?></span>
        </div>
    </div>

    <div class="amount-card">
        <div class="amount-title">Montant Total Réel Transféré / Versé à la Caisse Centrale</div>
        <div class="amount-val"><?= number_format((float)($report['solde_physique_declare'] ?? $report['total_encaisse_xof']), 0, ',', ' ') ?> XOF</div>
    </div>

    <div class="box grid-2">
        <div>
            <p style="margin:2px 0;"><strong>Agence Émettrice :</strong> <?= View::e($agenceName) ?></p>
            <p style="margin:2px 0;"><strong>Caissière / Émetteur :</strong> <?= View::e($chefName) ?></p>
            <p style="margin:2px 0;"><strong>Date du Point :</strong> <?= date('d/m/Y', strtotime((string)$report['date_jour'])) ?></p>
        </div>
        <div>
            <p style="margin:2px 0;"><strong>Caisse Réceptrice :</strong> Caisse Centrale / Banque</p>
            <p style="margin:2px 0;"><strong>Consolidé par :</strong> <?= View::e($consolideParName) ?></p>
            <p style="margin:2px 0;"><strong>Statut Clôture :</strong> <?= strtoupper((string)$report['statut']) ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation de la Rubrique</th>
                <th style="text-align:right;">Montant Calculé / Attendu</th>
                <th style="text-align:right;">Montant Réel Remis</th>
                <th style="text-align:center;">Écart</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Encaissements Espèces (Liquide physique)</td>
                <td style="text-align:right;"><?= number_format((float)$report['total_encaisse_xof'], 0, ',', ' ') ?> XOF</td>
                <td style="text-align:right; font-weight:bold;"><?= number_format((float)($report['solde_physique_declare'] ?? $report['total_encaisse_xof']), 0, ',', ' ') ?> XOF</td>
                <td style="text-align:center; font-weight:bold; color:<?= (float)($report['ecart_caisse'] ?? 0) < 0 ? '#dc2626' : '#16a34a' ?>;">
                    <?= number_format((float)($report['ecart_caisse'] ?? 0), 0, ',', ' ') ?> XOF
                </td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($report['explication_ecart'])): ?>
        <div class="box" style="margin-top:16px; background:#fff1f2; border-color:#fecdd3;">
            <strong style="color:#991b1b;">📝 Motifs & Explication de l'écart déclarés par la caissière :</strong>
            <p style="margin:4px 0 0 0; color:#7f1d1d;"><?= View::e($report['explication_ecart']) ?></p>
        </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-title">1. Remis par (Caissière Agence)</div>
            <div style="font-size:9px; color:#64748b; margin-top:20px;"><?= View::e($chefName) ?></div>
        </div>
        <div class="sig-box">
            <div class="sig-title">2. Convoyeur / Transporteur</div>
            <div style="font-size:9px; color:#64748b; margin-top:20px;">Nom & Signature</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">3. Reçu par (Caissière Principale / DG)</div>
            <div style="font-size:9px; color:#64748b; margin-top:20px;"><?= View::e($consolideParName) ?></div>
        </div>
    </div>

</body>
</html>
