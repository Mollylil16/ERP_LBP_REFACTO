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
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                    <path d="M6 14h12v8H6z"/>
                </svg>
                Imprimer / PDF S.T.T-CI
            </a>

            <a href="<?= \App\Helpers\View::url('logistique/codes-non-payes/export-excel?' . $queryString) ?>" 
               class="btn-cnp btn-cnp-success" 
               id="btn-export-excel"
               title="Exporter en fichier Excel / CSV">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                Export Excel (.CSV)
            </a>

            <a href="<?= \App\Helpers\View::url('finance/factures?statut=emise') ?>" 
               class="btn-cnp btn-cnp-secondary" 
               title="Accéder au listing complet des factures">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                    <line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                Factures Finance
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="cnp-kpis-grid">
        <div class="cnp-kpi-card">
            <div class="cnp-kpi-label">Colis Non Soldés</div>
            <div class="cnp-kpi-valeur" style="color: #2563eb;"><?= number_format($totaux['count'], 0, ',', ' ') ?></div>
            <div class="cnp-kpi-sub">Factures émises ou partielles</div>
        </div>

        <div class="cnp-kpi-card">
            <div class="cnp-kpi-label">Montant à Crédit (Total)</div>
            <div class="cnp-kpi-valeur" style="color: #0f172a;"><?= number_format($totaux['montant_total'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Total facturé sur la sélection</div>
        </div>

        <div class="cnp-kpi-card">
            <div class="cnp-kpi-label">Montant Encaissé (Acomptes)</div>
            <div class="cnp-kpi-valeur" style="color: #16a34a;"><?= number_format($totaux['montant_encaisse'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Règlements déjà reçus</div>
        </div>

        <div class="cnp-kpi-card cnp-kpi-highlight">
            <div class="cnp-kpi-label">Reste Dû à Recouvrer</div>
            <div class="cnp-kpi-valeur" style="color: #dc2626;"><?= number_format($totaux['montant_restant'], 0, ',', ' ') ?> <small>FCFA</small></div>
            <div class="cnp-kpi-sub">Montant en attente de paiement</div>
        </div>

        <?php if ($totaux['montant_eur'] > 0): ?>
        <div class="cnp-kpi-card">
            <div class="cnp-kpi-label">Equivalent Euro</div>
            <div class="cnp-kpi-valeur" style="color: #7c3aed;"><?= number_format($totaux['montant_eur'], 2, ',', ' ') ?> <small>€</small></div>
            <div class="cnp-kpi-sub">Devise internationale</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Barre de Filtres -->
    <div class="cnp-filter-card">
        <form method="GET" action="<?= \App\Helpers\View::url('logistique/codes-non-payes') ?>" class="cnp-filter-form">
            <div class="cnp-filter-group">
                <label for="agence_id">Agence</label>
                <select name="agence_id" id="agence_id" class="cnp-select">
                    <option value="all" <?= $selectedAgence === 'all' ? 'selected' : '' ?>>🌐 Toutes les agences (Réseau)</option>
                    <?php foreach ($agences as $ag): ?>
                        <option value="<?= (int) $ag['id'] ?>" <?= (string) $selectedAgence === (string) $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cnp-filter-group">
                <label for="date_debut">Date Début</label>
                <input type="date" name="date_debut" id="date_debut" value="<?= htmlspecialchars($dateDebut) ?>" class="cnp-input">
            </div>

            <div class="cnp-filter-group">
                <label for="date_fin">Date Fin</label>
                <input type="date" name="date_fin" id="date_fin" value="<?= htmlspecialchars($dateFin) ?>" class="cnp-input">
            </div>

            <div class="cnp-filter-group">
                <label for="type_transport">Type d'envoi</label>
                <select name="type_transport" id="type_transport" class="cnp-select">
                    <option value="all" <?= $typeTransport === 'all' ? 'selected' : '' ?>>Tous les types</option>
                    <option value="export" <?= $typeTransport === 'export' ? 'selected' : '' ?>>✈️ Colis Export (Aérien)</option>
                    <option value="cargo" <?= $typeTransport === 'cargo' ? 'selected' : '' ?>>🚢 Cargo (Maritime / Routier)</option>
                </select>
            </div>

            <div class="cnp-filter-group">
                <label for="date_filter_type">Base de date</label>
                <select name="date_filter_type" id="date_filter_type" class="cnp-select">
                    <option value="facture" <?= $dateFilterType === 'facture' ? 'selected' : '' ?>>Date Émission Facture</option>
                    <option value="colis" <?= $dateFilterType === 'colis' ? 'selected' : '' ?>>Date Départ / Saisie Colis</option>
                </select>
            </div>

            <div class="cnp-filter-group cnp-filter-grow">
                <label for="q">Recherche rapide</label>
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
                <div class="cnp-empty-icon">🎉</div>
                <h3>Aucun code non payé trouvé</h3>
                <p>Toutes les factures pour les critères sélectionnés sont entièrement soldées ou aucun colis ne correspond à cette période.</p>
            </div>
        <?php else: ?>
            <div class="cnp-table-responsive">
                <table class="cnp-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">DATE</th>
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
                                            <span class="cnp-group-tag">GROUPE</span>
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
                                            <button type="button" class="btn-save-group" title="Enregistrer l'alias">✓</button>
                                        </div>
                                    </td>

                                    <td class="cnp-cell-code">
                                        <a href="<?= \App\Helpers\View::url('colisage/parcels/' . (int)$row['colis_id']) ?>" class="cnp-tracking-link" target="_blank">
                                            <?= htmlspecialchars($row['code']) ?>
                                        </a>
                                        <?php if ($row['is_export']): ?>
                                            <span class="badge-export">Export</span>
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

                                    <td class="cnp-cell-amount cnp-cell-eur">
                                        <?= $row['montant_eur'] > 0 ? number_format($row['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-credit">
                                        <?= number_format($row['montant_total'], 0, ',', ' ') ?> F
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-paye">
                                        <?= number_format($row['montant_encaisse'], 0, ',', ' ') ?> F
                                    </td>

                                    <td class="cnp-cell-amount cnp-cell-restant">
                                        <strong><?= number_format($row['montant_restant'], 0, ',', ' ') ?> F</strong>
                                    </td>

                                    <td class="cnp-cell-statut">
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
                                    <td class="cnp-cell-amount cnp-cell-eur">
                                        <?= $sg['montant_eur'] > 0 ? number_format($sg['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-credit">
                                        <?= number_format($sg['montant_total'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-paye">
                                        <?= number_format($sg['montant_encaisse'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="cnp-cell-amount cnp-cell-restant">
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
                                        ✈️ RECAPITULATIF SECTION COLIS EXPORT (<?= $totauxExport['count'] ?> colis)
                                    </div>
                                </td>
                            </tr>
                            <tr class="cnp-export-subtotal-row">
                                <td colspan="6" class="cnp-subtotal-label">
                                    Sous-Total Général COLIS EXPORT
                                </td>
                                <td class="cnp-cell-amount cnp-cell-eur">
                                    <?= $totauxExport['montant_eur'] > 0 ? number_format($totauxExport['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                                </td>
                                <td class="cnp-cell-amount cnp-cell-credit">
                                    <?= number_format($totauxExport['montant_total'], 0, ',', ' ') ?> F
                                </td>
                                <td class="cnp-cell-amount cnp-cell-paye">
                                    <?= number_format($totauxExport['montant_encaisse'], 0, ',', ' ') ?> F
                                </td>
                                <td class="cnp-cell-amount cnp-cell-restant">
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
                            <td class="cnp-cell-amount cnp-cell-eur">
                                <?= $totaux['montant_eur'] > 0 ? number_format($totaux['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                            </td>
                            <td class="cnp-cell-amount cnp-cell-credit">
                                <?= number_format($totaux['montant_total'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="cnp-cell-amount cnp-cell-paye">
                                <?= number_format($totaux['montant_encaisse'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="cnp-cell-amount cnp-cell-restant">
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
<input type="hidden" id="csrf-token-cnp" value="<?= \App\Helpers\Csrf::generate() ?>">

<style>
.cnp-page-container {
    padding: 1.5rem;
    max-width: 1600px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
}

.cnp-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.cnp-breadcrumb {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}
.cnp-breadcrumb a {
    color: #047857;
    text-decoration: none;
    font-weight: 500;
}
.cnp-breadcrumb .sep { margin: 0 0.35rem; }

.cnp-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 0.25rem 0;
}

.cnp-subtitle {
    font-size: 0.95rem;
    color: #475569;
    margin: 0;
}

.cnp-actions-group {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn-cnp {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cnp-primary {
    background: #047857;
    color: #fff;
    box-shadow: 0 2px 4px rgba(4, 120, 87, 0.2);
}
.btn-cnp-primary:hover {
    background: #065f46;
    box-shadow: 0 4px 8px rgba(4, 120, 87, 0.3);
    color: #fff;
}

.btn-cnp-success {
    background: #0284c7;
    color: #fff;
}
.btn-cnp-success:hover {
    background: #0369a1;
    color: #fff;
}

.btn-cnp-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.btn-cnp-secondary:hover {
    background: #e2e8f0;
}

.btn-cnp-outline {
    background: transparent;
    border: 1px solid #cbd5e1;
    color: #475569;
}
.btn-cnp-outline:hover {
    background: #f8fafc;
}

/* KPIs */
.cnp-kpis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.cnp-kpi-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
}
.cnp-kpi-highlight {
    background: #fff5f5;
    border-color: #fecaca;
}

.cnp-kpi-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 0.35rem;
}
.cnp-kpi-valeur {
    font-size: 1.65rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 0.3rem;
}
.cnp-kpi-valeur small {
    font-size: 0.9rem;
    font-weight: 600;
}
.cnp-kpi-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

/* Filtres */
.cnp-filter-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
    font-weight: 600;
    color: #475569;
}

.cnp-input, .cnp-select {
    height: 38px;
    padding: 0 0.75rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: 0.875rem;
    background: #f8fafc;
    color: #1e293b;
    outline: none;
    transition: border 0.15s;
}
.cnp-input:focus, .cnp-select:focus {
    border-color: #047857;
    background: #fff;
    box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.15);
}

.cnp-filter-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Tableau */
.cnp-table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.cnp-table-responsive {
    overflow-x: auto;
}

.cnp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: left;
}

.cnp-table thead th {
    background: #0f172a;
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 0.75rem;
    border-right: 1px solid #334155;
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

.cnp-row:hover td {
    background-color: #f8fafc;
}
.cnp-row-export {
    background-color: #f0fdf4;
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
}
.cnp-group-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.cnp-group-tag {
    background: #334155;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
}
.cnp-group-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}
.cnp-group-count {
    font-size: 0.85rem;
    color: #64748b;
}

.cnp-group-summary-pills {
    display: flex;
    gap: 0.5rem;
    font-size: 0.8rem;
}
.pill-credit { background: #e2e8f0; color: #334155; padding: 0.2rem 0.5rem; border-radius: 4px; }
.pill-paye { background: #dcfce7; color: #166534; padding: 0.2rem 0.5rem; border-radius: 4px; }
.pill-restant { background: #fee2e2; color: #991b1b; padding: 0.2rem 0.5rem; border-radius: 4px; }

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
    background: #fff;
    text-transform: uppercase;
}
.cnp-group-input:focus {
    border-color: #047857;
    outline: none;
    box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.15);
}
.btn-save-group {
    background: #047857;
    color: #fff;
    border: none;
    border-radius: 4px;
    width: 26px;
    height: 28px;
    cursor: pointer;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
    opacity: 0.85;
}
.btn-save-group:hover {
    opacity: 1;
}

/* Liens */
.cnp-tracking-link {
    font-weight: 700;
    color: #047857;
    text-decoration: none;
}
.cnp-tracking-link:hover { text-decoration: underline; }

.badge-export {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.35rem;
    border-radius: 3px;
    margin-left: 0.25rem;
}

.cnp-facture-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}
.cnp-facture-link:hover { text-decoration: underline; }

.cnp-agency-pill {
    background: #f1f5f9;
    color: #334155;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    font-size: 0.78rem;
    font-weight: 500;
}

.cnp-client-line {
    font-size: 0.8rem;
    line-height: 1.25;
}
.cnp-client-role {
    color: #94a3b8;
    font-weight: 600;
    margin-right: 0.2rem;
}
.cnp-client-name {
    color: #1e293b;
}

/* Montants */
.cnp-cell-amount {
    font-variant-numeric: tabular-nums;
    font-size: 0.875rem;
}
.cnp-cell-eur {
    color: #7c3aed;
    font-weight: 600;
}
.cnp-cell-credit {
    color: #0f172a;
}
.cnp-cell-paye {
    color: #16a34a;
}
.cnp-cell-restant {
    color: #dc2626;
}

/* Statut Pills */
.cnp-status-pill {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
}
.status-emise { background: #fee2e2; color: #991b1b; }
.status-partiellement_payee { background: #fef3c7; color: #92400e; }
.status-en_retard { background: #fecaca; color: #7f1d1d; }

/* Sous-totaux & Total */
.cnp-subtotal-row td {
    background: #f8fafc;
    border-top: 1px solid #cbd5e1;
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
    color: #fff;
    padding: 0.5rem 1rem;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}
.cnp-export-subtotal-row td {
    background: #eff6ff;
    font-weight: 700;
    border-bottom: 2px solid #bfdbfe;
}

.cnp-grand-total-row td {
    background: #0f172a;
    color: #fff;
    padding: 1rem 0.75rem;
    font-size: 0.95rem;
    font-weight: 800;
    border: none;
}
.cnp-grand-total-label {
    text-align: right;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
    color: #f8fafc;
}
.badge-total-due {
    background: #dc2626;
    color: #fff;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    font-weight: 800;
}

.cnp-empty-state {
    padding: 4rem 2rem;
    text-align: center;
}
.cnp-empty-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
}
.cnp-empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: #0f172a;
}
.cnp-empty-state p {
    color: #64748b;
    max-width: 500px;
    margin: 0 auto;
}
</style>

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
                    btn.textContent = '✓';
                    btn.style.backgroundColor = '#16a34a';
                    setTimeout(() => {
                        btn.textContent = '✓';
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
