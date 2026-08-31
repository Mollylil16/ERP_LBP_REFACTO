<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Finance\FinanceDashboardController;
use App\Controllers\Finance\FinanceController;

/** @var Router $router */

$router->group('/finance', function (Router $router): void {
    $router->get('/', [FinanceDashboardController::class, 'index']);
    $router->get('/dashboard', [FinanceDashboardController::class, 'index']);

    // Factures
    $router->get('/factures', [FinanceController::class, 'facturesIndex']);
    $router->get('/factures/nouveau', [FinanceController::class, 'factureCreate']);
    $router->post('/factures/enregistrer', [FinanceController::class, 'factureStore']);
    $router->post('/factures/relancer-tout', [FinanceController::class, 'factureRelancerTout']);
    $router->get('/factures/{id}', [FinanceController::class, 'factureShow']);
    $router->post('/factures/{id}/encaisser', [FinanceController::class, 'factureEncaisser']);
    $router->post('/factures/{id}/payer-portefeuille', [FinanceController::class, 'facturePayerPortefeuille']);
    $router->post('/factures/{id}/reinitialiser', [FinanceController::class, 'factureReinitialiser']);
    $router->post('/factures/{id}/relancer', [FinanceController::class, 'factureRelancer']);
    $router->get('/factures/{id}/modifier', [\App\Controllers\Facturation\FactureEditController::class, 'edit']);
    $router->post('/factures/{id}/modifier', [\App\Controllers\Facturation\FactureEditController::class, 'update']);
    $router->post('/factures/{id}/supprimer', [FinanceController::class, 'factureDelete']);

    // Clôtures et Points de caisse
    $router->get('/clotures', [FinanceController::class, 'cloturesIndex']);
    $router->get('/clotures/export-pdf-global', [FinanceController::class, 'exportClotureGlobalPdf']);
    $router->get('/clotures/export-pdf-agence', [FinanceController::class, 'exportClotureAgencePdf']);
    $router->post('/clotures/soumettre', [FinanceController::class, 'clotureSoumettre']);
    $router->get('/clotures/{id}/export-pdf', [FinanceController::class, 'exportCloturePdf']);
    $router->get('/clotures/{id}/bordereau-pdf', [FinanceController::class, 'exportBordereauPdf']);
    $router->post('/clotures/{id}/consolider', [FinanceController::class, 'clotureConsolider']);

    // Dépenses prestataires
    $router->get('/depenses', [FinanceController::class, 'depensesIndex']);
    $router->post('/depenses/enregistrer', [FinanceController::class, 'depenseStore']);
    $router->post('/depenses/{id}/valider', [FinanceController::class, 'depenseValider']);

    // Comptabilité & SYSCOHADA
    $router->get('/comptabilite', [FinanceController::class, 'comptabilite']);
    $router->post('/comptabilite/ecriture-manuelle', [FinanceController::class, 'ecritureManuelleStore']);
    $router->post('/comptabilite/lettrer', [FinanceController::class, 'lettrer']);
    $router->post('/comptabilite/{id}/contre-passer', [FinanceController::class, 'contrePasser']);
    $router->get('/export-syscohada', [FinanceController::class, 'exportSyscohada']);
    $router->get('/balance-comptes', [FinanceController::class, 'balanceComptes']);
    $router->get('/plan-comptable', [FinanceController::class, 'planComptableIndex']);
    $router->post('/plan-comptable/enregistrer', [FinanceController::class, 'planComptableStore']);

    // Pilotage financier & Rentabilité
    $router->get('/rentabilite', [FinanceController::class, 'rentabilite']);
    $router->get('/rentabilite/export-pdf', [FinanceController::class, 'exportRentabilitePdf']);
    $router->get('/balance-agee', [FinanceController::class, 'balanceAgee']);
    $router->get('/balance-agee/export-pdf', [FinanceController::class, 'exportBalanceAgeePdf']);

    // Portefeuilles Clients & Acomptes
    $router->get('/portefeuilles', [FinanceController::class, 'portefeuillesIndex']);
    $router->post('/portefeuilles/crediter', [FinanceController::class, 'portefeuilleCrediter']);

    // Landed Costs / Coûts d'approche Douane & Fret
    $router->get('/couts-approche', [FinanceController::class, 'coutsApprocheIndex']);
    $router->post('/couts-approche/calculer', [FinanceController::class, 'coutsApprocheCalculer']);

    // Rapprochement Mobile Money & Reçus Officiels
    $router->get('/rapprochement-mobile-money', [FinanceController::class, 'rapprochementMobileMoneyIndex']);
    $router->post('/rapprochement-mobile-money/valider', [FinanceController::class, 'rapprochementMobileMoneyValider']);
    $router->get('/factures/{id}/recu-pdf', [FinanceController::class, 'exportRecuPdf']);
    $router->get('/paiements/{id}/recu', [FinanceController::class, 'exportRecuPdf']);
    $router->get('/paiements/{id}/recu-pdf', [FinanceController::class, 'exportRecuPdf']);

    // Trésorerie Prévisionnelle (30/60/90j)
    $router->get('/tresorerie', [FinanceController::class, 'tresorerieIndex']);

    // Guide Interactif & Tutoriel Finance
    $router->get('/guide', [FinanceController::class, 'guideIndex']);
});
