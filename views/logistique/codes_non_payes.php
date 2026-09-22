<?php
/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $agences */
/** @var array<string, array<int, array<string, mixed>>> $groupes */
/** @var array<int, array<string, mixed>> $colisExport */
/** @var array<string, array<string, mixed>> $sousTotauxGroupes */
/** @var array<string, mixed> $totaux */
/** @var array<string, mixed> $totauxExport */
/** @var string $dateDebut */
/** @var string $dateFin */
/** @var string $selectedAgence */
/** @var string $typeTransport */
/** @var string $dateFilterType */
/** @var string $search */

$queryString = http_build_query([
    'date_debut' => $dateDebut,
    'date_fin' => $dateFin,
    'agence_id' => $selectedAgence,
    'type_transport' => $typeTransport,
    'date_filter_type' => $dateFilterType,
    'q' => $search,
]);
?>

<style>
/* Neutralisation du voile sombre GPS sur cet écran */
#lbp-gps-blocker {
    display: none !important;
}

.cnp-page-container {
    padding: 1.5rem;
    max-width: 100%;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
    background: #f8fafc;
    min-height: 100vh;
}

.cnp-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    background: #ffffff;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.cnp-breadcrumb {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.cnp-breadcrumb a {
    color: #047857;
    text-decoration: none;
    font-weight: 600;
}
.cnp-breadcrumb .sep { color: #94a3b8; }
.cnp-breadcrumb .current { color: #0f172a; font-weight: 600; }

.cnp-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.35rem 0;
    letter-spacing: -0.02em;
}

.cnp-subtitle {
    font-size: 0.9rem;
    color: #475569;
    margin: 0;
    line-height: 1.4;
}

.cnp-actions-group {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    align-items: center;
}

.btn-cnp {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-cnp svg {
    flex-shrink: 0;
}

.btn-cnp-primary {
    background: #047857;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(4, 120, 87, 0.2);
}
.btn-cnp-primary:hover {
    background: #065f46;
    color: #ffffff;
}

.btn-cnp-success {
    background: #0284c7;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
}
.btn-cnp-success:hover {
    background: #0369a1;
    color: #ffffff;
}

.btn-cnp-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.btn-cnp-secondary:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.btn-cnp-outline {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
}
.btn-cnp-outline:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* KPIs */
.cnp-kpis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.cnp-kpi-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.15rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}
.cnp-kpi-highlight {
    background: #fff8f8;
    border-color: #fecaca;
}

.cnp-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.cnp-kpi-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.cnp-kpi-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #475569;
}

.cnp-kpi-valeur {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 0.35rem;
    letter-spacing: -0.02em;
}
.cnp-kpi-valeur small {
    font-size: 0.85rem;
    font-weight: 600;
}
.cnp-kpi-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

/* Filtres */
.cnp-filter-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    margin-bottom: 1.5rem;
}

.cnp-filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}

.cnp-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.cnp-filter-grow {
    flex-grow: 1;
    min-width: 220px;
}

.cnp-filter-group label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.cnp-input, .cnp-select {
    height: 38px;
    padding: 0 0.75rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: 0.85rem;
    background: #f8fafc;
    color: #1e293b;
    outline: none;
    transition: all 0.15s;
}
.cnp-input:focus, .cnp-select:focus {
    border-color: #047857;
    background: #ffffff;
    box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.12);
}

.cnp-filter-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Tableau */
.cnp-table-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.cnp-table-responsive {
    overflow-x: auto;
}

.cnp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    text-align: left;
}

.cnp-table thead th {
    background: #0f172a;
    color: #f8fafc;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 0.75rem;
    border-right: 1px solid #334155;
    white-space: nowrap;
}
.cnp-table thead th:last-child {
    border-right: none;
}

.cnp-table tbody td {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #f1f5f9;
    vertical-align: middle;
}
.cnp-table tbody td:last-child {
    border-right: none;
}

.cnp-row {
    background: #ffffff;
    transition: background 0.15s;
}
.cnp-row:hover {
    background-color: #f8fafc;
}
.cnp-row-export {
    background-color: #f0fdf4;
}
.cnp-row-export:hover {
    background-color: #e6f9ed;
}

