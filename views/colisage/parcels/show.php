<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var array<string, mixed> $colis */

$badgeTone = match($colis['statut']) {
    'RETIRÉ', 'LIVRÉ' => 'success',
    'RÉCEPTIONNÉ' => 'info',
    'EN_PRÉPARATION' => 'warning',
    'EN_TRANSIT' => 'primary',
    'ARRIVÉ' => 'accent',
    default => 'secondary'
};

// Montant total with conversion
$montantTotal = (float) ($colis['montant_total'] ?? $colis['valeur_declaree'] ?? 0);
$montantEur = (float) ($colis['montant_total_eur'] ?? 0);
$devise = $colis['devise'] ?? 'XOF';

// Trafic display
$traficLabel = $colis['trafic'] ?? match($colis['type_expediteur'] ?? '') {
    'export_aerien' => 'Groupage Aérien',
    'export_maritime' => 'Groupage Maritime',
    'import_aerien' => 'Import Aérien',
    'import_maritime' => 'Import Maritime',
    default => 'Groupage Aérien',
};

// Sous-total from marchandises
$sousTotal = 0.0;
if (!empty($colis['marchandises'])) {
    foreach ($colis['marchandises'] as $m) {
        $sousTotal += (float) ($m['total_ligne'] ?? 0);
    }
}

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Détails Colis ' . View::e($colis['numero_tracking']),
            'Facture & Colisage — Imprimé spécifique LBP-CI.',
            [
                'eyebrow' => 'Suivi de Colis — Facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::badge($colis['statut'], $badgeTone, ['class' => 'finea-badge--large']),
                    Ui::button('Retour à la liste', ['href' => 'colisage/parcels', 'variant' => 'secondary']),
                    Ui::button('Imprimer la facture', ['href' => 'colisage/parcels/' . $colis['id'] . '/facture', 'variant' => 'accent']),
                ],
            ]
        ) ?>

        <?= Colisage::parcelDetailsCard($colis, $traficLabel) ?>

        <?= Colisage::parcelMerchandiseTable($colis, $sousTotal, $montantTotal, $montantEur) ?>

        <?= Colisage::parcelSignatureBoxes() ?>

        <?= Colisage::parcelStatusAction($colis, $badgeTone) ?>

        <?= Colisage::parcelFooter($colis) ?>

    </div>
</div>
