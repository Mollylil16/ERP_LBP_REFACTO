<?php
use App\Helpers\View;
use App\Models\Finance\EtatJournalier;

/** @var EtatJournalier $report */
/** @var string $agenceName */
/** @var string $chefName */
/** @var string $consolideParName */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Point de Caisse Journalier — <?= View::e($agenceName) ?> — <?= View::e($report->dateJour) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0f172a; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #334155; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0f172a; color: #ffffff; padding: 8px 10px; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .badge-soumis { background: #e0f2fe; color: #0369a1; }
        .badge-consolide { background: #dcfce7; color: #15803d; }
        .badge-brouillon { background: #fef3c7; color: #b45309; }
        .sign-area { margin-top: 40px; display: flex; justify-content: space-between; }
        .sign-box { border-top: 1px solid #0f172a; width: 30%; text-align: center; padding-top: 8px; font-weight: bold; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div class="logo">LA BELLE PORTE TRANSIT</div>
        <div class="title">Procès-Verbal de Clôture & Point de Caisse</div>
    </div>

    <div class="meta-box">
        <div>
            <div><strong>Agence :</strong> <?= View::e($agenceName) ?></div>
            <div><strong>Date du point :</strong> <?= View::e($report->dateJour) ?></div>
            <div><strong>Chef d'Agence / Caissier :</strong> <?= View::e($chefName) ?></div>
        </div>
        <div>
            <div><strong>Statut du point :</strong> <span class="badge badge-<?= View::e($report->statut) ?>"><?= strtoupper($report->statut) ?></span></div>
            <div><strong>Date de soumission :</strong> <?= $report->dateSoumission ? View::e($report->dateSoumission) : 'N/A' ?></div>
            <div><strong>Consolidé par :</strong> <?= View::e($consolideParName) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rubrique de Rapprochement</th>
                <th class="text-right">Montant XOF</th>
                <th class="text-right">Montant EUR</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Colis enregistrés le jour</td>
                <td class="text-right" colspan="2"><strong><?= number_format($report->nbColisEnregistres) ?> colis</strong></td>
            </tr>
            <tr>
                <td>Factures émises le jour</td>
                <td class="text-right" colspan="2"><strong><?= number_format($report->nbFacturesEmises) ?> factures</strong></td>
            </tr>
            <tr>
                <td>Total facturé le jour</td>
                <td class="text-right"><?= number_format($report->totalFactureXof, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($report->totalFactureEur, 2, ',', ' ') ?> EUR</td>
            </tr>
            <tr>
                <td><strong>Encaissements réels du jour (Solde Théorique attendu)</strong></td>
                <td class="text-right" style="color:#15803d; font-weight:bold;"><?= number_format($report->totalEncaisseXof, 0, ',', ' ') ?> XOF</td>
                <td class="text-right" style="color:#15803d; font-weight:bold;"><?= number_format($report->totalEncaisseEur, 2, ',', ' ') ?> EUR</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td><strong>Solde Physique compté en caisse (Billets + Pièces)</strong></td>
                <td class="text-right" style="font-weight:bold; font-size:12px;"><?= $report->soldePhysiqueDeclare !== null ? number_format($report->soldePhysiqueDeclare, 0, ',', ' ') . ' XOF' : 'Non déclaré' ?></td>
                <td class="text-right">-</td>
            </tr>
            <tr>
                <td><strong>Écart de caisse (Physique - Théorique)</strong></td>
                <td class="text-right" style="font-weight:bold; color: <?= $report->ecartCaisse == 0 ? '#15803d' : '#b91c1c' ?>;">
                    <?= number_format($report->ecartCaisse, 0, ',', ' ') ?> XOF
                </td>
                <td class="text-right">-</td>
            </tr>
            <?php if ($report->explicationEcart): ?>
            <tr>
                <td colspan="3" style="background:#fff3cd; color:#856404; font-style:italic;">
                    <strong>Explication de l'écart déclarée par le Chef d'Agence :</strong> <?= View::e($report->explicationEcart) ?>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sign-area">
        <div class="sign-box">Le Chef d'Agence / Caissier<br><br><br><?= View::e($chefName) ?></div>
        <div class="sign-box">La Caissière Principale<br><br><br><?= View::e($consolideParName) ?></div>
        <div class="sign-box">La Direction Générale<br><br><br>Visa DG</div>
    </div>

</body>
</html>