/* Groupe Header */
.cnp-group-header-row td {
    background: #f1f5f9;
    border-bottom: 2px solid #cbd5e1;
    padding: 0.75rem 1rem;
}
.cnp-group-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.cnp-group-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.cnp-group-tag {
    background: #334155;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.cnp-group-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}
.cnp-group-count {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.cnp-group-summary-pills {
    display: flex;
    gap: 0.5rem;
    font-size: 0.8rem;
    flex-wrap: wrap;
}
.pill-credit { background: #e2e8f0; color: #334155; padding: 0.25rem 0.55rem; border-radius: 4px; font-weight: 600; }
.pill-paye { background: #dcfce7; color: #166534; padding: 0.25rem 0.55rem; border-radius: 4px; font-weight: 600; }
.pill-restant { background: #fee2e2; color: #991b1b; padding: 0.25rem 0.55rem; border-radius: 4px; font-weight: 700; }

/* Inline Group Editor */
.cnp-inline-group-editor {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.cnp-group-input {
    width: 85px;
    height: 28px;
    padding: 0 0.4rem;
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    text-transform: uppercase;
}
.cnp-group-input:focus {
    border-color: #047857;
    outline: none;
    box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.15);
}
.btn-save-group {
    background: #047857;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    width: 26px;
    height: 28px;
    cursor: pointer;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.15s;
    opacity: 0.9;
}
.btn-save-group:hover {
    opacity: 1;
}

/* Liens & Badges */
.cnp-tracking-link {
    font-weight: 700;
    color: #047857;
    text-decoration: none;
}
.cnp-tracking-link:hover { text-decoration: underline; }

.badge-export {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    background: #dbeafe;
    color: #1e40af;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    margin-left: 0.25rem;
}

.cnp-facture-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.cnp-facture-link:hover { text-decoration: underline; }

.cnp-agency-pill {
    background: #f1f5f9;
    color: #334155;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-block;
}

.cnp-client-line {
    font-size: 0.8rem;
    line-height: 1.3;
}
.cnp-client-role {
    color: #94a3b8;
    font-weight: 600;
    margin-right: 0.2rem;
}
.cnp-client-name {
    color: #1e293b;
    font-weight: 500;
}

/* Montants */
.cnp-cell-amount {
    font-variant-numeric: tabular-nums;
    font-size: 0.85rem;
    white-space: nowrap;
}
.cnp-cell-eur {
    color: #7c3aed;
    font-weight: 600;
}
.cnp-cell-credit {
    color: #0f172a;
    font-weight: 600;
}
.cnp-cell-paye {
    color: #16a34a;
    font-weight: 600;
}
.cnp-cell-restant {
    color: #dc2626;
    font-weight: 800;
}

/* Statut Pills */
.cnp-status-pill {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.status-emise { background: #fee2e2; color: #991b1b; }
.status-partiellement_payee { background: #fef3c7; color: #92400e; }
.status-en_retard { background: #fecaca; color: #7f1d1d; }

/* Sous-totaux & Total */
.cnp-subtotal-row td {
    background: #f8fafc;
    border-top: 1.5px solid #cbd5e1;
    border-bottom: 2px solid #94a3b8;
    font-weight: 700;
}
.cnp-subtotal-label {
    text-align: right;
    font-size: 0.82rem;
    text-transform: uppercase;
    color: #475569;
    letter-spacing: 0.5px;
}

.cnp-export-separator-row td {
    background: #1e3a8a;
    color: #ffffff;
    padding: 0.6rem 1rem;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}
.cnp-export-title-banner {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.cnp-export-subtotal-row td {
    background: #eff6ff;
    font-weight: 700;
    border-bottom: 2px solid #bfdbfe;
}

.cnp-grand-total-row td {
    background: #0f172a;
    color: #ffffff;
    padding: 1rem 0.75rem;
    font-size: 0.95rem;
    font-weight: 800;
    border: none;
}
.cnp-grand-total-label {
    text-align: right;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    color: #f8fafc;
}
.badge-total-due {
    background: #dc2626;
    color: #ffffff;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    font-weight: 800;
    white-space: nowrap;
}

.cnp-empty-state {
    padding: 4rem 2rem;
    text-align: center;
    background: #ffffff;
}
.cnp-empty-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem auto;
    color: #047857;
}
.cnp-empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: #0f172a;
    font-weight: 700;
}
.cnp-empty-state p {
    color: #64748b;
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.5;
}
</style>

<div class="cnp-page-container">
    <!-- En-tête & Titre -->
    <div class="cnp-header-bar">
        <div>
            <div class="cnp-breadcrumb">
                <a href="<?= \App\Helpers\View::url('logistique/dashboard') ?>">Logistique</a>
                <span class="sep">/</span>
                <span class="current">Suivi Groupages</span>
            </div>
            <h1 class="cnp-title">
                Tableau des Codes Non Payés (CNP)
            </h1>
            <p class="cnp-subtitle">
                <?= $dateDebut === $dateFin 
                    ? "Situation des impayés du <strong>" . date('d/m/Y', strtotime($dateDebut)) . "</strong>" 
                    : "Situation des impayés du <strong>" . date('d/m/Y', strtotime($dateDebut)) . "</strong> au <strong>" . date('d/m/Y', strtotime($dateFin)) . "</strong>" ?>
                — Module Responsable Groupage Général (Aéroport, Adjamé, Dokui & Réseau)
            </p>
        </div>

        <div class="cnp-actions-group">
            <a href="<?= \App\Helpers\View::url('logistique/codes-non-payes/export-pdf?' . $queryString) ?>" 
               target="_blank" 
               class="btn-cnp btn-cnp-primary" 
               id="btn-export-pdf"
               title="Ouvrir la version imprimable / PDF conforme au modèle S.T.T-CI">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Imprimer / PDF S.T.T-CI
            </a>

            <a href="<?= \App\Helpers\View::url('logistique/codes-non-payes/export-excel?' . $queryString) ?>" 
               class="btn-cnp btn-cnp-success" 
               id="btn-export-excel"
               title="Exporter en fichier Excel / CSV">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Export Excel (.CSV)
            </a>

            <a href="<?= \App\Helpers\View::url('finance/factures?statut=emise') ?>" 
               class="btn-cnp btn-cnp-secondary" 
               title="Accéder au listing complet des factures">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
                Factures Finance
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="cnp-kpis-grid">
        <div class="cnp-kpi-card">
            <div class="cnp-kpi-header">
                <span class="cnp-kpi-label">Colis Non Soldés</span>
                <span class="cnp-kpi-icon-wrap" style="color: #2563eb; background: #eff6ff;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </span>
            </div>
            <div class="cnp-kpi-valeur" style="color: #2563eb;"><?= number_format($totaux['count'], 0, ',', ' ') ?></div>
            <div class="cnp-kpi-sub">Factures émises ou partielles</div>
        </div>

        <div class="cnp-kpi-card">
            <div class="cnp-kpi-header">
                <span class="cnp-kpi-label">Montant à Crédit</span>
                <span class="cnp-kpi-icon-wrap" style="color: #0f172a; background: #f1f5f9;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                </span>
            </div>
            <div class="cnp-kpi-valeur" style="color: #0f172a;"><?= number_format($totaux['montant_total'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Total facturé sur la sélection</div>
        </div>

        <div class="cnp-kpi-card">
            <div class="cnp-kpi-header">
                <span class="cnp-kpi-label">Montant Encaissé</span>
                <span class="cnp-kpi-icon-wrap" style="color: #16a34a; background: #f0fdf4;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
            </div>
            <div class="cnp-kpi-valeur" style="color: #16a34a;"><?= number_format($totaux['montant_encaisse'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Règlements déjà reçus</div>
        </div>

        <div class="cnp-kpi-card cnp-kpi-highlight">
            <div class="cnp-kpi-header">
                <span class="cnp-kpi-label">Reste Dû à Recouvrer</span>
                <span class="cnp-kpi-icon-wrap" style="color: #dc2626; background: #fef2f2;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </span>
            </div>
            <div class="cnp-kpi-valeur" style="color: #dc2626;"><?= number_format($totaux['montant_restant'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Montant en attente de paiement</div>
        </div>

        <?php if ($totaux['montant_eur'] > 0): ?>
        <div class="cnp-kpi-card">
            <div class="cnp-kpi-header">
                <span class="cnp-kpi-label">Équivalent Euro</span>
                <span class="cnp-kpi-icon-wrap" style="color: #7c3aed; background: #f5f3ff;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><line x1="12" y1="18" x2="12" y2="20"></line><line x1="12" y1="4" x2="12" y2="6"></line></svg>
                </span>
            </div>
            <div class="cnp-kpi-valeur" style="color: #7c3aed;"><?= number_format($totaux['montant_eur'], 2, ',', ' ') ?> <small>€</small></div>
            <div class="cnp-kpi-sub">Devise internationale</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Barre de Filtres -->
    <div class="cnp-filter-card">
        <form method="GET" action="<?= \App\Helpers\View::url('logistique/codes-non-payes') ?>" class="cnp-filter-form">
            <div class="cnp-filter-group">
                <label for="agence_id">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    Agence
                </label>
                <select name="agence_id" id="agence_id" class="cnp-select">
                    <option value="all" <?= $selectedAgence === 'all' ? 'selected' : '' ?>>Toutes les agences (Réseau)</option>
                    <?php foreach ($agences as $ag): ?>
                        <option value="<?= (int) $ag['id'] ?>" <?= (string) $selectedAgence === (string) $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cnp-filter-group">
                <label for="date_debut">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Date Début
                </label>
                <input type="date" name="date_debut" id="date_debut" value="<?= htmlspecialchars($dateDebut) ?>" class="cnp-input">
            </div>

            <div class="cnp-filter-group">
                <label for="date_fin">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Date Fin
                </label>
                <input type="date" name="date_fin" id="date_fin" value="<?= htmlspecialchars($dateFin) ?>" class="cnp-input">
            </div>

            <div class="cnp-filter-group">
                <label for="type_transport">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    Type d'envoi
                </label>
                <select name="type_transport" id="type_transport" class="cnp-select">
                    <option value="all" <?= $typeTransport === 'all' ? 'selected' : '' ?>>Tous les types</option>
                    <option value="export" <?= $typeTransport === 'export' ? 'selected' : '' ?>>Colis Export (Aérien)</option>
                    <option value="cargo" <?= $typeTransport === 'cargo' ? 'selected' : '' ?>>Cargo (Maritime / Routier)</option>
                </select>
            </div>

            <div class="cnp-filter-group">
                <label for="date_filter_type">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Base de date
                </label>
                <select name="date_filter_type" id="date_filter_type" class="cnp-select">
                    <option value="facture" <?= $dateFilterType === 'facture' ? 'selected' : '' ?>>Date Émission Facture</option>
                    <option value="colis" <?= $dateFilterType === 'colis' ? 'selected' : '' ?>>Date Départ / Saisie Colis</option>
                </select>
            </div>

            <div class="cnp-filter-group cnp-filter-grow">
                <label for="q">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Recherche rapide
                </label>
                <input type="text" name="q" id="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tracking, Facture, Client, Groupe (A1, A2)..." class="cnp-input">
            </div>

            <div class="cnp-filter-buttons">
                <button type="submit" class="btn-cnp btn-cnp-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Filtrer
                </button>
                <a href="<?= \App\Helpers\View::url('logistique/codes-non-payes') ?>" class="btn-cnp btn-cnp-outline" title="Aujourd'hui, toutes agences">
                    Aujourd'hui
                </a>
            </div>
        </form>
    </div>

    <!-- Tableau Principal -->
    <div class="cnp-table-card">
        <?php if (empty($groupes)): ?>
            <div class="cnp-empty-state">
                <div class="cnp-empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                </div>
                <h3>Aucun code non payé trouvé</h3>
                <p>Toutes les factures pour les critères sélectionnés sont entièrement soldées ou aucun colis ne correspond à cette période.</p>
            </div>
        <?php else: ?>
            <div class="cnp-table-responsive">
                <table class="cnp-table">
                    <thead>
                        <tr>
                            <th style="width: 95px;">DATE</th>
                            <th style="width: 130px;">GROUPE / LOT</th>
                            <th style="width: 150px;">CODE (TRACKING)</th>
                            <th style="width: 130px;">N° FACTURE</th>
                            <th style="width: 140px;">AGENCE</th>
                            <th>EXPÉDITEUR & DESTINATAIRE</th>
                            <th style="text-align: right; width: 120px;">MONTANT EURO</th>
                            <th style="text-align: right; width: 140px;">MONTANT À CRÉDIT</th>
                            <th style="text-align: right; width: 130px;">MONTANT PAYÉ</th>
                            <th style="text-align: right; width: 140px;">MONTANT RESTANT</th>
                            <th style="width: 110px; text-align: center;">STATUT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupes as $groupeNom => $lignes): ?>
                            <!-- En-tête de Groupe -->
                            <tr class="cnp-group-header-row">
                                <td colspan="11">
                                    <div class="cnp-group-header-content">
                                        <div class="cnp-group-badge">
                                            <span class="cnp-group-tag">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                                GROUPE
                                            </span>
                                            <span class="cnp-group-name"><?= htmlspecialchars($groupeNom) ?></span>
                                            <span class="cnp-group-count">(<?= count($lignes) ?> colis)</span>
                                        </div>
                                        <div class="cnp-group-summary-pills">
                                            <?php $sub = $sousTotauxGroupes[$groupeNom] ?? null; ?>
                                            <?php if ($sub): ?>
                                                <span class="pill-credit">Crédit : <?= number_format($sub['montant_total'], 0, ',', ' ') ?> F</span>
                                                <span class="pill-paye">Payé : <?= number_format($sub['montant_encaisse'], 0, ',', ' ') ?> F</span>
                                                <span class="pill-restant">Reste dû : <strong><?= number_format($sub['montant_restant'], 0, ',', ' ') ?> F</strong></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Lignes du groupe -->
                            <?php foreach ($lignes as $row): ?>
                                <tr class="cnp-row <?= $row['is_export'] ? 'cnp-row-export' : '' ?>" id="row-colis-<?= (int)$row['colis_id'] ?>">
                                    <td class="cnp-cell-date">
                                        <?= htmlspecialchars($row['date_formatted']) ?>
                                    </td>

                                    <!-- Groupe éditable en direct (A1, A2, A3...) -->
                                    <td class="cnp-cell-groupe">
                                        <div class="cnp-inline-group-editor" data-colis-id="<?= (int)$row['colis_id'] ?>">
                                            <input type="text" 
                                                   class="cnp-group-input" 
                                                   value="<?= htmlspecialchars($row['groupe_code'] ?? ($row['expedition_reference'] ?? '')) ?>" 
                                                   placeholder="Alias..." 
                                                   maxlength="30"
                                                   title="Cliquez pour modifier le groupe (A1, A2, A3...) — Entrée pour valider">
                                            <button type="button" class="btn-save-group" title="Enregistrer l'alias">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="cnp-cell-code">
                                        <a href="<?= \App\Helpers\View::url('colisage/parcels/' . (int)$row['colis_id']) ?>" class="cnp-tracking-link" target="_blank">
                                            <?= htmlspecialchars($row['code']) ?>
                                        </a>
                                        <?php if ($row['is_export']): ?>
                                            <span class="badge-export">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"></path><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                                Export
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="cnp-cell-facture">
                                        <a href="<?= \App\Helpers\View::url('finance/factures/' . (int)$row['facture_id']) ?>" class="cnp-facture-link" target="_blank">
                                            <?= htmlspecialchars($row['numero_facture']) ?>
                                        </a>
                                    </td>

                                    <td class="cnp-cell-agence">
                                        <span class="cnp-agency-pill">
                                            <?= htmlspecialchars($row['agence_nom']) ?>
                                        </span>
                                    </td>

                                    <td class="cnp-cell-clients">
                                        <div class="cnp-client-line">
                                            <span class="cnp-client-role">Exp:</span>
                                            <span class="cnp-client-name"><?= htmlspecialchars($row['expediteur_nom'] ?? 'Client') ?></span>
                                        </div>
                                        <div class="cnp-client-line">
                                            <span class="cnp-client-role">Dest:</span>
                                            <span class="cnp-client-name"><?= htmlspecialchars($row['destinataire_nom'] ?? '-') ?></span>
                                        </div>
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-eur" style="text-align: right;">
                                        <?= $row['montant_eur'] > 0 ? number_format($row['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-credit" style="text-align: right;">
                                        <?= number_format($row['montant_total'], 0, ',', ' ') ?> F
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-paye" style="text-align: right;">
                                        <?= number_format($row['montant_encaisse'], 0, ',', ' ') ?> F
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-restant" style="text-align: right;">
                                        <strong><?= number_format($row['montant_restant'], 0, ',', ' ') ?> F</strong>
                                    </td>

                                    <td class="cnp-cell-statut" style="text-align: center;">
                                        <span class="cnp-status-pill status-<?= htmlspecialchars($row['facture_statut']) ?>">
                                            <?= htmlspecialchars($row['facture_statut_label']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Ligne Sous-Total Groupe -->
                            <?php if (!empty($sousTotauxGroupes[$groupeNom])): ?>
                                <?php $sg = $sousTotauxGroupes[$groupeNom]; ?>
                                <tr class="cnp-subtotal-row">
                                    <td colspan="6" class="cnp-subtotal-label">
                                        Sous-Total Groupe <?= htmlspecialchars($groupeNom) ?> (<?= $sg['count'] ?> colis)
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-eur" style="text-align: right;">
                                        <?= $sg['montant_eur'] > 0 ? number_format($sg['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-credit" style="text-align: right;">
                                        <?= number_format($sg['montant_total'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-paye" style="text-align: right;">
                                        <?= number_format($sg['montant_encaisse'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-restant" style="text-align: right;">
                                        <strong><?= number_format($sg['montant_restant'], 0, ',', ' ') ?> F</strong>
                                    </td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Section COLIS EXPORT Spécifique (si applicable) -->
                        <?php if (!empty($colisExport) && count($colisExport) > 0): ?>
                            <tr class="cnp-export-separator-row">
                                <td colspan="11">
                                    <div class="cnp-export-title-banner">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"></path><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                        RECAPITULATIF SECTION COLIS EXPORT (<?= $totauxExport['count'] ?> colis)
                                    </div>
                                </td>
                            </tr>
                            <tr class="cnp-export-subtotal-row">
                                <td colspan="6" class="cnp-subtotal-label">
                                    Sous-Total Général COLIS EXPORT
                                </td>
                                <td class="cnp-cell-amount cnp-cell-eur" style="text-align: right;">
                                    <?= $totauxExport['montant_eur'] > 0 ? number_format($totauxExport['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                </td>
                                <td class="cnp-cell-amount cnp-cell-credit" style="text-align: right;">
                                    <?= number_format($totauxExport['montant_total'], 0, ',', ' ') ?> F
                                </td>
                                <td class="cnp-cell-amount cnp-cell-paye" style="text-align: right;">
                                    <?= number_format($totauxExport['montant_encaisse'], 0, ',', ' ') ?> F
                                </td>
                                <td class="cnp-cell-amount cnp-cell-restant" style="text-align: right;">
                                    <strong><?= number_format($totauxExport['montant_restant'], 0, ',', ' ') ?> F</strong>
                                </td>
                                <td></td>
                            </tr>
                        <?php endif; ?>

                        <!-- Total Général -->
                        <tr class="cnp-grand-total-row">
                            <td colspan="6" class="cnp-grand-total-label">
                                TOTAL GÉNÉRAL DES CODES NON PAYÉS (<?= $totaux['count'] ?> colis)
                            </td>
                            <td class="cnp-cell-amount cnp-cell-eur" style="text-align: right;">
                                <?= $totaux['montant_eur'] > 0 ? number_format($totaux['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                            </td>
                            <td class="cnp-cell-amount cnp-cell-credit" style="text-align: right;">
                                <?= number_format($totaux['montant_total'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="cnp-cell-amount cnp-cell-paye" style="text-align: right;">
                                <?= number_format($totaux['montant_encaisse'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="cnp-cell-amount cnp-cell-restant" style="text-align: right;">
                                <span class="badge-total-due"><?= number_format($totaux['montant_restant'], 0, ',', ' ') ?> FCFA</span>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Jeton CSRF pour AJAX -->
<input type="hidden" id="csrf-token-cnp" value="<?= \App\Helpers\Csrf::token() ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.getElementById('csrf-token-cnp')?.value || '';

    // Gestion de la sauvegarde AJAX du groupe / lot
    document.querySelectorAll('.cnp-inline-group-editor').forEach(function(editor) {
        const colisId = editor.dataset.colisId;
        const input = editor.querySelector('.cnp-group-input');
        const btn = editor.querySelector('.btn-save-group');

        function saveGroupe() {
            const newGroup = input.value.trim().toUpperCase();
            btn.textContent = '...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('colis_id', colisId);
            formData.append('groupe_code', newGroup);

            fetch('<?= \App\Helpers\View::url('logistique/codes-non-payes/update-groupe') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    btn.style.backgroundColor = '#16a34a';
                    setTimeout(() => {
                        btn.style.backgroundColor = '#047857';
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert('Erreur lors de la mise à jour : ' + (data.error || 'Erreur inconnue'));
                    btn.textContent = '✗';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Erreur réseau ou serveur : ' + err.message);
                btn.textContent = '✗';
                btn.disabled = false;
            });
        }

        btn.addEventListener('click', saveGroupe);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveGroupe();
            }
        });
    });
});
</script>
