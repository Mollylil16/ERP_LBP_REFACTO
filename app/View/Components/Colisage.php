<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Colisage\ColisageIndexPage;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Colisage
{
    public static function dashboardPage(\App\View\Pages\Colisage\DashboardPage $page, array $dashboardModule): string
    {
        $header = \App\View\Components\Dashboard::header(
            $dashboardModule['label'],
            "Le module colisage orchestre la réception en agence, le groupage des manifestes, le transport et les retraits de colis.",
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $kpis = \App\View\Components\Dashboard::kpis($page->kpis);
        $overview = self::agencesOverview();
        $recentParcels = self::recentParcels($page->recentParcels);
        $recentExpeditions = self::recentExpeditions($page->recentExpeditions);
        $actions = \App\View\Components\Dashboard::actions($page->quickActions, [
            'title' => 'Raccourcis Opérationnels',
            'class' => 'finea-section-card',
        ]);

        return '<div class="finea-shell colisage-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . '<h3>Réseau des Agences Actives</h3>'
            . '<p style="color: #64748b; font-size: 0.95rem; margin-top: 0.2rem;">Suivi de l\'activité par point de vente / agence d\'expédition.</p>'
            . $overview
            . '</div>'
            . '<div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">'
            . '<div>'
            . '<h3>Derniers Colis Enregistrés</h3>'
            . $recentParcels
            . '</div>'
            . '<div>'
            . '<h3>Dernières Expéditions (Groupage)</h3>'
            . $recentExpeditions
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="rh-dashboard-side">'
            . $actions
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    public static function autresListPage(array $parcels, array $filters, ?array $pagination): string
    {
        $actionHtml = Ui::button('Nouvel envoi express', [
            'href' => 'colisage/autres/nouveau',
            'variant' => 'accent',
        ]);

        $header = Ui::pageHeader(
            'Autres Envois (Express)',
            'Suivi, saisie et édition des factures pour les envois express (DHL & Colis Rapide).',
            [
                'eyebrow' => 'Flux Express Internationaux',
                'class' => 'rh-hero-white',
                'actions' => [
                    $actionHtml,
                ],
            ]
        );

        $filterForm = self::autresFilterForm($filters);
        $listTable = self::autresListTable($parcels);

        $paginationHtml = '';
        if ($pagination && ($pagination['totalPages'] ?? 1) > 1) {
            $paginationLinks = [];
            for ($pNum = 1; $pNum <= $pagination['totalPages']; $pNum++) {
                $query = http_build_query(array_filter(
                    $filters + ['page' => $pNum],
                    static fn(mixed $val): bool => $val !== '' && $val !== 0
                ));
                $paginationLinks[] = [
                    'number' => $pNum,
                    'href' => View::url('colisage/autres?' . $query),
                    'active' => $pNum === $pagination['currentPage'],
                ];
            }
            $paginationHtml = Rh::paginationLinks($paginationLinks);
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $filterForm
            . $listTable
            . $paginationHtml
            . '</div>'
            . '</div>';
    }

    public static function autresCreatePage(array $sites, array $clients, array $products, float $eurToXofRate): string
    {
        $clientOpts = [['value' => '', 'label' => '-- Choisir un client existant --']];
        foreach ($clients as $c) {
            $clientOpts[] = ['value' => (string) $c['id'], 'label' => $c['name'] . ' (' . $c['phone'] . ')'];
        }

        $siteOpts = [['value' => '', 'label' => '-- Sélectionner l\'agence --']];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        $prodOptions = [['value' => '', 'label' => '-- Sélectionner un produit --']];
        foreach ($products as $p) {
            $prodOptions[] = [
                'value' => (string) $p['id'],
                'label' => $p['nom'] . ' (' . number_format((float) $p['prix_unitaire'], 0, ',', ' ') . ' XOF/' . $p['unite'] . ')'
            ];
        }

        $header = Ui::pageHeader(
            'Enregistrer un Envoi Express',
            'Saisie d\'une fiche de colisage pour DHL Express ou Colis Rapide inter-pays.',
            [
                'eyebrow' => 'Nouveau Colis Express — Facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Retour à la liste', ['href' => 'colisage/autres', 'variant' => 'secondary'])
                ],
            ]
        );

        $formContent = '<form method="post" action="' . View::url('colisage/autres/enregistrer') . '">'
            . '<div class="rh-form-step-card">'
            . '<div class="rh-step-badge">ÉTAPE 1</div>'
            . '<h3 class="rh-step-title">Expéditeur & Destinataire</h3>'
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">'
            . '<div>'
            . '<h4 style="margin-bottom:0.8rem; color:#1e40af;">EXPÉDITEUR</h4>'
            . Form::selectSearch('expediteur_id', $clientOpts, '', ['label' => 'Client existant'])
            . '<div style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.015); border-radius:8px; border:1px solid rgba(0,0,0,0.05);">'
            . '<small style="color:#64748b;">Ou créer un nouvel expéditeur :</small>'
            . '<div class="rh-form-grid-3" style="margin-top:0.5rem;">'
            . Form::input('expediteur_name', ['label' => 'Nom complet', 'placeholder' => 'Ex: AICHA OUATTARA'])
            . Form::input('expediteur_phone', ['label' => 'Tél. Exp.', 'placeholder' => 'Ex: 0789665421'])
            . Form::input('expediteur_email', ['label' => 'E-mail'])
            . Form::input('expediteur_address', ['label' => 'Adresse'])
            . '</div></div></div>'
            . '<div>'
            . '<h4 style="margin-bottom:0.8rem; color:#1e40af;">DESTINATAIRE</h4>'
            . Form::selectSearch('destinataire_id', $clientOpts, '', ['label' => 'Client existant'])
            . '<div style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.015); border-radius:8px; border:1px solid rgba(0,0,0,0.05);">'
            . '<small style="color:#64748b;">Ou créer un nouveau destinataire :</small>'
            . '<div class="rh-form-grid-3" style="margin-top:0.5rem;">'
            . Form::input('destinataire_name', ['label' => 'Nom complet', 'placeholder' => 'Ex: KOUAO YVES'])
            . Form::input('destinataire_phone', ['label' => 'Tél. Dest.', 'placeholder' => 'Ex: +33 178255886'])
            . Form::input('destinataire_email', ['label' => 'E-mail'])
            . Form::input('destinataire_address', ['label' => 'Adresse'])
            . '</div></div></div></div></div>'
            . '<div class="rh-form-step-card">'
            . '<div class="rh-step-badge">ÉTAPE 2</div>'
            . '<h3 class="rh-step-title">Service Express & Trajet</h3>'
            . '<div class="rh-form-grid-3">'
            . Form::select('type_expediteur', [
                ['value' => 'dhl', 'label' => ' DHL Express'],
                ['value' => 'colis_rapide_export', 'label' => ' Colis Rapide Export'],
                ['value' => 'colis_rapide_import', 'label' => ' Colis Rapide Import'],
            ], 'dhl', ['label' => 'Service de transport', 'required' => true, 'id' => 'service_selector'])
            . '<div id="trajet_container" style="display:none;">'
            . Form::select('trajet', [
                ['value' => '', 'label' => '-- Sélectionner le trajet --'],
                ['value' => 'CIV_SEN', 'label' => 'CIV ➔ SEN'],
                ['value' => 'SEN_CIV', 'label' => 'SEN ➔ CIV'],
                ['value' => 'CIV_FR', 'label' => 'CIV ➔ FR'],
                ['value' => 'FR_CIV', 'label' => 'FR ➔ CIV'],
                ['value' => 'SEN_FR', 'label' => 'SEN ➔ FR'],
                ['value' => 'FR_SEN', 'label' => 'FR ➔ SEN'],
            ], '', ['label' => 'Trajet inter-pays'])
            . '</div>'
            . Form::selectSearch('agence_depart_id', $siteOpts, '', ['label' => 'Agence de départ', 'required' => true])
            . Form::selectSearch('agence_arrivee_id', $siteOpts, '', ['label' => 'DESTINATION (agence d\'arrivée)', 'required' => true])
            . Form::input('nombre_colis', ['label' => 'Nombre total de colis', 'type' => 'number', 'min' => 1, 'value' => '1', 'required' => true])
            . Form::input('poids_total', ['label' => 'Poids total (kg)', 'type' => 'number', 'step' => '0.01', 'required' => true])
            . Form::select('devise', [
                ['value' => 'XOF', 'label' => 'Franc CFA (XOF / FCFA)'],
                ['value' => 'EUR', 'label' => 'Euro (EUR)'],
                ['value' => 'USD', 'label' => 'US Dollar (USD)'],
            ], 'XOF', ['label' => 'Devise'])
            . Form::input('valeur_declaree', ['label' => 'Valeur déclarée (assurance/douane)', 'type' => 'number', 'step' => '1'])
            . '</div></div>'
            . '<div class="rh-form-step-card">'
            . '<div class="rh-step-badge">ÉTAPE 3</div>'
            . '<h3 class="rh-step-title">Détail des marchandises</h3>'
            . '<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">Conforme au format facture LB-CI : N°, Nbre Colis, Description, Emballage, Qté Emb., Poids (kg), Prix/Kg, Total</p>'
            . self::marchandisesInputTable($prodOptions)
            . '</div>'
            . '<div style="margin-top:2rem; display:flex; gap:1rem; justify-content:flex-end; padding-bottom:3rem;">'
            . Ui::button('Annuler', ['href' => 'colisage/autres', 'variant' => 'secondary'])
            . Ui::button('Enregistrer & Générer la facture', ['type' => 'submit', 'variant' => 'accent', 'style' => 'font-size:1rem; padding:0.8rem 2rem;'])
            . '</div></form>';

        $script = '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    const clientsData = ' . json_encode($clients) . ';'
            . '    const productsData = ' . json_encode($products) . ';'
            . '    const rows = document.querySelectorAll(\'.finea-table tbody tr\');'
            . '    const sousTotalEl = document.getElementById(\'sous_total\');'
            . '    const totalFcfaEl = document.getElementById(\'montant_total_fcfa\');'
            . '    const totalEurEl = document.getElementById(\'montant_total_eur\');'
            . '    const inputValeurDeclaree = document.querySelector(\'input[name="valeur_declaree"]\');'
            . '    const serviceSelector = document.getElementById(\'service_selector\');'
            . '    const trajetContainer = document.getElementById(\'trajet_container\');'
            . '    const trajetSelect = document.querySelector(\'select[name="trajet"]\');'
            . '    const eurToXofRate = ' . (float) $eurToXofRate . ';'
            . '    function toggleTrajet() {'
            . '        const val = serviceSelector.value;'
            . '        if (val === \'colis_rapide_export\' || val === \'colis_rapide_import\') {'
            . '            trajetContainer.style.display = \'block\';'
            . '            trajetSelect.required = true;'
            . '        } else {'
            . '            trajetContainer.style.display = \'none\';'
            . '            trajetSelect.required = false;'
            . '            trajetSelect.value = \'\';'
            . '        }'
            . '    }'
            . '    if (serviceSelector) {'
            . '        serviceSelector.addEventListener(\'change\', toggleTrajet);'
            . '        toggleTrajet();'
            . '    }'
            . '    const inputClientExp = document.querySelector(\'select[name="expediteur_id"]\');'
            . '    if (inputClientExp) {'
            . '        inputClientExp.addEventListener(\'change\', function() {'
            . '            const client = clientsData.find(c => c.id == this.value);'
            . '            if (client) {'
            . '                document.querySelector(\'input[name="expediteur_name"]\').value = client.name || \'\';'
            . '                document.querySelector(\'input[name="expediteur_phone"]\').value = client.phone || \'\';'
            . '                document.querySelector(\'input[name="expediteur_email"]\').value = client.email || \'\';'
            . '                document.querySelector(\'input[name="expediteur_address"]\').value = client.adresse || \'\';'
            . '            }'
            . '        });'
            . '    }'
            . '    const inputClientDest = document.querySelector(\'select[name="destinataire_id"]\');'
            . '    if (inputClientDest) {'
            . '        inputClientDest.addEventListener(\'change\', function() {'
            . '            const client = clientsData.find(c => c.id == this.value);'
            . '            if (client) {'
            . '                document.querySelector(\'input[name="destinataire_name"]\').value = client.name || \'\';'
            . '                document.querySelector(\'input[name="destinataire_phone"]\').value = client.phone || \'\';'
            . '                document.querySelector(\'input[name="destinataire_email"]\').value = client.email || \'\';'
            . '                document.querySelector(\'input[name="destinataire_address"]\').value = client.adresse || \'\';'
            . '            }'
            . '        });'
            . '    }'
            . '    function calculateTotals() {'
            . '        let subtotal = 0;'
            . '        let totalWeight = 0;'
            . '        let totalCount = 0;'
            . '        rows.forEach(row => {'
            . '            const qtyInput = row.querySelector(\'input[name="m_qty[]"]\');'
            . '            const weightInput = row.querySelector(\'input[name="m_weight[]"]\');'
            . '            const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '            const totalInput = row.querySelector(\'.js-item-total\');'
            . '            if (qtyInput && weightInput && priceInput && totalInput) {'
            . '                const qty = parseInt(qtyInput.value) || 0;'
            . '                const weight = parseFloat(weightInput.value) || 0;'
            . '                const price = parseFloat(priceInput.value) || 0;'
            . '                const total = weight * price * qty;'
            . '                subtotal += total;'
            . '                totalWeight += weight * qty;'
            . '                totalCount += qty;'
            . '                totalInput.innerText = Math.round(total).toLocaleString() + \' XOF\';'
            . '            }'
            . '        });'
            . '        if (sousTotalEl) sousTotalEl.innerText = Math.round(subtotal).toLocaleString() + \' XOF\';'
            . '        if (totalFcfaEl) totalFcfaEl.innerText = Math.round(subtotal).toLocaleString() + \' XOF\';'
            . '        if (totalEurEl) totalEurEl.innerText = (subtotal / eurToXofRate).toFixed(2).toLocaleString() + \' €\';'
            . '        const inputPoidsTotal = document.querySelector(\'input[name="poids_total"]\');'
            . '        if (inputPoidsTotal) inputPoidsTotal.value = totalWeight.toFixed(2);'
            . '        const inputNombreColis = document.querySelector(\'input[name="nombre_colis"]\');'
            . '        if (inputNombreColis) inputNombreColis.value = totalCount;'
            . '        if (inputValeurDeclaree && (!inputValeurDeclaree.value || inputValeurDeclaree.dataset.auto === \'true\')) {'
            . '            inputValeurDeclaree.value = Math.round(subtotal);'
            . '            inputValeurDeclaree.dataset.auto = \'true\';'
            . '        }'
            . '    }'
            . '    rows.forEach(row => {'
            . '        const prodSelect = row.querySelector(\'select[name="m_product_id[]"]\');'
            . '        if (prodSelect) {'
            . '            prodSelect.addEventListener(\'change\', function() {'
            . '                const selectedOptions = Array.from(this.selectedOptions).filter(opt => opt.value !== "");'
            . '                if (selectedOptions.length > 0) {'
            . '                    let firstPrice = null;'
            . '                    let validValues = [];'
            . '                    let hasPriceMismatch = false;'
            . '                    selectedOptions.forEach(opt => {'
            . '                        const product = productsData.find(p => p.id == opt.value);'
            . '                        if (product) {'
            . '                            const price = Math.round(parseFloat(product.prix_unitaire) || 0);'
            . '                            if (firstPrice === null) {'
            . '                                firstPrice = price;'
            . '                                validValues.push(opt.value);'
            . '                            } else if (firstPrice === price) {'
            . '                                validValues.push(opt.value);'
            . '                            } else {'
            . '                                hasPriceMismatch = true;'
            . '                            }'
            . '                        }'
            . '                    });'
            . '                    if (hasPriceMismatch) {'
            . '                        alert("Attention : Tous les produits sélectionnés sur une même ligne doivent avoir le même prix unitaire !");'
            . '                        Array.from(this.options).forEach(opt => {'
            . '                            if (opt.value && !validValues.includes(opt.value)) {'
            . '                                opt.selected = false;'
            . '                            }'
            . '                        });'
            . '                        this.dispatchEvent(new Event("change", { bubbles: true }));'
            . '                        return;'
            . '                    }'
            . '                    const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '                    if (priceInput && firstPrice !== null) {'
            . '                        priceInput.value = firstPrice;'
            . '                        calculateTotals();'
            . '                    }'
            . '                } else {'
            . '                    const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '                    if (priceInput) {'
            . '                        priceInput.value = \'0.00\';'
            . '                        calculateTotals();'
            . '                    }'
            . '                }'
            . '            });'
            . '        }'
            . '        const inputs = row.querySelectorAll(\'input\');'
            . '        inputs.forEach(input => {'
            . '            input.addEventListener(\'input\', calculateTotals);'
            . '        });'
            . '    });'
            . '    if (inputValeurDeclaree) {'
            . '        inputValeurDeclaree.addEventListener(\'input\', function() {'
            . '            this.dataset.auto = \'false\';'
            . '        });'
            . '    }'
            . '    const form = document.querySelector(\'form\');'
            . '    if (form) {'
            . '        form.addEventListener(\'submit\', function(e) {'
            . '            const submitBtn = form.querySelector(\'button[type="submit"]\');'
            . '            if (submitBtn) {'
            . '                if (submitBtn.dataset.submitted === \'true\') {'
            . '                    e.preventDefault();'
            . '                    return;'
            . '                }'
            . '                submitBtn.dataset.submitted = \'true\';'
            . '                submitBtn.disabled = true;'
            . '                submitBtn.innerHTML = \'<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Enregistrement en cours...</span>\';'
            . '            }'
            . '        });'
            . '    }'
            . '    calculateTotals();'
            . '});'
            . '</script>'
            . '<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $formContent
            . '</div></div>'
            . $script;
    }

    public static function groupageIndexPage(array $expeditions): string
    {
        $header = Ui::pageHeader(
            'Groupage & Manifestes',
            'Planification des voyages de groupage et affectation des colis aux conteneurs ou palettes.',
            [
                'eyebrow' => 'Logistique & Fret',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Planifier un voyage', [
                        'href' => 'colisage/groupage/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        );

        $list = self::groupageListTable($expeditions);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $list
            . '</div>'
            . '</div>';
    }

    public static function groupageCreatePage(array $sites, string $defaultDepart): string
    {
        $siteOpts = [['value' => '', 'label' => '-- Sélectionner l\'agence --']];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        $header = Ui::pageHeader(
            'Planifier un Voyage de Groupage',
            'Enregistrement d\'un nouveau manifeste d\'expédition de fret.',
            [
                'eyebrow' => 'Nouveau Manifeste',
                'class' => 'rh-hero-white',
            ]
        );

        $formContent = '<form method="post" action="' . View::url('colisage/groupage/enregistrer') . '" class="finea-section-card" style="max-width: 800px; margin-top: 1.5rem;">'
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">'
            . Form::select('type_transport', [
                ['value' => 'AÉRIEN', 'label' => '✈️ AÉRIEN (Fret aérien rapide)'],
                ['value' => 'MARITIME', 'label' => '🚢 MARITIME (Fret maritime conteneur)'],
                ['value' => 'TERRESTRE', 'label' => 'Terrestre (Route / Flotte livreurs)'],
            ], 'AÉRIEN', ['label' => 'Type de transport', 'required' => true])
            . '<div></div>'
            . Form::selectSearch('agence_depart_id', $siteOpts, '', ['label' => 'Agence de départ', 'required' => true])
            . Form::selectSearch('agence_arrivee_id', $siteOpts, '', ['label' => 'Agence de destination', 'required' => true])
            . Form::input('date_depart_prevue', [
                'label' => 'Date & Heure de départ prévue',
                'type' => 'datetime-local',
                'required' => true,
                'value' => $defaultDepart,
            ])
            . Form::input('date_arrivee_estimee', [
                'label' => 'Date & Heure d\'arrivée estimée',
                'type' => 'datetime-local',
                'required' => true,
            ])
            . '</div>'
            . '<div style="margin-top: 2rem; display:flex; gap:1rem; justify-content:flex-end;">'
            . Ui::button('Annuler', ['href' => 'colisage/groupage', 'variant' => 'secondary'])
            . Ui::button('Créer le manifeste', ['type' => 'submit', 'variant' => 'accent'])
            . '</div>'
            . '</form>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $formContent
            . '</div>'
            . '</div>';
    }

    public static function groupageShowPage(array $exp, array $availableParcels): string
    {
        $badgeTone = match($exp['statut']) {
            'ARRIVÉ' => 'success',
            'EN_TRANSIT' => 'primary',
            'BROUILLON' => 'warning',
            default => 'neutral'
        };

        $assignedParcels = $exp['parcels'] ?? [];

        $parcelOpts = [['value' => '', 'label' => '-- Sélectionner un colis à ajouter --']];
        foreach ($availableParcels as $ap) {
            $parcelOpts[] = [
                'value' => (string) $ap['id'],
                'label' => $ap['numero_tracking'] . ' - ' . $ap['expediteur_name'] . ' (' . $ap['poids_total'] . ' kg)'
            ];
        }

        $header = Ui::pageHeader(
            'Manifeste ' . $exp['reference'],
            'Gestion du groupage et du voyage d\'expédition.',
            [
                'eyebrow' => 'Groupage Fret',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::badge($exp['statut'], $badgeTone, ['class' => 'finea-badge--large']),
                    Ui::button('Retour à la liste', [
                        'href' => 'colisage/groupage',
                        'variant' => 'secondary'
                    ])
                ]
            ]
        );

        $addFormSection = '';
        if ($exp['statut'] === 'BROUILLON') {
            $addForm = '';
            if (empty($availableParcels)) {
                $addForm = '<p style="color: #64748b; font-size: 0.95rem;">Aucun colis en agence n\'est actuellement en attente d\'expédition pour ce trajet.</p>';
            } else {
                $addForm = '<form method="post" action="' . View::url('colisage/groupage/' . $exp['id'] . '/colis') . '" style="display:flex; align-items:flex-end; gap:1rem;" class="js-protect-form">'
                    . '<div style="flex-grow:1;">'
                    . Form::selectSearch('colis_id', $parcelOpts, '', ['label' => 'Colis disponible à l\'agence de départ (' . View::e($exp['agence_depart_name']) . ')'])
                    . '</div>'
                    . Ui::button('Affecter au groupage', ['type' => 'submit', 'variant' => 'primary', 'style' => 'height: 42px;', 'data-label' => 'Affecter au groupage'])
                    . '</form>';
            }
            $addFormSection = Ui::section('Scanner & Charger des colis dans ce manifeste', $addForm);
        }

        $detail = self::groupageDetail($exp);
        $parcelsTable = self::groupageParcelsTable($assignedParcels);

        $script = '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    document.querySelectorAll(\'.js-protect-form\').forEach(function(form) {'
            . '        form.addEventListener(\'submit\', function(e) {'
            . '            const btn = form.querySelector(\'button[type="submit"]\');'
            . '            if (btn) {'
            . '                if (btn.dataset.submitted === \'true\') { e.preventDefault(); return; }'
            . '                btn.dataset.submitted = \'true\';'
            . '                btn.disabled = true;'
            . '                btn.innerHTML = \'<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Traitement en cours...</span>\';'
            . '            }'
            . '        });'
            . '    });'
            . '});'
            . '</script>'
            . '<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns: 1fr; gap: 1.5rem;">'
            . $detail
            . $addFormSection
            . $parcelsTable
            . '</div>'
            . '</div>'
            . '</div>'
            . $script;
    }

    public static function synthesePage(array $dailyRevenue, array $agencyStats, array $unpaidStats, array $transitExpeditions): string
    {
        $header = Ui::pageHeader(
            'Synthèse de l\'Exploitation',
            'Vision consolidée en temps réel de l\'activité opérationnelle et financière du réseau d\'agences.',
            [
                'eyebrow' => 'Exploitation & Suivi Réseau',
                'class' => 'rh-hero-white'
            ]
        );

        $cards = self::syntheseCards($dailyRevenue, count($transitExpeditions));
        $agencyStatsTable = self::agencyStatsTable($agencyStats);
        $unpaidTable = self::unpaidTable($unpaidStats);
        $transitTable = self::transitTable($transitExpeditions);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $cards
            . '<div style="margin-top:2rem;">'
            . '<h3>Performances Commerciales des Agences</h3>'
            . $agencyStatsTable
            . '</div>'
            . '<div style="margin-top:2rem; display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">'
            . '<div>'
            . '<h3>Créances clients (Factures non payées)</h3>'
            . $unpaidTable
            . '</div>'
            . '<div>'
            . '<h3>Expéditions inter-agences en Transit</h3>'
            . $transitTable
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    public static function trackingPage(array $expeditions, array $recentGps): string
    {
        $header = Ui::pageHeader(
            'Suivi Cartographique & Logistique',
            'Saisie des coordonnées GPS et suivi des expéditions inter-agences en cours de route.',
            [
                'eyebrow' => 'Suivi de transit (Fret)',
                'class' => 'rh-hero-white'
            ]
        );

        $trackingForm = self::trackingForm($expeditions);
        $trackingMapMockup = self::trackingMapMockup();
        $gpsEventsTable = self::gpsEventsTable($recentGps);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">'
            . $trackingForm
            . $trackingMapMockup
            . '</div>'
            . $gpsEventsTable
            . '</div>'
            . '</div>'
            . '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    const form = document.getElementById(\'gps-form\');'
            . '    const select = document.getElementById(\'exp_select\');'
            . '    if (form && select) {'
            . '        const updateAction = () => {'
            . '            const val = select.value;'
            . '            form.action = \'' . View::url('colisage/exploitation/tracking/') . '\' + val;'
            . '        };'
            . '        select.addEventListener(\'change\', updateAction);'
            . '        updateAction();'
            . '    }'
            . '});'
            . '</script>';
    }

    public static function creditsPage(array $credits, array $balances, array $sites): string
    {
        $siteOpts = [];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        $header = Ui::pageHeader(
            'Compensation Financière Inter-Agences',
            'Suivi des dettes croisées et règlement des flux financiers réciproques du réseau.',
            [
                'eyebrow' => 'Grand Livre Logistique & Trésorerie',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Déclarer un crédit', [
                        'href' => '#',
                        'variant' => 'accent',
                        'onclick' => 'document.getElementById("modal-credit").style.display="flex"; return false;'
                    ])
                ]
            ]
        );

        $balancesTable = self::balancesTable($balances);
        $creditsTable = self::creditsTable($credits);
        $creditModal = self::creditModal($siteOpts);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $balancesTable
            . $creditsTable
            . '</div>'
            . '</div>'
            . $creditModal;
    }

    public static function fournituresPage(array $demandes, array $sites): string
    {
        $siteOpts = [];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        $header = Ui::pageHeader(
            'Fournitures de Bureau & Logistique Interne',
            'Suivi, contrôle budgétaire et validation des demandes de fournitures du réseau d\'agences.',
            [
                'eyebrow' => 'Ressources Internes',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Nouvelle demande (Simulation)', [
                        'href' => '#',
                        'variant' => 'accent',
                        'onclick' => 'document.getElementById("modal-demande").style.display="flex"; return false;'
                    ])
                ]
            ]
        );

        $table = self::fournituresTable($demandes);
        $modal = self::fournitureModal($siteOpts);
        $refusal = self::refusalModal();

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $table
            . '</div>'
            . '</div>'
            . $modal
            . $refusal;
    }

    public static function documentsPage(array $manifests, array $parcels): string
    {
        $header = Ui::pageHeader(
            'Gestion Documentaire & Impressions',
            'Édition des manifestes de fret, étiquettes colis et documents de transport LBP.',
            [
                'eyebrow' => 'Documents Logistiques',
                'class' => 'rh-hero-white'
            ]
        );

        $manifestsTable = self::manifestsTable($manifests);
        $parcelsDocTable = self::parcelsDocTable($parcels);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">'
            . $manifestsTable
            . $parcelsDocTable
            . '</div>'
            . '</div>'
            . '</div>';
    }

    public static function reportingPage(array $tonnageData, array $caData, array $delaiData, string $dateDebut, string $dateFin): string
    {
        $header = Ui::pageHeader(
            'Reporting & Analyses Opérationnelles',
            'Indicateurs clés de performance fret, volumes de groupage et statistiques financières.',
            [
                'eyebrow' => 'Décisionnel & Analytics',
                'class' => 'rh-hero-white'
            ]
        );

        $dateFilter = self::dateFilter($dateDebut, $dateFin);
        $tonnageTable = self::tonnageTable($tonnageData);
        $revenueTable = self::revenueTable($caData);
        $delaysTable = self::delaysTable($delaiData);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $dateFilter
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">'
            . $tonnageTable
            . $revenueTable
            . '</div>'
            . $delaysTable
            . '</div>'
            . '</div>';
    }

    public static function settingsPage(float $tauxChangeEur, array $devisesRates, array $allSettings): string
    {
        $header = Ui::pageHeader(
            'Paramétrage du module Colisage',
            'Configuration des taux de change, préférences logistiques et paramètres opérationnels.',
            [
                'eyebrow' => 'Configuration & Préférences',
                'class' => 'rh-hero-white',
            ]
        );

        $ratesTable = self::settingsRatesTable($devisesRates);

        $section1Content = '<form method="post" action="' . View::url('colisage/settings/enregistrer') . '" class="js-protect-form">'
            . '<input type="hidden" name="section" value="taux_change">'
            . '<div style="background: rgba(30,58,95,0.03); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem;">'
            . '<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">'
            . '<span style="background:#1e3a5f; color:#fff; padding:0.4rem 0.8rem; border-radius:6px; font-weight:700; font-size:0.85rem;">EUR → XOF</span>'
            . '<span style="color:#64748b; font-size:0.85rem;">Parité de conversion</span>'
            . '</div>'
            . Form::input('taux_change_eur', [
                'label' => 'Valeur de 1 € en Francs CFA (XOF)',
                'type' => 'number',
                'step' => '0.000001',
                'min' => '0.01',
                'value' => number_format($tauxChangeEur, 6, '.', ''),
                'required' => true,
            ])
            . '<p style="margin-top:0.75rem; font-size:0.8rem; color:#94a3b8;">'
            . 'Parité officielle BCEAO : 655,957 FCFA. Dernière mise à jour : '
            . '<strong>' . View::e($allSettings['taux_change_eur_updated'] ?? date('d/m/Y')) . '</strong>'
            . '</p></div>'
            . $ratesTable
            . '<div style="display:flex; justify-content:flex-end;">'
            . Ui::button('Enregistrer le taux de change', [
                'type' => 'submit',
                'variant' => 'accent',
                'data-label' => 'Enregistrer le taux',
            ])
            . '</div></form>';

        $section2Content = '<form method="post" action="' . View::url('colisage/settings/enregistrer') . '" class="js-protect-form">'
            . '<input type="hidden" name="section" value="preferences">'
            . '<div style="background: rgba(30,58,95,0.03); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem;">'
            . '<h4 style="margin-bottom: 1rem; color: #1e3a5f;">Règles Logistiques & Sécurité</h4>'
            . Form::select('pref_delai_transit_max', [
                ['value' => '24', 'label' => '24 heures (Fret Express)'],
                ['value' => '48', 'label' => '48 heures (Standard standardisé)'],
                ['value' => '72', 'label' => '72 heures (Tolérance normale)'],
                ['value' => '168', 'label' => '1 week (Fret maritime)'],
            ], (string) ($allSettings['pref_delai_transit_max'] ?? '48'), ['label' => 'Délai de transit max autorisé'])
            . '<div style="margin-top:1rem;">'
            . Form::select('pref_double_validation_groupage', [
                ['value' => '1', 'label' => 'Activée (Validation agence départ + chef d\'exploitation)'],
                ['value' => '0', 'label' => 'Désactivée (Le chargeur valide seul le départ)'],
            ], (string) ($allSettings['pref_double_validation_groupage'] ?? '1'), ['label' => 'Sécurité de Groupage'])
            . '</div>'
            . '<div style="margin-top:1rem;">'
            . Form::select('pref_alerte_poids_colis', [
                ['value' => '30', 'label' => '30 kg (Seuil de pénibilité standard)'],
                ['value' => '50', 'label' => '50 kg (Colis lourds avec surtaxe)'],
                ['value' => '100', 'label' => '100 kg (Palettes obligatoires)'],
            ], (string) ($allSettings['pref_alerte_poids_colis'] ?? '30'), ['label' => 'Alerte poids colis individuel'])
            . '</div></div>'
            . '<div style="display:flex; justify-content:flex-end;">'
            . Ui::button('Enregistrer les préférences', [
                'type' => 'submit',
                'variant' => 'accent',
                'data-label' => 'Enregistrer les préférences',
            ])
            . '</div></form>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . Ui::section('Gestion des Devises & Taux', $section1Content)
            . Ui::section('Préférences Logistiques & Alertes', $section2Content)
            . '</div>'
            . '</div>'
            . '</div>'
            . '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    document.querySelectorAll(\'.js-protect-form\').forEach(function(form) {'
            . '        form.addEventListener(\'submit\', function(e) {'
            . '            const btn = form.querySelector(\'button[type="submit"]\');'
            . '            if (btn) {'
            . '                if (btn.dataset.submitted === \'true\') { e.preventDefault(); return; }'
            . '                btn.dataset.submitted = \'true\';'
            . '                btn.disabled = true;'
            . '                btn.innerHTML = \'<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Enregistrement...</span>\';'
            . '            }'
            . '        });'
            . '    });'
            . '});'
            . '</script>'
            . '<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>';
    }

    public static function recentParcels(array $rows): string
    {
        $html = '<section class="finea-section-card" style="margin-top: 1rem;">'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Tracking</th><th>Expéditeur</th><th>Statut</th></tr></thead><tbody>';
            
        if ($rows === []) {
            $html .= '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">Aucun colis enregistré récemment.</td></tr>';
        } else {
            foreach ($rows as $p) {
                $html .= '<tr>'
                    . '<td><strong><a href="' . View::url('colisage/parcels/' . $p['id']) . '">' . View::e($p['numero_tracking']) . '</a></strong></td>'
                    . '<td>' . View::e($p['expediteur_name']) . '</td>'
                    . '<td>' . Ui::badge($p['statut'], $p['status_tone']) . '</td>'
                    . '</tr>';
            }
        }
        $html .= '</tbody></table></div></section>';
        return $html;
    }

    public static function recentExpeditions(array $rows): string
    {
        $html = '<section class="finea-section-card" style="margin-top: 1rem;">'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Référence</th><th>Destination</th><th>Statut</th></tr></thead><tbody>';
            
        if ($rows === []) {
            $html .= '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">Aucun manifeste planifié.</td></tr>';
        } else {
            foreach ($rows as $e) {
                $html .= '<tr>'
                    . '<td><strong><a href="' . View::url('colisage/groupage/' . $e['id']) . '">' . View::e($e['reference']) . '</a></strong></td>'
                    . '<td>' . View::e($e['agence_arrivee_name']) . '</td>'
                    . '<td>' . Ui::badge($e['statut'], $e['status_tone']) . '</td>'
                    . '</tr>';
            }
        }
        $html .= '</tbody></table></div></section>';
        return $html;
    }

    public static function agencesOverview(): string
    {
        return '<div class="finea-section-card" style="margin-top: 1rem; padding: 1.5rem;">'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">'
            . '<div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">'
            . '<strong>Europe</strong><p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Agence France (Paris)</p></div>'
            . '<div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">'
            . '<strong>Afrique de l\'Ouest</strong><p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Agence Sénégal (Dakar)</p></div>'
            . '<div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">'
            . '<strong>Zone Aéroportuaire</strong><p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Aéroport Port Bouët Fret</p></div>'
            . '<div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">'
            . '<strong>Côte d\'Ivoire (Abidjan)</strong><p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Siege Abidjan, Abobo Dokui, Adjamé Pharmacie Latin</p></div>'
            . '</div></div>';
    }

    public static function listPage(ColisageIndexPage $page): string
    {
        $actionHtml = Ui::button('Enregistrer un colis', [
            'href' => 'colisage/parcels/nouveau',
            'variant' => 'accent',
        ]);

        $header = Ui::pageHeader(
            'Gestion des Colis',
            'Saisie, suivi et groupage des colis des clients.',
            [
                'eyebrow' => 'Opérations de Colisage',
                'class' => 'rh-hero-white',
                'actions' => [
                    $actionHtml,
                ],
            ]
        );

        // Filters form
        $q = Form::input('q', [
            'label' => 'Recherche',
            'value' => (string) ($page->filters['q'] ?? ''),
            'placeholder' => 'N° Tracking, expéditeur, destinataire',
        ]);

        $status = Form::selectSearch('statut', [
            ['value' => '', 'label' => 'Tous les statuts'],
            ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
            ['value' => 'EN_PRÉPARATION', 'label' => 'En préparation'],
            ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
            ['value' => 'ARRIVÉ', 'label' => 'Arrivé'],
            ['value' => 'LIVRÉ', 'label' => 'Livré'],
            ['value' => 'RETIRÉ', 'label' => 'Retiré'],
        ], $page->filters['statut'] ?? '', ['label' => 'Statut']);

        $type = Form::selectSearch('type_expediteur', [
            ['value' => '', 'label' => 'Toutes les catégories'],
            ['value' => 'export_aerien', 'label' => 'Export Aérien'],
            ['value' => 'export_maritime', 'label' => 'Export Maritime'],
            ['value' => 'import_aerien', 'label' => 'Import Aérien'],
            ['value' => 'import_maritime', 'label' => 'Import Maritime'],
        ], $page->filters['type_expediteur'] ?? '', ['label' => 'Catégorie Fret']);

        $filterGrid = '<div class="rh-personnel-filter-grid">' . $q . $status . $type . '</div>';

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Rechercher'
            . '</button>';

        $resetBtn = '<a href="' . View::url('colisage/parcels') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>';
        $form = '<form method="get" action="' . View::url('colisage/parcels') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . '</form>';

        // Table
        $tableHtml = '';
        if ($page->parcels === []) {
            $tableHtml = Ui::emptyState(
                'Aucun colis trouvé',
                'Aucune fiche ne correspond aux critères sélectionnés.'
            );
        } else {
            $rows = '';
            foreach ($page->parcels as $p) {
                $typeLabel = match($p['type_expediteur']) {
                    'export_aerien' => '✈️ Export Aérien',
                    'export_maritime' => '🚢 Export Maritime',
                    'import_aerien' => '✈️ Import Aérien',
                    'import_maritime' => '🚢 Import Maritime',
                    default => $p['type_expediteur']
                };

                $badgeTone = match($p['statut']) {
                    'RETIRÉ', 'LIVRÉ' => 'success',
                    'RÉCEPTIONNÉ' => 'info',
                    'EN_PRÉPARATION' => 'warning',
                    'EN_TRANSIT' => 'primary',
                    default => 'secondary'
                };

                $badge = Ui::badge($p['statut'], $badgeTone);

                $actionsStr = '';
                foreach ($p['actions'] as $act) {
                    $actionsStr .= Ui::button($act['label'], [
                        'href' => $act['href'],
                        'variant' => $act['variant'] ?? 'secondary',
                        'class' => 'finea-button-sm'
                    ]);
                }

                $rows .= '<tr>'
                    . '<td><strong>' . View::e($p['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($p['expediteur_name']) . '</td>'
                    . '<td>' . View::e($p['destinataire_name']) . '</td>'
                    . '<td><small>' . View::e($typeLabel) . '</small></td>'
                    . '<td>' . View::e((string) $p['poids_total']) . ' kg</td>'
                    . '<td>' . View::e(number_format((float) $p['valeur_declaree'], 0, ',', ' ')) . ' ' . View::e($p['devise']) . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsStr . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>N° Tracking</th>'
                . '<th>Expéditeur</th>'
                . '<th>Destinataire</th>'
                . '<th>Catégorie</th>'
                . '<th>Poids</th>'
                . '<th>Valeur Décl.</th>'
                . '<th>Statut</th>'
                . '<th>Actions</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        $pagination = Rh::paginationLinks($page->pagination);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . $tableHtml
            . '</div>'
            . $pagination
            . '</div></div>';
    }

    public static function createPage(array $sites, array $clients, array $products = [], float $tauxChangeEur = 655.957): string
    {
        $header = Ui::pageHeader(
            'Enregistrer un Colis',
            'Saisie de la fiche de colisage et des marchandises.',
            [
                'eyebrow' => 'Nouveau Colis',
                'class' => 'rh-hero-white',
            ]
        );

        // Prep options for clients
        $clientOpts = [['value' => '', 'label' => '-- Choisir un client existant --']];
        foreach ($clients as $c) {
            $clientOpts[] = ['value' => (string) $c['id'], 'label' => $c['name'] . ' (' . $c['phone'] . ')'];
        }

        $siteOpts = [['value' => '', 'label' => '-- Sélectionner l\'agence --']];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        // Section Client/Expéditeur
        $expChoice = Form::select('expediteur_id', $clientOpts, '', ['label' => 'Sélectionner l\'expéditeur']);
        $expQuick = '<div class="finea-section-card-nested" style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.02); border-radius:8px;">'
            . '<h4>Ou créer rapidement un nouvel expéditeur :</h4>'
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:0.5rem;">'
            . Form::input('expediteur_name', ['label' => 'Nom Complet'])
            . Form::input('expediteur_phone', ['label' => 'Téléphone'])
            . Form::input('expediteur_email', ['label' => 'E-mail'])
            . Form::input('expediteur_address', ['label' => 'Adresse'])
            . '</div>'
            . '</div>';

        // Section Destinataire
        $destChoice = Form::select('destinataire_id', $clientOpts, '', ['label' => 'Sélectionner le destinataire']);
        $destQuick = '<div class="finea-section-card-nested" style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.02); border-radius:8px;">'
            . '<h4>Ou créer rapidement un nouveau destinataire :</h4>'
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:0.5rem;">'
            . Form::input('destinataire_name', ['label' => 'Nom Complet'])
            . Form::input('destinataire_phone', ['label' => 'Téléphone'])
            . Form::input('destinataire_email', ['label' => 'E-mail'])
            . Form::input('destinataire_address', ['label' => 'Adresse'])
            . '</div>'
            . '</div>';

        // Details du Colis
        $fretOpts = [
            ['value' => 'export_aerien', 'label' => '✈️ Export Aérien'],
            ['value' => 'export_maritime', 'label' => '🚢 Export Maritime'],
            ['value' => 'import_aerien', 'label' => '✈️ Import Aérien'],
            ['value' => 'import_maritime', 'label' => '🚢 Import Maritime'],
        ];
        $typeExp = Form::select('type_expediteur', $fretOpts, 'export_aerien', ['label' => 'Catégorie de Fret']);
        $weight = Form::input('poids_total', ['label' => 'Poids total (kg)', 'type' => 'number', 'step' => '0.01']);
        $valeur = Form::input('valeur_declaree', ['label' => 'Valeur déclarée', 'type' => 'number', 'step' => '1']);
        $devise = Form::select('devise', [
            ['value' => 'XOF', 'label' => 'Franc CFA (XOF)'],
            ['value' => 'EUR', 'label' => 'Euro (EUR)'],
            ['value' => 'USD', 'label' => 'US Dollar (USD)'],
        ], 'XOF', ['label' => 'Devise']);

        $depAgency = Form::select('agence_depart_id', $siteOpts, '', ['label' => 'Agence de départ']);
        $arrAgency = Form::select('agence_arrivee_id', $siteOpts, '', ['label' => 'Agence d\'arrivée prévue']);

        $colisGrid = '<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">'
            . $typeExp . $weight . $valeur
            . '</div>'
            . '<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-top:1rem;">'
            . $devise . $depAgency . $arrAgency
            . '</div>';

        // Prep options for products dropdown (multi-select needs no default -- option)
        $prodOptions = [];
        foreach ($products as $p) {
            $prodOptions[] = [
                'value' => (string) $p['id'],
                'label' => $p['nom'] . ' (' . number_format((float) $p['prix_unitaire'], 0, ',', ' ') . ' XOF/' . $p['unite'] . ')'
            ];
        }

        // Marchandises list
        $marchandisesHtml = '<div style="margin-top: 1.5rem;">'
            . '<h3>Marchandises contenues dans le colis</h3>'
            . '<div class="finea-table-wrapper" style="margin-top:0.5rem;">'
            . '<table class="finea-table" style="table-layout: auto;" id="marchandises-table">'
            . '<thead><tr style="background:#1e3a5f; color:#fff;">'
            . '<th style="width:3%; min-width:30px;">N°</th>'
            . '<th style="width:7%; min-width:80px;">Nbre Colis</th>'
            . '<th style="width:35%; min-width:320px;">Description</th>'
            . '<th style="width:12%; min-width:110px;">Emballage</th>'
            . '<th style="width:7%; min-width:80px;">Qté Emb.</th>'
            . '<th style="width:11%; min-width:105px;">Poids (kg)</th>'
            . '<th style="width:11%; min-width:110px;">Prix / Kg</th>'
            . '<th style="width:14%; min-width:120px;">Total</th>'
            . '</tr></thead>'
            . '<tbody id="marchandises-tbody">';

        for ($i = 0; $i < 5; $i++) {
            $selectHtml = Form::rawSelect('m_product_id_' . $i . '[]', $prodOptions, '', [
                'id' => 'm_product_id_' . $i,
                'multiple' => 'multiple',
                'data-finea-select-search' => '1',
                'class' => 'finea-native-select finea-select-search-source',
            ]);

            $customNameInput = Form::rawInput('m_custom_name[]', '', ['placeholder' => 'Ou saisir un nom...']);
            $customPriceInput = Form::rawInput('m_custom_price[]', '', ['type' => 'number', 'step' => '0.01', 'placeholder' => 'Prix unit.']);

            $marchandisesHtml .= '<tr>'
                . '<td style="text-align:center; font-weight:600;" class="row-num">' . ($i + 1) . '</td>'
                . '<td>' . Form::rawInput('m_nbre_colis[]', '1', ['type' => 'number', 'min' => '1']) . '</td>'
                . '<td>'
                . $selectHtml
                . '<div style="margin-top:0.4rem; display:flex; gap:0.4rem;">'
                . $customNameInput
                . $customPriceInput
                . '</div>'
                . '</td>'
                . '<td>' . Form::rawInput('m_emballage[]', '', ['placeholder' => 'Carton, Sac...']) . '</td>'
                . '<td>' . Form::rawInput('m_qte_emballage[]', '1', ['type' => 'number', 'min' => '1']) . '</td>'
                . '<td>' . Form::rawInput('m_weight[]', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) . '</td>'
                . '<td>' . Form::rawInput('m_prix_kg[]', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) . '</td>'
                . '<td style="background:rgba(0,0,0,0.02); text-align:right; font-weight:600;"><span class="ligne-total">0 FCFA</span></td>'
                . '</tr>';
        }

        $marchandisesHtml .= '</tbody>'
            . '<tfoot>'
            . '<tr><td colspan="7" style="text-align:right; font-weight:600;">SOUS-TOTAL</td><td style="text-align:right; font-weight:600;" id="sous_total">0 FCFA</td></tr>'
            . '<tr style="background:#1e3a5f; color:#fff;"><td colspan="7" style="background:#1e3a5f !important; text-align:right; font-weight:700; font-size:1.1rem; color:#ffffff !important;">MONTANT TOTAL</td>'
            . '<td style="background:#1e3a5f !important; text-align:right; font-weight:700; font-size:1.1rem; color:#ffffff !important;"><span id="montant_total_fcfa" style="color:#ffffff !important;">0 FCFA</span><br><small id="montant_total_eur" style="color:rgba(255,255,255,0.85) !important;">≈ 0.00 €</small></td></tr>'
            . '</tfoot></table></div>'
            . '<button type="button" id="add-row-btn" class="finea-button finea-button--secondary" style="margin-top: 1rem;">+ Ajouter une ligne</button>'
            . '</div>';

        $formContent = '<form method="post" action="' . View::url('colisage/parcels/enregistrer') . '">'
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">'
            . Ui::section('Informations Expéditeur', $expChoice . $expQuick)
            . Ui::section('Informations Destinataire', $destChoice . $destQuick)
            . '</div>'
            . '<div style="margin-top:2rem;">'
            . Ui::section('Détails de l\'expédition', $colisGrid . $marchandisesHtml)
            . '</div>'
            . '<div style="margin-top: 2rem; display:flex; gap:1rem; justify-content:flex-end;">'
            . Ui::button('Annuler', ['href' => 'colisage/parcels', 'variant' => 'secondary'])
            . '<button type="submit" class="finea-button finea-button--accent">Enregistrer le colis</button>'
            . '</div>'
            . '</form>';

        $script = '<script>'
            . 'document.addEventListener("DOMContentLoaded", function() {'
            . '    const clientsData = ' . json_encode($clients) . ';'
            . '    const productsData = ' . json_encode($products) . ';'
            . '    const tbody = document.getElementById("marchandises-tbody");'
            . '    const sousTotalEl = document.getElementById("sous_total");'
            . '    const totalFcfaEl = document.getElementById("montant_total_fcfa");'
            . '    const totalEurEl = document.getElementById("montant_total_eur");'
            . '    const inputValeurDeclaree = document.querySelector(\'input[name="valeur_declaree"]\');'
            . '    const tauxChangeEur = ' . json_encode($tauxChangeEur) . ';'
            . '    const expSelect = document.querySelector(\'select[name="expediteur_id"]\');'
            . '    const destSelect = document.querySelector(\'select[name="destinataire_id"]\');'
            . '    if (expSelect) {'
            . '        expSelect.addEventListener("change", function() {'
            . '            const client = clientsData.find(c => c.id == this.value);'
            . '            if (client) {'
            . '                document.querySelector(\'input[name="expediteur_name"]\').value = client.name || "";'
            . '                document.querySelector(\'input[name="expediteur_phone"]\').value = client.phone || "";'
            . '                document.querySelector(\'input[name="expediteur_email"]\').value = client.email || "";'
            . '                document.querySelector(\'input[name="expediteur_address"]\').value = client.address || "";'
            . '            }'
            . '        });'
            . '    }'
            . '    if (destSelect) {'
            . '        destSelect.addEventListener("change", function() {'
            . '            const client = clientsData.find(c => c.id == this.value);'
            . '            if (client) {'
            . '                document.querySelector(\'input[name="destinataire_name"]\').value = client.name || "";'
            . '                document.querySelector(\'input[name="destinataire_phone"]\').value = client.phone || "";'
            . '                document.querySelector(\'input[name="destinataire_email"]\').value = client.email || "";'
            . '                document.querySelector(\'input[name="destinataire_address"]\').value = client.address || "";'
            . '            }'
            . '        });'
            . '    }'
            . '    function calculateTotals() {'
            . '        let grandTotal = 0;'
            . '        const rows = tbody.querySelectorAll("tr");'
            . '        rows.forEach(row => {'
            . '            const nbreColis = parseFloat(row.querySelector(\'input[name="m_nbre_colis[]"]\').value) || 0;'
            . '            const weight = parseFloat(row.querySelector(\'input[name="m_weight[]"]\').value) || 0;'
            . '            const prixKg = parseFloat(row.querySelector(\'input[name="m_prix_kg[]"]\').value) || 0;'
            . '            const lineTotal = nbreColis * weight * prixKg;'
            . '            grandTotal += lineTotal;'
            . '            const totalSpan = row.querySelector(".ligne-total");'
            . '            if (totalSpan) {'
            . '                totalSpan.textContent = new Intl.NumberFormat("fr-FR").format(Math.round(lineTotal)) + " FCFA";'
            . '            }'
            . '        });'
            . '        const formattedGrandTotal = new Intl.NumberFormat("fr-FR").format(Math.round(grandTotal)) + " FCFA";'
            . '        if (sousTotalEl) sousTotalEl.textContent = formattedGrandTotal;'
            . '        if (totalFcfaEl) totalFcfaEl.textContent = formattedGrandTotal;'
            . '        const grandTotalEur = grandTotal / tauxChangeEur;'
            . '        if (totalEurEl) {'
            . '            totalEurEl.textContent = "≈ " + new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(grandTotalEur) + " €";'
            . '        }'
            . '        if (inputValeurDeclaree && grandTotal > 0 && (!inputValeurDeclaree.value || inputValeurDeclaree.value === "0" || inputValeurDeclaree.dataset.auto === "true")) {'
            . '            inputValeurDeclaree.value = Math.round(grandTotal);'
            . '            inputValeurDeclaree.dataset.auto = "true";'
            . '        }'
            . '    }'
            . '    tbody.addEventListener("input", calculateTotals);'
            . '    tbody.addEventListener("change", function(e) {'
            . '        if (e.target && e.target.name && e.target.name.startsWith("m_product_id_")) {'
            . '            const row = e.target.closest("tr");'
            . '            const selectedOptions = Array.from(e.target.selectedOptions).filter(opt => opt.value !== "");'
            . '            if (selectedOptions.length > 0) {'
            . '                let firstPrice = null;'
            . '                let validValues = [];'
            . '                let hasPriceMismatch = false;'
            . '                selectedOptions.forEach(opt => {'
            . '                    const product = productsData.find(p => p.id == opt.value);'
            . '                    if (product) {'
            . '                        const price = Math.round(parseFloat(product.prix_unitaire) || 0);'
            . '                        if (firstPrice === null) {'
            . '                            firstPrice = price;'
            . '                            validValues.push(opt.value);'
            . '                        } else if (firstPrice === price) {'
            . '                            validValues.push(opt.value);'
            . '                        } else {'
            . '                            hasPriceMismatch = true;'
            . '                        }'
            . '                    }'
            . '                });'
            . '                if (hasPriceMismatch) {'
            . '                    alert("Attention : Tous les produits sélectionnés sur une même ligne doivent avoir le même prix unitaire !");'
            . '                    Array.from(e.target.options).forEach(opt => {'
            . '                        if (opt.value && !validValues.includes(opt.value)) {'
            . '                            opt.selected = false;'
            . '                        }'
            . '                    });'
            . '                    e.target.dispatchEvent(new Event("change", { bubbles: true }));'
            . '                    return;'
            . '                }'
            . '                const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '                if (priceInput && firstPrice !== null) {'
            . '                    priceInput.value = firstPrice;'
            . '                    calculateTotals();'
            . '                }'
            . '            } else {'
            . '                const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '                if (priceInput) {'
            . '                    priceInput.value = "0.00";'
            . '                    calculateTotals();'
            . '                }'
            . '            }'
            . '        }'
            . '    });'
            . '    if (inputValeurDeclaree) {'
            . '        inputValeurDeclaree.addEventListener("input", function() {'
            . '            this.dataset.auto = "false";'
            . '        });'
            . '    }'
            . '    let rowIndex = 5;'
            . '    const addRowBtn = document.getElementById("add-row-btn");'
            . '    if (addRowBtn) {'
            . '        addRowBtn.addEventListener("click", function() {'
            . '            const tr = document.createElement("tr");'
            . '            let optionsHtml = "";'
            . '            productsData.forEach(p => {'
            . '                const label = p.nom + " (" + new Intl.NumberFormat("fr-FR").format(p.prix_unitaire) + " XOF/" + p.unite + ")";'
            . '                optionsHtml += \'<option value="\' + p.id + \'">\' + label + \'</option>\';'
            . '            });'
            . '            tr.innerHTML = \'<td style="text-align:center; font-weight:600;" class="row-num">\' + (rowIndex + 1) + \'</td>\''
            . '                + \'<td><input class="finea-input" type="number" name="m_nbre_colis[]" value="1" min="1"></td>\''
            . '                + \'<td>\''
            . '                + \'<select class="finea-native-select finea-select-search-source" name="m_product_id_\' + rowIndex + \'[]" id="m_product_id_\' + rowIndex + \'" multiple="multiple" data-finea-select-search="1">\''
            . '                + optionsHtml'
            . '                + \'</select>\''
            . '                + \'<div style="margin-top:0.4rem; display:flex; gap:0.4rem;">\''
            . '                + \'<input class="finea-input" name="m_custom_name[]" placeholder="Ou saisir un nom...">\''
            . '                + \'<input class="finea-input" name="m_custom_price[]" type="number" step="0.01" placeholder="Prix unit.">\''
            . '                + \'</div>\''
            . '                + \'</td>\''
            . '                + \'<td><input class="finea-input" name="m_emballage[]" placeholder="Carton, Sac..."></td>\''
            . '                + \'<td><input class="finea-input" type="number" name="m_qte_emballage[]" value="1" min="1"></td>\''
            . '                + \'<td><input class="finea-input" type="number" name="m_weight[]" value="0.00" step="0.01" min="0"></td>\''
            . '                + \'<td><input class="finea-input" type="number" name="m_prix_kg[]" value="0.00" step="0.01" min="0"></td>\''
            . '                + \'<td style="background:rgba(0,0,0,0.02); text-align:right; font-weight:600;"><span class="ligne-total">0 FCFA</span></td>\';'
            . '            tbody.appendChild(tr);'
            . '            if (window.FineaComponents && typeof window.FineaComponents.init === "function") {'
            . '                window.FineaComponents.init();'
            . '            }'
            . '            rowIndex++;'
            . '            calculateTotals();'
            . '        });'
            . '    }'
            . '    const form = document.querySelector("form");'
            . '    if (form) {'
            . '        form.addEventListener("submit", function(e) {'
            . '            const submitBtn = form.querySelector(\'button[type="submit"]\');'
            . '            if (submitBtn) {'
            . '                if (submitBtn.dataset.submitted === "true") {'
            . '                    e.preventDefault();'
            . '                    return;'
            . '                }'
            . '                submitBtn.dataset.submitted = "true";'
            . '                submitBtn.disabled = true;'
            . '                submitBtn.innerHTML = \'<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Enregistrement en cours...</span>\';'
            . '            }'
            . '        });'
            . '    }'
            . '    calculateTotals();'
            . '});'
            . '</script>'
            . '<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $formContent
            . $script
            . '</div></div>';
    }

    public static function showPage(array $colis): string
    {
        $badgeTone = match($colis['statut']) {
            'RETIRÉ', 'LIVRÉ' => 'success',
            'RÉCEPTIONNÉ' => 'info',
            'EN_PRÉPARATION' => 'warning',
            'EN_TRANSIT' => 'primary',
            default => 'secondary'
        };

        $header = Ui::pageHeader(
            'Colis ' . $colis['numero_tracking'],
            'Visualisation et suivi opérationnel du colis.',
            [
                'eyebrow' => 'Suivi de Colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::badge($colis['statut'], $badgeTone),
                    Ui::button('Retour à la liste', ['href' => 'colisage/parcels', 'variant' => 'secondary'])
                ]
            ]
        );

        $colisInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>N° Tracking :</strong> ' . View::e($colis['numero_tracking']) . '</p>'
            . '<p><strong>Poids total :</strong> ' . View::e((string) $colis['poids_total']) . ' kg</p>'
            . '<p><strong>Valeur déclarée :</strong> ' . View::e(number_format((float) $colis['valeur_declaree'], 0, ',', ' ')) . ' ' . View::e($colis['devise']) . '</p>'
            . '<p><strong>Catégorie Fret :</strong> ' . View::e(str_replace('_', ' ', $colis['type_expediteur'])) . '</p>'
            . '</div>'
            . '<div>'
            . '<p><strong>Agence départ :</strong> ' . View::e($colis['agence_depart_name'] ?? 'Non spécifiée') . '</p>'
            . '<p><strong>Agence d\'arrivée :</strong> ' . View::e($colis['agence_arrivee_name'] ?? 'Non spécifiée') . '</p>'
            . '<p><strong>Date d\'enregistrement :</strong> ' . View::e($colis['created_at']) . '</p>'
            . '</div>'
            . '</div>';

        $actorsInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<h4>Expéditeur</h4>'
            . '<p><strong>Nom :</strong> ' . View::e($colis['expediteur_name']) . '</p>'
            . '<p><strong>Téléphone :</strong> ' . View::e($colis['expediteur_phone'] ?? 'Non spécifié') . '</p>'
            . '</div>'
            . '<div>'
            . '<h4>Destinataire</h4>'
            . '<p><strong>Nom :</strong> ' . View::e($colis['destinataire_name']) . '</p>'
            . '<p><strong>Téléphone :</strong> ' . View::e($colis['destinataire_phone'] ?? 'Non spécifié') . '</p>'
            . '</div>'
            . '</div>';

        $goodsRows = '';
        foreach (($colis['marchandises'] ?? []) as $m) {
            $goodsRows .= '<tr>'
                . '<td>' . View::e($m['description']) . '</td>'
                . '<td>' . View::e((string) $m['quantite']) . '</td>'
                . '<td>' . View::e((string) $m['poids_unitaire']) . ' kg</td>'
                . '</tr>';
        }

        $goodsTable = '<table class="finea-table" style="margin-top:0.5rem;">'
            . '<thead><tr><th>Description</th><th>Quantité</th><th>Poids Unitaire</th></tr></thead>'
            . '<tbody>' . ($goodsRows ?: '<tr><td colspan="3">Aucune marchandise répertoriée.</td></tr>') . '</tbody>'
            . '</table>';

        // Withdraw form if not withdrawn yet
        $withdrawForm = '';
        if ($colis['statut'] !== 'RETIRÉ' && $colis['statut'] !== 'LIVRÉ') {
            $withdrawForm = '<form method="post" action="' . View::url('colisage/parcels/' . $colis['id'] . '/retirer') . '" style="margin-top:2rem;">'
                . '<h3>Signaler le retrait du colis</h3>'
                . '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:0.5rem;">'
                . Form::input('recup_nom', ['label' => 'Nom du récupérateur', 'required' => true])
                . Form::input('recup_cni', ['label' => 'Numéro de CNI / Identité', 'required' => true])
                . Form::input('recup_telephone', ['label' => 'Téléphone récupérateur', 'required' => true])
                . '</div>'
                . '<div style="margin-top: 1rem; display:flex; justify-content:flex-end;">'
                . '<button type="submit" class="finea-button finea-button--accent">Valider le retrait (Livré)</button>'
                . '</div>'
                . '</form>';
        } else {
            $withdrawForm = '<div class="finea-section-card-nested" style="margin-top:2rem; padding:1.5rem; background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.2); border-radius:8px;">'
                . '<h3>Colis Retiré / Livré</h3>'
                . '<p style="margin-top:0.5rem;"><strong>Récupérateur :</strong> ' . View::e($colis['recup_nom']) . '</p>'
                . '<p><strong>CNI :</strong> ' . View::e($colis['recup_cni']) . '</p>'
                . '<p><strong>Téléphone :</strong> ' . View::e($colis['recup_telephone']) . '</p>'
                . '<p><strong>Date & Heure exactes :</strong> ' . View::e($colis['recup_date_heure']) . '</p>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">'
            . Ui::section('Informations Générales', $colisInfo)
            . Ui::section('Contacts Expédition', $actorsInfo)
            . Ui::section('Marchandises', $goodsTable)
            . $withdrawForm
            . '</div>'
            . '</div></div>';
    }

    // ─── CREDITS / COMPENSATION ──────────────────────────────────────

    public static function balancesTable(array $balances): string
    {
        $rows = '';
        if ($balances === []) {
            $rows = '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#94a3b8;">Toutes les agences sont à l\'équilibre. Aucune dette inter-agence.</td></tr>';
        } else {
            foreach ($balances as $b) {
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($b['agence_creanciere']) . '</strong></td>'
                    . '<td><span style="color:#dc2626;"> owes to ➔ </span><strong>' . View::e($b['agence_debitrice']) . '</strong></td>'
                    . '<td style="text-align:right; font-weight:700; color:#dc2626;">' . number_format((float) $b['total_montant'], 0, ',', '.') . ' ' . View::e($b['devise']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card" style="margin-bottom:2rem;">'
            . '<h3 class="rh-step-title" style="color:var(--lbp-blue-light); border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem; margin-bottom:1rem;">Consolidation des Dettes Réciproques</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Agence Créancière</th><th>Agence Débitrice</th><th style="text-align:right;">Solde Dû</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function creditsTable(array $credits): string
    {
        $rows = '';
        if ($credits === []) {
            $rows = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#94a3b8;">Aucune transaction de crédit ou débit inter-agence enregistrée.</td></tr>';
        } else {
            foreach ($credits as $c) {
                $tone = $c['statut'] === 'REGLE' ? 'success' : 'warning';
                $refHtml = $c['reference_justificatif']
                    ? Ui::badge('Ref: ' . $c['reference_justificatif'], 'neutral') . ' '
                    : '';
                $actionHtml = '';
                if ($c['statut'] === 'NON_REGLE') {
                    $actionHtml = '<form method="post" action="' . View::url('colisage/exploitation/credits/' . $c['id'] . '/regler') . '" style="display:inline;" onsubmit="return confirm(\'Confirmer le règlement physique de cette dette ?\');">'
                        . '<button type="submit" class="finea-button finea-button--accent finea-button-sm">Marquer réglé</button></form>';
                } else {
                    $actionHtml = '<span style="color:var(--lbp-success); font-weight:600; font-size:0.85rem;">✓ Compensé</span>';
                }

                $rows .= '<tr>'
                    . '<td><small>' . View::e($c['created_at']) . '</small></td>'
                    . '<td><strong>' . View::e($c['numero_tracking'] ?? '—') . '</strong></td>'
                    . '<td>' . View::e($c['agence_creanciere_name']) . '</td>'
                    . '<td>' . View::e($c['agence_debitrice_name']) . '</td>'
                    . '<td>' . $refHtml . View::e($c['description'] ?: 'Généré par le système') . '</td>'
                    . '<td style="text-align:right; font-weight:600;">' . number_format((float) $c['montant'], 0, ',', '.') . ' ' . View::e($c['devise']) . '</td>'
                    . '<td style="text-align:center;">' . Ui::badge($c['statut'], $tone) . '</td>'
                    . '<td style="text-align:right;">' . $actionHtml . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title">Registre des Transactions de Compensation</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#1e3a5f; color:#fff;"><th>Date</th><th>N° Colis</th><th>Créancier (Départ)</th><th>Débiteur (Arrivée)</th><th>Justificatif / Note</th><th style="text-align:right;">Montant</th><th style="text-align:center;">Statut</th><th style="text-align:right;">Actions</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function creditModal(array $siteOpts): string
    {
        $fields = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">'
            . Form::select('agence_creanciere_id', $siteOpts, '', ['label' => 'Agence Créancière (Bénéficiaire)', 'required' => true])
            . Form::select('agence_debitrice_id', $siteOpts, '', ['label' => 'Agence Débitrice (Payeur)', 'required' => true])
            . '</div>'
            . '<div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">'
            . Form::input('montant', ['label' => 'Montant', 'type' => 'number', 'step' => '0.01', 'placeholder' => 'Ex: 25000', 'required' => true])
            . Form::select('devise', [['value' => 'XOF', 'label' => 'FCFA (XOF)'], ['value' => 'EUR', 'label' => 'Euro (EUR)']], 'XOF', ['label' => 'Devise', 'required' => true])
            . '</div>'
            . Form::input('reference_justificatif', ['label' => 'N° Pièce / Tracking / WhatsApp Ref (Optionnel)', 'placeholder' => 'Ex: LBCI-5464, Photo-7865'])
            . Form::input('description', ['label' => 'Note explicative', 'placeholder' => 'Ex: Dépôt cash par l\'expéditeur en France, remboursement colis express...', 'required' => true]);

        return Ui::modal('modal-credit', 'Déclarer un Crédit Inter-Agence', $fields, View::url('colisage/exploitation/credits/declarer'));
    }

    // ─── REPORTING ───────────────────────────────────────────────────

    public static function dateFilter(string $dateDebut, string $dateFin): string
    {
        return '<div style="background: white; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: flex-end; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">'
            . '<form method="get" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">'
            . Form::input('date_debut', ['label' => 'Date de début', 'type' => 'date', 'value' => $dateDebut, 'required' => true])
            . Form::input('date_fin', ['label' => 'Date de fin', 'type' => 'date', 'value' => $dateFin, 'required' => true])
            . '<div>' . Ui::button('Appliquer le filtre', ['type' => 'submit', 'variant' => 'accent'])
            . ' ' . Ui::button('Réinitialiser', ['href' => '?', 'variant' => 'secondary'])
            . '</div></form></div>';
    }

    public static function tonnageTable(array $data): string
    {
        $rows = '';
        if ($data === []) {
            $rows = '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucune donnée de volume.</td></tr>';
        } else {
            foreach ($data as $t) {
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($t['trajet'] ?: 'Non spécifié') . '</strong></td>'
                    . '<td style="text-align:center;">' . Ui::badge((string) (int) $t['total_colis'], 'neutral') . '</td>'
                    . '<td style="text-align:right; font-weight:600;">' . number_format((float) $t['total_poids'], 2, ',', '.') . ' kg</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title" style="color:var(--lbp-blue-light);">Tonnage & Volumes par Trajet</h3>'
            . '<p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Visualisation du poids total expédié selon les trajets logistiques.</p>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Trajet</th><th style="text-align:center;">Nombre Colis</th><th style="text-align:right;">Poids Cumulé</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function revenueTable(array $data): string
    {
        $rows = '';
        if ($data === []) {
            $rows = '<tr><td colspan="2" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun chiffre d\'affaires enregistré.</td></tr>';
        } else {
            foreach ($data as $c) {
                $channel = match ($c['type_expediteur']) {
                    'export_aerien' => '✈️ Export Aérien',
                    'export_maritime' => '🚢 Export Maritime',
                    'import_aerien' => '✈️ Import Aérien',
                    'import_maritime' => '🚢 Import Maritime',
                    'colis_rapide_export' => '⚡ Colis Rapide Export',
                    'colis_rapide_import' => '⚡ Colis Rapide Import',
                    'dhl' => '📦 DHL Express',
                    default => $c['type_expediteur']
                };
                $rows .= '<tr>'
                    . '<td>' . View::e($channel) . '</td>'
                    . '<td style="text-align:right; font-weight:700; color:var(--lbp-blue-deep);">' . number_format((float) $c['total_ca'], 0, ',', '.') . ' ' . View::e($c['devise']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title" style="color:var(--lbp-gold);">Chiffre d\'Affaires par Canal d\'Envoi</h3>'
            . '<p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Répartition financière entre le fret de groupage classique et l\'express.</p>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Mode / Canal</th><th style="text-align:right;">Total Collecté</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function delaysTable(array $data): string
    {
        $rows = '';
        if ($data === []) {
            $rows = '<tr><td colspan="3" style="text-align:center; padding:2rem; color:#94a3b8;">Données insuffisantes (les colis livrés doivent avoir une date de retrait renseignée).</td></tr>';
        } else {
            foreach ($data as $d) {
                $label = $d['avg_days'] <= 7 ? 'Excellent' : 'Normal';
                $tone = $d['avg_days'] <= 7 ? 'success' : 'warning';
                $rows .= '<tr>'
                    . '<td><strong>Axe Inter-Agences #' . (int) $d['agence_depart_id'] . ' ➔ #' . (int) $d['agence_arrivee_id'] . '</strong></td>'
                    . '<td style="text-align:center; font-weight:700; color:var(--lbp-blue-light);">' . number_format((float) $d['avg_days'], 1) . ' jours</td>'
                    . '<td>' . Ui::badge($label, $tone) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title">Délais Logistiques Moyens (Transit Time)</h3>'
            . '<p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Temps d\'acheminement moyen mesuré entre la prise en charge et le retrait par le destinataire.</p>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Axe / Corridor</th><th style="text-align:center;">Délai Moyen (Jours)</th><th>Qualité SLA</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    // ─── SYNTHESE EXPLOITATION ───────────────────────────────────────

    public static function syntheseCards(array $dailyRevenue, int $transitCount): string
    {
        $cards = '';
        if ($dailyRevenue === []) {
            $cards .= '<div class="finea-section-card" style="padding:1.5rem; border-left:4px solid var(--lbp-gold);">'
                . '<small style="color:var(--lbp-text-muted); font-weight:600; text-transform:uppercase;">Recettes du jour (XOF)</small>'
                . '<h2 style="font-size:2rem; font-weight:800; color:var(--lbp-blue-deep); margin-top:0.5rem;">0 XOF</h2>'
                . '<p style="font-size:0.85rem; color:var(--lbp-text-muted); margin-top:0.25rem;">Aucun encaissement aujourd\'hui.</p></div>';
        } else {
            foreach ($dailyRevenue as $rev) {
                $cards .= '<div class="finea-section-card" style="padding:1.5rem; border-left:4px solid var(--lbp-blue-light);">'
                    . '<small style="color:var(--lbp-text-muted); font-weight:600; text-transform:uppercase;">Recettes du jour (' . View::e($rev['devise']) . ')</small>'
                    . '<h2 style="font-size:2rem; font-weight:800; color:var(--lbp-blue-deep); margin-top:0.5rem;">' . number_format((float) $rev['total'], 0, ',', '.') . ' ' . View::e($rev['devise']) . '</h2>'
                    . '<p style="font-size:0.85rem; color:var(--lbp-text-muted); margin-top:0.25rem;">Total brut collecté sur le réseau.</p></div>';
            }
        }
        $cards .= '<div class="finea-section-card" style="padding:1.5rem; border-left:4px solid var(--lbp-success);">'
            . '<small style="color:var(--lbp-text-muted); font-weight:600; text-transform:uppercase;">Flux en transit</small>'
            . '<h2 style="font-size:2rem; font-weight:800; color:var(--lbp-blue-deep); margin-top:0.5rem;">' . $transitCount . '</h2>'
            . '<p style="font-size:0.85rem; color:var(--lbp-text-muted); margin-top:0.25rem;">Manifestes logistiques inter-pays actifs.</p></div>';

        return '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.5rem; margin-bottom:2rem;">' . $cards . '</div>';
    }

    public static function agencyStatsTable(array $stats): string
    {
        $rows = '';
        foreach ($stats as $stat) {
            $rows .= '<tr>'
                . '<td><strong>' . View::e($stat['site_name']) . '</strong></td>'
                . '<td style="text-align:center;">' . Ui::badge((string) (int) $stat['parcels_count'], 'info') . '</td>'
                . '<td style="text-align:right; font-weight:600;">' . number_format((float) ($stat['total_xof'] ?? 0), 0, ',', '.') . ' XOF</td>'
                . '<td style="text-align:right; font-weight:600;">' . number_format((float) ($stat['total_eur'] ?? 0), 2, ',', '.') . ' €</td>'
                . '</tr>';
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title" style="margin-bottom:1rem; border:none; padding-bottom:0;">Performance des caisses (Aujourd\'hui)</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Agence / Site</th><th style="text-align:center;">Colis Reçus</th><th style="text-align:right;">Recettes XOF</th><th style="text-align:right;">Recettes EUR</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function unpaidTable(array $stats): string
    {
        $rows = '';
        if ($stats === []) {
            $rows = '<tr><td colspan="2" style="text-align:center; color:#94a3b8; padding:1.5rem;">Aucune facture en attente.</td></tr>';
        } else {
            foreach ($stats as $u) {
                $rows .= '<tr>'
                    . '<td>' . View::e($u['site_name']) . '</td>'
                    . '<td style="text-align:right; font-weight:600; color:#b45309;">' . number_format((float) $u['unpaid_total'], 0, ',', '.') . ' ' . View::e($u['devise']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title" style="margin-bottom:1rem; border:none; padding-bottom:0; color:#b45309;">⚠️ Factures Impayées / En cours</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Agence</th><th style="text-align:right;">Montant Dû</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function transitTable(array $expeditions): string
    {
        $rows = '';
        if ($expeditions === []) {
            $rows = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">Aucune expédition en transit actuellement.</td></tr>';
        } else {
            foreach ($expeditions as $e) {
                $mode = match ($e['type_transport']) {
                    'AÉRIEN' => '✈️ Aérien',
                    'MARITIME' => '🚢 Maritime',
                    'TERRESTRE' => '🚛 Route',
                    default => $e['type_transport']
                };
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($e['reference']) . '</strong></td>'
                    . '<td>' . View::e($mode) . '</td>'
                    . '<td>' . View::e($e['agence_depart_name']) . '</td>'
                    . '<td>' . View::e($e['agence_arrivee_name']) . '</td>'
                    . '<td>' . Ui::badge((int) $e['colis_count'] . ' colis', 'neutral') . '</td>'
                    . '<td>' . Ui::badge($e['statut'], 'primary') . '</td>'
                    . '<td>' . Ui::button('Mettre à jour GPS', ['href' => 'colisage/exploitation/tracking', 'variant' => 'accent', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title" style="margin-bottom:1.2rem;">Suivi Opérationnel des Expéditions en Transit</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#1e3a5f; color:#fff;"><th>Référence</th><th>Mode</th><th>Départ</th><th>Destination</th><th>Nombre Colis</th><th>Statut</th><th>Localisation</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    // ─── DOCUMENTS ───────────────────────────────────────────────────

    public static function manifestsTable(array $manifests): string
    {
        $rows = '';
        if ($manifests === []) {
            $rows = '<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun manifeste disponible.</td></tr>';
        } else {
            foreach ($manifests as $m) {
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($m['reference']) . '</strong></td>'
                    . '<td><small>' . View::e($m['agence_depart_name']) . ' ➔ ' . View::e($m['agence_arrivee_name']) . '</small></td>'
                    . '<td style="text-align:center;">' . Ui::badge((string) (int) $m['colis_count'], 'neutral') . '</td>'
                    . '<td style="text-align:right;">' . Ui::button('Visualiser / Éditer', ['href' => 'colisage/groupage/' . $m['id'], 'variant' => 'secondary', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title">Manifestes & Packing Lists</h3>'
            . '<p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Générez le manifeste de fret récapitulatif pour les autorités douanières et logistiques.</p>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Référence</th><th>Trajet</th><th style="text-align:center;">Colis</th><th style="text-align:right;">Actions</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function parcelsDocTable(array $parcels): string
    {
        $rows = '';
        if ($parcels === []) {
            $rows = '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun colis enregistré.</td></tr>';
        } else {
            foreach ($parcels as $p) {
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($p['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($p['expediteur_name']) . '</td>'
                    . '<td style="text-align:right; white-space:nowrap;">'
                    . Ui::button('Facture', ['href' => 'colisage/parcels/' . $p['id'] . '/facture', 'variant' => 'accent', 'class' => 'finea-button-sm', 'target' => '_blank'])
                    . ' <button type="button" class="finea-button finea-button--secondary finea-button-sm" onclick="alert(\'Impression de l\\\'étiquette de tracking ' . View::e($p['numero_tracking']) . '...\');">Étiquette</button>'
                    . '</td></tr>';
            }
        }

        return '<div class="finea-section-card">'
            . '<h3 class="rh-step-title">Étiquettes & Factures Colis</h3>'
            . '<p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Imprimez les justificatifs individuels ou les étiquettes de tracking avec code-barres.</p>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>N° Tracking</th><th>Client</th><th style="text-align:right;">Actions</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    // ─── TRACKING GPS ────────────────────────────────────────────────

    public static function trackingForm(array $expeditions): string
    {
        if ($expeditions === []) {
            return '<div class="finea-section-card"><h3 class="rh-step-title">Mettre à jour la position GPS</h3>'
                . '<div style="padding:2rem; text-align:center; color:#94a3b8;">Aucune expédition en cours de transit à mettre à jour.</div></div>';
        }

        $expOpts = array_map(fn($e) => [
            'value' => $e['id'],
            'label' => $e['reference'] . ' (' . $e['agence_depart_name'] . ' ➔ ' . $e['agence_arrivee_name'] . ')'
        ], $expeditions);

        $fields = Form::select('expeditions_list', $expOpts, '', ['label' => 'Sélectionner l\'expédition', 'required' => true, 'id' => 'exp_select'])
            . Form::input('etape', ['label' => 'Étape logistique / Ville actuelle', 'placeholder' => 'Ex: Escale Bobo-Dioulasso, Douane Noé...', 'required' => true])
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">'
            . Form::input('latitude', ['label' => 'Latitude', 'placeholder' => 'Ex: 5.3599', 'type' => 'number', 'step' => '0.0000001', 'required' => true])
            . Form::input('longitude', ['label' => 'Longitude', 'placeholder' => 'Ex: -4.0083', 'type' => 'number', 'step' => '0.0000001', 'required' => true])
            . '</div>';

        return '<div class="finea-section-card"><h3 class="rh-step-title">Mettre à jour la position GPS</h3>'
            . '<form method="post" action="" id="gps-form"><div class="rh-form-grid-3" style="grid-template-columns:1fr; gap:1rem;">'
            . $fields . '</div>'
            . '<div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">'
            . Ui::button('Enregistrer la position', ['type' => 'submit', 'variant' => 'accent'])
            . '</div></form></div>';
    }

    public static function trackingMapMockup(): string
    {
        return '<div class="finea-section-card" style="background:#0f172a; color:#fff; display:flex; flex-direction:column; justify-content:center; align-items:center; border:none; border-radius:14px; position:relative; overflow:hidden;">'
            . '<div style="position:absolute; inset:0; opacity:0.1; background-image: radial-gradient(#38bdf8 1px, transparent 0); background-size: 24px 24px;"></div>'
            . '<div style="z-index:2; text-align:center; padding:2rem;">'
            . '<svg style="width:64px; height:64px; color:#38bdf8; margin-bottom:1rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>'
            . '<h3 style="font-weight:700; font-size:1.2rem; margin-bottom:0.5rem;">Visualisation Logistique Cartographique</h3>'
            . '<p style="color:#94a3b8; font-size:0.85rem; max-width:320px; margin:0 auto;">Les coordonnées GPS soumises alimentent le widget de suivi client en temps réel pour tous les colis associés à la cargaison.</p>'
            . '</div></div>';
    }

    public static function gpsEventsTable(array $recentGps): string
    {
        $rows = '';
        if ($recentGps === []) {
            $rows = '<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun point de suivi enregistré.</td></tr>';
        } else {
            foreach ($recentGps as $g) {
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($g['reference']) . '</strong></td>'
                    . '<td>' . View::e($g['etape']) . '</td>'
                    . '<td style="text-align:center; font-family:monospace;">' . View::e((string) $g['latitude']) . ', ' . View::e((string) $g['longitude']) . '</td>'
                    . '<td style="text-align:center; color:var(--lbp-text-muted);"><small>' . View::e($g['date_etape']) . '</small></td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card"><h3 class="rh-step-title">Derniers Événements Logistiques</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#f8fafc;"><th>Cargaison</th><th>Étape / Localisation</th><th style="text-align:center;">Coordonnées GPS</th><th style="text-align:center;">Date d\'événement</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    // ─── FOURNITURES ─────────────────────────────────────────────────

    public static function fournituresTable(array $demandes): string
    {
        $rows = '';
        if ($demandes === []) {
            $rows = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">Aucune demande enregistrée.</td></tr>';
        } else {
            foreach ($demandes as $d) {
                $tone = match ($d['status']) {
                    'EN_ATTENTE' => 'primary', 'APPROUVEE' => 'info', 'LIVREE' => 'success', 'REJETEE' => 'danger', default => 'neutral'
                };
                $statusLabel = match ($d['status']) {
                    'EN_ATTENTE' => 'SOUMIS', 'APPROUVEE' => 'VALIDÉE', 'LIVREE' => 'LIVRÉE', 'REJETEE' => 'REFUSÉE', default => $d['status']
                };
                $rejectionHtml = $d['rejection_reason']
                    ? '<div style="margin-top:0.4rem; padding:0.5rem; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#dc2626; font-size:0.8rem;"><strong>Motif de refus :</strong> ' . View::e($d['rejection_reason']) . '</div>'
                    : '';
                $actionHtml = '';
                if ($d['status'] === 'EN_ATTENTE') {
                    $actionHtml = '<form method="post" action="' . View::url('colisage/exploitation/fournitures/' . $d['id'] . '/statut') . '" style="display:inline;">'
                        . '<input type="hidden" name="statut" value="APPROUVEE">'
                        . Ui::button('Valider', ['type' => 'submit', 'variant' => 'accent', 'class' => 'finea-button-sm'])
                        . '</form> '
                        . '<button type="button" class="finea-button finea-button--danger finea-button-sm" onclick="openRefusalModal(' . $d['id'] . ')">Refuser</button>';
                } elseif ($d['status'] === 'APPROUVEE') {
                    $actionHtml = '<form method="post" action="' . View::url('colisage/exploitation/fournitures/' . $d['id'] . '/statut') . '" style="display:inline;">'
                        . '<input type="hidden" name="statut" value="LIVREE">'
                        . Ui::button('Déclarer Livré', ['type' => 'submit', 'variant' => 'success', 'class' => 'finea-button-sm'])
                        . '</form>';
                } else {
                    $actionHtml = '<span style="color:#64748b; font-size:0.85rem; font-weight:600;">Traité</span>';
                }

                $rows .= '<tr>'
                    . '<td><small>' . View::e($d['created_at']) . '</small></td>'
                    . '<td><strong>' . View::e($d['agence_name']) . '</strong></td>'
                    . '<td>' . View::e($d['demandeur_name']) . '</td>'
                    . '<td>' . nl2br(View::e($d['items_requested'])) . $rejectionHtml . '</td>'
                    . '<td>' . Ui::badge($statusLabel, $tone) . '</td>'
                    . '<td style="text-align:right; white-space:nowrap;">' . $actionHtml . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card"><h3 class="rh-step-title">Toutes les demandes de fournitures</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr style="background:#1e3a5f; color:#fff;"><th>Date</th><th>Agence</th><th>Demandeur</th><th>Description</th><th>Statut</th><th style="text-align:right;">Actions / Décisions</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function fournitureModal(array $siteOpts): string
    {
        $fields = Form::select('agence_id', $siteOpts, '', ['label' => 'Agence concernée', 'required' => true])
            . Form::input('description', ['label' => 'Description détaillée', 'placeholder' => 'Ex: 5 Ramettes papier A4, 1 paquet de stylos bleus...', 'required' => true]);

        return Ui::modal('modal-demande', 'Faire une demande de fournitures', $fields, View::url('colisage/exploitation/fournitures/demander'));
    }

    public static function refusalModal(): string
    {
        $fields = '<input type="hidden" name="statut" value="REFUSEE">'
            . Form::input('motif_refus', ['label' => 'Motif détaillé du rejet', 'placeholder' => 'Ex: Hors budget ce mois-ci, stock déjà disponible à l\'agence...', 'required' => true]);

        return Ui::modal('modal-refus', 'Motif du refus', $fields, '', ['btnLabel' => 'Confirmer le rejet', 'btnVariant' => 'danger', 'formId' => 'refus-form']);
    }

    // ─── SETTINGS ────────────────────────────────────────────────────

    public static function moduleInfoCards(): string
    {
        $items = [
            ['label' => 'Version', 'value' => '2.0'],
            ['label' => 'Tables SQL', 'value' => '12'],
            ['label' => 'Routes', 'value' => '28'],
            ['label' => 'Dernière migration', 'value' => '05/07/2026'],
        ];
        $cards = '';
        foreach ($items as $item) {
            $cards .= '<div style="background:rgba(30,58,95,0.03); padding:1rem; border-radius:8px; text-align:center;">'
                . '<p style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">' . $item['label'] . '</p>'
                . '<p style="font-size:1.3rem; font-weight:700; color:#1e3a5f; margin-top:0.3rem;">' . $item['value'] . '</p></div>';
        }

        return '<section class="finea-section-card" style="margin-top:2rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">📋 Informations du module</h2></div>'
            . '<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.5rem;">' . $cards . '</div></section>';
    }

    // ─── GROUPAGE ────────────────────────────────────────────────────

    public static function groupageDetail(array $exp): string
    {
        $icon = match ($exp['type_transport']) {
            'AÉRIEN' => '✈️ AÉRIEN', 'MARITIME' => '🚢 MARITIME', 'TERRESTRE' => '🚛 TERRESTRE', default => $exp['type_transport']
        };
        $assignedParcels = $exp['parcels'] ?? [];

        $info = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Référence Voyage :</strong> ' . View::e($exp['reference']) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Type de transport :</strong> ' . View::e($icon) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Agence de départ :</strong> ' . View::e($exp['agence_depart_name']) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Agence de destination :</strong> ' . View::e($exp['agence_arrivee_name']) . '</p>'
            . '</div><div>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Date de départ prévue :</strong> ' . View::e($exp['date_depart_prevue']) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Date d\'arrivée estimée :</strong> ' . View::e($exp['date_arrivee_estimee']) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Date de création :</strong> ' . View::e($exp['created_at']) . '</p>'
            . '<p style="margin-bottom: 0.5rem;"><strong>Nombre de colis chargés :</strong> ' . count($assignedParcels) . '</p>'
            . '</div></div>';

        $workflowBtn = '';
        if ($exp['statut'] === 'BROUILLON') {
            $disabled = empty($assignedParcels) ? 'disabled' : '';
            $workflowBtn = '<form method="post" action="' . View::url('colisage/groupage/' . $exp['id'] . '/demarrer') . '" class="js-protect-form">'
                . '<button type="submit" class="finea-button finea-button--accent" ' . $disabled . ' data-label="✈️ Démarrer l\'expédition (Départ du voyage)">✈️ Démarrer l\'expédition (Départ du voyage)</button></form>';
        } elseif ($exp['statut'] === 'EN_TRANSIT') {
            $workflowBtn = '<form method="post" action="' . View::url('colisage/groupage/' . $exp['id'] . '/arriver') . '" class="js-protect-form">'
                . '<button type="submit" class="finea-button finea-button--success" data-label="🏁 Marquer comme Arrivé à Destination (Dégroupage)">🏁 Marquer comme Arrivé à Destination (Dégroupage)</button></form>';
        } else {
            $workflowBtn = '<div style="color:#16a34a; font-weight:600; display:flex; align-items:center; gap:0.5rem;">'
                . '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                . 'Voyage Clôturé - Colis arrivés à bon port</div>';
        }

        return Ui::section('Informations du Voyage', $info
            . '<div style="margin-top: 1.5rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 1.5rem; display:flex; justify-content:flex-end; gap:1rem;">'
            . $workflowBtn . '</div>');
    }

    public static function groupageParcelsTable(array $parcels): string
    {
        $rows = '';
        if ($parcels === []) {
            $rows = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">Aucun colis chargé dans ce manifeste.</td></tr>';
        } else {
            foreach ($parcels as $ap) {
                $tone = match ($ap['statut']) {
                    'RETIRÉ', 'LIVRÉ' => 'success', 'RÉCEPTIONNÉ' => 'info', 'EN_PRÉPARATION' => 'warning', 'EN_TRANSIT' => 'primary', default => 'neutral'
                };
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($ap['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($ap['expediteur_name']) . '</td>'
                    . '<td>' . View::e($ap['destinataire_name']) . '</td>'
                    . '<td>' . View::e((string) $ap['poids_total']) . ' kg</td>'
                    . '<td>' . View::e(number_format((float) $ap['valeur_declaree'], 0, ',', ' ')) . ' ' . View::e($ap['devise']) . '</td>'
                    . '<td>' . Ui::badge($ap['statut'], $tone) . '</td>'
                    . '<td>' . Ui::button('Voir colis', ['href' => 'colisage/parcels/' . $ap['id'], 'variant' => 'secondary', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return Ui::section('Contenu du Manifeste (Colis groupés)',
            '<div class="finea-table-wrapper"><table class="finea-table"><thead>'
            . '<tr><th>N° Tracking</th><th>Expéditeur</th><th>Destinataire</th><th>Poids</th><th>Valeur Déclarée</th><th>Statut Colis</th><th>Actions</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div>');
    }

    // ─── PARCEL DETAILS (FACTURE SHOW) ────────────────────────────────

    public static function parcelDetailsCard(array $colis, string $traficLabel): string
    {
        $manifestLink = '';
        if (!empty($colis['expedition_id'])) {
            $manifestLink = '<p style="margin-top:1rem; font-size:0.9rem;">'
                . '<strong style="color:#1e3a5f;">Lié au Manifeste :</strong> '
                . '<a href="' . View::url('colisage/groupage/' . $colis['expedition_id']) . '" style="font-weight:600; color:#f97316; text-decoration:underline;">Voir le manifeste</a>'
                . '</p>';
        }

        $html = '<div style="padding:0.5rem 0; margin-bottom:1rem; background:rgba(30,58,95,0.03); border-radius:4px;">'
            . '<p style="text-align:center; color:#1e3a5f; font-weight:700; font-size:0.85rem; text-transform:uppercase;">IMPRIMÉ SPÉCIFIQUE — Facture & Colisage</p>'
            . '</div>'
            . '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">'
            . '<div><p style="color:#64748b; font-size:0.85rem;">Agence : <strong style="color:#1e3a5f;">LBP Logistics — ' . View::e($colis['agence_depart_name'] ?? 'Siège Social') . '</strong></p></div>'
            . '<div style="text-align:right;"><p style="color:#64748b; font-size:0.85rem;">SERVICE CLIENT : <strong>0503467979 / 0503497979</strong></p></div>'
            . '</div>'
            . '<div style="background:#1e3a5f; color:#fff; padding:1rem 2rem; border-radius:6px; text-align:center; margin:1rem 0;">'
            . '<h2 style="margin:0; font-size:1.4rem; letter-spacing:0.5px;">DÉTAILS COLIS&nbsp;&nbsp;' . View::e($colis['numero_tracking']) . '</h2>'
            . '</div>'
            . '<div style="text-align:center; margin-bottom:1rem;">'
            . '<p style="color:#64748b; font-size:0.9rem;">Nombre total de colis : <strong>' . View::e((string) ($colis['nombre_colis'] ?? 1)) . '</strong></p>'
            . '</div>'
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; padding:0.5rem 0;">'
            . '<div><table style="width:100%; border-collapse:collapse;">'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f; width:40%;">Code Colis :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['numero_tracking']) . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">EXPÉDITEUR :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['expediteur_name']) . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TÉL EXP. :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['expediteur_phone'] ?? '—') . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TRAFIC :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($traficLabel) . '</td></tr>'
            . '</table></div>'
            . '<div><table style="width:100%; border-collapse:collapse;">'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f; width:40%;">Date d\'envoi :</td><td style="padding:0.4rem 0; color:#333;">' . View::e(date('d/m/Y', strtotime($colis['created_at']))) . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">DESTINATION :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['agence_arrivee_name'] ?? '—') . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">DESTINATAIRE :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['destinataire_name']) . '</td></tr>'
            . '<tr><td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TÉL DEST. :</td><td style="padding:0.4rem 0; color:#333;">' . View::e($colis['destinataire_phone'] ?? '—') . '</td></tr>'
            . '</table></div>'
            . '</div>'
            . $manifestLink;

        return Ui::section('', $html, '', ['style' => 'border-top:4px solid #1e3a5f;']);
    }

    public static function parcelMerchandiseTable(array $colis, float $sousTotal, float $montantTotal, float $montantEur): string
    {
        $rows = '';
        if (empty($colis['marchandises'])) {
            $rows = '<tr><td colspan="8" style="text-align:center; padding:1.5rem;">Aucune marchandise répertoriée.</td></tr>';
        } else {
            $idx = 0;
            foreach ($colis['marchandises'] as $m) {
                $idx++;
                $rows .= '<tr>'
                    . '<td style="text-align:center; font-weight:600;">' . $idx . '</td>'
                    . '<td style="text-align:center;">' . View::e((string) ($m['nbre_colis'] ?? 1)) . '</td>'
                    . '<td>' . View::e($m['description']) . '</td>'
                    . '<td>' . View::e($m['emballage'] ?? '—') . '</td>'
                    . '<td style="text-align:center;">' . View::e((string) ($m['qte_emballage'] ?? 1)) . '</td>'
                    . '<td style="text-align:right;">' . View::e(number_format((float) $m['poids_unitaire'], 2, ',', ' ')) . '</td>'
                    . '<td style="text-align:right;">' . View::e(number_format((float) ($m['prix_kg'] ?? 0), 0, ',', ' ')) . '</td>'
                    . '<td style="text-align:right; font-weight:600;">' . number_format((float) ($m['total_ligne'] ?? 0), 0, ',', '.') . ' FCFA</td>'
                    . '</tr>';
            }
        }

        $table = '<div class="finea-table-wrapper"><table class="finea-table">'
            . '<thead><tr style="background:#1e3a5f; color:#fff;">'
            . '<th style="width:5%;">N°</th><th style="width:8%;">Nbre Colis</th><th>Description</th><th style="width:12%;">Emballage</th><th style="width:8%;">Qté Emb.</th><th style="width:10%;">Poids (kg)</th><th style="width:10%;">Prix / Kg</th><th style="width:12%;">Total</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr><td colspan="7" style="text-align:right; font-weight:600;">SOUS-TOTAL</td><td style="text-align:right; font-weight:600;">' . number_format($sousTotal, 0, ',', '.') . ' FCFA</td></tr>'
            . '<tr style="background:#1e3a5f; color:#fff;"><td colspan="7" style="text-align:right; font-weight:700; font-size:1.1rem;">MONTANT TOTAL</td>'
            . '<td style="text-align:right; font-weight:700; font-size:1.1rem;">' . number_format($montantTotal, 0, ',', '.') . ' FCFA<br><small>≈ ' . number_format($montantEur, 2, ',', '.') . ' €</small></td></tr>'
            . '</tfoot></table></div>';

        return Ui::section('Marchandises répertoriées', $table);
    }

    public static function parcelSignatureBoxes(): string
    {
        $boxes = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:3rem;">'
            . '<div style="border:1px solid #ccc; border-radius:6px; padding:1.5rem; min-height:100px;"><p style="font-weight:600; font-size:0.85rem; color:#1e3a5f;">CLIENT (date et visa)</p></div>'
            . '<div style="border:1px solid #ccc; border-radius:6px; padding:1.5rem; min-height:100px;"><p style="font-weight:600; font-size:0.85rem; color:#1e3a5f;">SOCIÉTÉ (date et visa)</p></div>'
            . '</div>';

        return Ui::section('', $boxes);
    }

    public static function parcelStatusAction(array $colis, string $badgeTone): string
    {
        if ($colis['statut'] !== 'RETIRÉ' && $colis['statut'] !== 'LIVRÉ') {
            $fields = '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">'
                . Form::input('recup_nom', ['label' => 'Nom du récupérateur', 'required' => true, 'placeholder' => 'Nom complet'])
                . Form::input('recup_cni', ['label' => 'N° pièce d\'identité (CNI)', 'required' => true, 'placeholder' => 'Ex: CNI998877'])
                . Form::input('recup_telephone', ['label' => 'Téléphone récupérateur', 'required' => true, 'placeholder' => 'Ex: 05050505'])
                . '</div>';

            $form = '<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">⚠️ Vérification obligatoire de la CNI du récupérateur (Responsabilité Juridique)</p>'
                . '<form method="post" action="' . View::url('colisage/parcels/' . $colis['id'] . '/retirer') . '" id="form-retrait">'
                . $fields
                . '<div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">'
                . Ui::button('Confirmer la livraison (Signature juridique)', ['type' => 'submit', 'variant' => 'accent', 'id' => 'btn-retrait'])
                . '</div></form>'
                . '<script>'
                . '(function() { const form = document.getElementById("form-retrait"); if (form) { form.addEventListener("submit", function(e) { const btn = document.getElementById("btn-retrait"); if (btn) { if (btn.dataset.submitted === "true") { e.preventDefault(); return; } btn.dataset.submitted = "true"; btn.disabled = true; btn.innerHTML = \'<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Traitement en cours...</span>\'; } }); } })();'
                . '</script><style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>';

            return Ui::section('Signaler le retrait du colis (Livraison finale)', $form, '', ['style' => 'border-left:4px solid #f97316;']);
        }

        $deliveredHtml = '<h3 style="color:#15803d; display:flex; align-items:center; gap:0.5rem;">'
            . '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Colis Retiré / Livré au destinataire</h3>'
            . '<div style="margin-top:1rem; display:grid; grid-template-columns:1fr 1fr; gap:2rem; font-size:0.95rem;">'
            . '<div>'
            . '<p style="margin-bottom:0.3rem;"><strong>Récupérateur :</strong> ' . View::e($colis['recup_nom'] ?? '') . '</p>'
            . '<p style="margin-bottom:0.3rem;"><strong>N° d\'identité (CNI) :</strong> ' . View::e($colis['recup_cni'] ?? '') . '</p>'
            . '</div><div>'
            . '<p style="margin-bottom:0.3rem;"><strong>Téléphone :</strong> ' . View::e($colis['recup_telephone'] ?? '') . '</p>'
            . '<p style="margin-bottom:0.3rem;"><strong>Date & Heure :</strong> ' . View::e($colis['recup_date_heure'] ?? '') . '</p>'
            . '</div></div>';

        return '<div class="finea-section-card" style="background:rgba(34,197,94,0.06); border:1px solid rgba(34,197,94,0.15);">' . $deliveredHtml . '</div>';
    }

    public static function parcelFooter(array $colis): string
    {
        $operatorName = \App\Helpers\Auth::user() ? \App\Helpers\Auth::user()->fullName : 'Service Transit';
        $editedBy = 'Édité par <strong>' . View::e($operatorName) . '</strong> le ' . date('d/m/Y', strtotime($colis['created_at'])) . ' à ' . date('H:i', strtotime($colis['created_at']));
        $refStr = 'Réf. FCO-' . View::e(date('my', strtotime($colis['created_at']))) . '-' . View::e(substr($colis['numero_tracking'], -3));

        return '<div style="text-align:center; padding:2rem 0 3rem; color:#64748b; font-size:0.85rem;">'
            . '<div style="display:flex; justify-content:space-between; margin-bottom:1.5rem; font-size:0.8rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem; color:#64748b;">'
            . '<div>' . $editedBy . '</div>'
            . '<div>' . $refStr . '</div>'
            . '</div>'
            . '<p style="font-weight:700; color:#1e3a5f;">ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY</p>'
            . '<p>Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92</p>'
            . '<div style="display:flex; justify-content:center; gap:4rem; margin-top:0.5rem;">'
            . '<div><strong>ABIDJAN</strong><br>Lun–Ven : 08h–17h | Sam–Dim : 08h–14h30</div>'
            . '<div><strong>PARIS</strong><br>Lun–Sam : 10h30–18h | Dim : 10h–14h</div>'
            . '</div>'
            . '<p style="margin-top:1rem; font-size:0.8rem;"><strong>www.labelleporte.net</strong> | contact@labelleporte.net | +2252721580978 | +2250101222195</p>'
            . '</div>';
    }

    // ─── PARCELS LIST (INDEX) ────────────────────────────────────────

    public static function parcelsFilterForm(array $filters): string
    {
        return '<form method="get" action="' . View::url('colisage/parcels') . '" class="rh-personnel-filters">'
            . '<div class="rh-personnel-filter-grid">'
            . Form::input('q', [
                'label' => 'Recherche',
                'value' => (string) ($filters['q'] ?? ''),
                'placeholder' => 'N° Tracking, expéditeur, destinataire'
            ])
            . Form::selectSearch('statut', 'Statut', [
                ['value' => '', 'label' => 'Tous les statuts'],
                ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
                ['value' => 'EN_PRÉPARATION', 'label' => 'En préparation'],
                ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                ['value' => 'ARRIVÉ', 'label' => 'Arrivé'],
                ['value' => 'LIVRÉ', 'label' => 'Livré'],
                ['value' => 'RETIRÉ', 'label' => 'Retiré']
            ], $filters['statut'] ?? '')
            . Form::selectSearch('type_expediteur', 'Catégorie Fret', [
                ['value' => '', 'label' => 'Toutes les catégories'],
                ['value' => 'export_aerien', 'label' => '✈️ Export Aérien'],
                ['value' => 'export_maritime', 'label' => '🚢 Export Maritime'],
                ['value' => 'import_aerien', 'label' => '✈️ Import Aérien'],
                ['value' => 'import_maritime', 'label' => '🚢 Import Maritime']
            ], $filters['type_expediteur'] ?? '')
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Rechercher'
            . '</button>'
            . '<a href="' . View::url('colisage/parcels') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg> Réinitialiser'
            . '</a>'
            . '</div></form>';
    }

    public static function parcelsListTable(array $parcels): string
    {
        $rows = '';
        if ($parcels === []) {
            $rows = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;"><strong>Aucun colis trouvé</strong><br><small>Aucune fiche ne correspond aux critères sélectionnés.</small></td></tr>';
        } else {
            foreach ($parcels as $p) {
                $catLabel = match ($p['type_expediteur']) {
                    'export_aerien' => '✈️ Export Aérien',
                    'export_maritime' => '🚢 Export Maritime',
                    'import_aerien' => '✈️ Import Aérien',
                    'import_maritime' => '🚢 Import Maritime',
                    default => $p['type_expediteur']
                };
                $tone = match ($p['statut']) {
                    'RETIRÉ', 'LIVRÉ' => 'success', 'RÉCEPTIONNÉ' => 'info', 'EN_PRÉPARATION' => 'warning', 'EN_TRANSIT' => 'primary', default => 'neutral'
                };
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($p['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($p['expediteur_name']) . '</td>'
                    . '<td>' . View::e($p['destinataire_name']) . '</td>'
                    . '<td><small>' . View::e($catLabel) . '</small></td>'
                    . '<td>' . View::e((string) $p['poids_total']) . ' kg</td>'
                    . '<td>' . View::e(number_format((float) $p['valeur_declaree'], 0, ',', ' ')) . ' ' . View::e($p['devise']) . '</td>'
                    . '<td>' . Ui::badge($p['statut'], $tone) . '</td>'
                    . '<td>' . Ui::button('Voir détails', ['href' => 'colisage/parcels/' . $p['id'], 'variant' => 'primary', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Tracking</th><th>Expéditeur</th><th>Destinataire</th><th>Catégorie</th><th>Poids</th><th>Valeur Décl.</th><th>Statut</th><th>Actions</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function groupageListTable(array $expeditions): string
    {
        $rows = '';
        if ($expeditions === []) {
            $rows = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;"><strong>Aucune expédition planifiée</strong><br><small>Commencez par planifier un nouveau voyage de groupage.</small></td></tr>';
        } else {
            foreach ($expeditions as $e) {
                $icon = match ($e['type_transport']) {
                    'AÉRIEN' => '✈️ Aérien', 'MARITIME' => '🚢 Maritime', 'TERRESTRE' => '🚛 Terrestre', default => $e['type_transport']
                };
                $tone = match ($e['statut']) {
                    'ARRIVÉ' => 'success', 'EN_TRANSIT' => 'primary', 'BROUILLON' => 'warning', default => 'neutral'
                };
                $rows .= '<tr>'
                    . '<td><strong>' . View::e($e['reference']) . '</strong></td>'
                    . '<td>' . View::e($icon) . '</td>'
                    . '<td>' . View::e($e['agence_depart_name']) . '</td>'
                    . '<td>' . View::e($e['agence_arrivee_name']) . '</td>'
                    . '<td>' . View::e($e['date_depart_prevue'] ?? 'Non planifiée') . '</td>'
                    . '<td>' . View::e($e['date_arrivee_estimee'] ?? 'Non planifiée') . '</td>'
                    . '<td>' . Ui::badge($e['statut'], $tone) . '</td>'
                    . '<td>' . Ui::button('Gérer groupage', ['href' => 'colisage/groupage/' . $e['id'], 'variant' => 'primary', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Référence</th><th>Type Transport</th><th>Agence Départ</th><th>Agence Arrivée</th><th>Départ Prévu</th><th>Arrivée Estimée</th><th>Statut</th><th>Actions</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function autresFilterForm(array $filters): string
    {
        return '<form method="get" action="' . View::url('colisage/autres') . '" class="rh-personnel-filters">'
            . '<div class="rh-personnel-filter-grid">'
            . Form::input('q', [
                'label' => 'Recherche',
                'value' => (string) ($filters['q'] ?? ''),
                'placeholder' => 'N° Tracking, expéditeur, destinataire'
            ])
            . Form::selectSearch('type_expediteur', 'Transporteur / Service', [
                ['value' => '', 'label' => 'Tous les services'],
                ['value' => 'dhl', 'label' => '📦 DHL Express'],
                ['value' => 'colis_rapide_export', 'label' => '⚡ Colis Rapide Export'],
                ['value' => 'colis_rapide_import', 'label' => '⚡ Colis Rapide Import']
            ], $filters['type_expediteur'] ?? '')
            . Form::selectSearch('trajet', 'Trajet (Colis Rapide)', [
                ['value' => '', 'label' => 'Tous les trajets'],
                ['value' => 'CIV_SEN', 'label' => 'CIV ➔ SEN'],
                ['value' => 'SEN_CIV', 'label' => 'SEN ➔ CIV'],
                ['value' => 'CIV_FR', 'label' => 'CIV ➔ FR'],
                ['value' => 'FR_CIV', 'label' => 'FR ➔ CIV'],
                ['value' => 'SEN_FR', 'label' => 'SEN ➔ FR'],
                ['value' => 'FR_SEN', 'label' => 'FR ➔ SEN']
            ], $filters['trajet'] ?? '')
            . Form::selectSearch('statut', 'Statut', [
                ['value' => '', 'label' => 'Tous les statuts'],
                ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
                ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                ['value' => 'ARRIVÉ', 'label' => 'Arrivé'],
                ['value' => 'RETIRÉ', 'label' => 'Retiré']
            ], $filters['statut'] ?? '')
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Rechercher'
            . '</button>'
            . '<a href="' . View::url('colisage/autres') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg> Réinitialiser'
            . '</a>'
            . '</div></form>';
    }

    public static function autresListTable(array $parcels): string
    {
        $rows = '';
        if ($parcels === []) {
            $rows = '<tr><td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;"><strong>Aucun envoi express trouvé</strong><br><small>Aucune fiche ne correspond aux critères sélectionnés.</small></td></tr>';
        } else {
            foreach ($parcels as $p) {
                $srv = match ($p['type_expediteur']) {
                    'dhl' => '📦 DHL Express',
                    'colis_rapide_export' => '⚡ Colis Rapide Export',
                    'colis_rapide_import' => '⚡ Colis Rapide Import',
                    default => $p['type_expediteur']
                };
                $trajetHtml = $p['trajet']
                    ? '<span class="finea-badge finea-badge--info" style="font-size:0.75rem; text-transform:none;">' . str_replace('_', ' ➔ ', $p['trajet']) . '</span>'
                    : '<span style="color:#94a3b8;">—</span>';
                $tone = match ($p['statut']) {
                    'RETIRÉ', 'LIVRÉ' => 'success', 'RÉCEPTIONNÉ' => 'info', 'EN_TRANSIT' => 'primary', 'ARRIVÉ' => 'accent', default => 'neutral'
                };

                $rows .= '<tr>'
                    . '<td><strong>' . View::e($p['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($srv) . '</td>'
                    . '<td>' . $trajetHtml . '</td>'
                    . '<td>' . View::e($p['expediteur_name']) . '</td>'
                    . '<td>' . View::e($p['destinataire_name']) . '</td>'
                    . '<td>' . View::e((string) $p['poids_total']) . ' kg</td>'
                    . '<td><strong>' . number_format((float) $p['montant_total'], 0, ',', '.') . ' ' . View::e($p['devise']) . '</strong></td>'
                    . '<td>' . Ui::badge($p['statut'], $tone) . '</td>'
                    . '<td>' . Ui::button('Voir détails', ['href' => 'colisage/parcels/' . $p['id'], 'variant' => 'primary', 'class' => 'finea-button-sm']) . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Tracking</th><th>Service</th><th>Trajet</th><th>Expéditeur</th><th>Destinataire</th><th>Poids total</th><th>Montant</th><th>Statut</th><th>Actions</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    public static function marchandisesInputTable(array $prodOptions, float $eurToXofRate = 655.957): string
    {
        $rows = '';
        for ($i = 0; $i < 5; $i++) {
            $rows .= '<tr>'
                . '<td style="text-align:center; font-weight:600;">' . ($i + 1) . '</td>'
                . '<td>' . Form::rawInput('m_nbre_colis[]', '1', ['type' => 'number', 'min' => '1']) . '</td>'
                . '<td>'
                . Form::rawSelect('m_product_id_' . $i . '[]', $prodOptions, '', [
                    'id' => 'm_product_id_' . $i,
                    'multiple' => 'multiple',
                    'data-finea-select-search' => '1',
                ])
                . '<div style="margin-top:0.4rem; display:flex; gap:0.4rem;">'
                . Form::rawInput('m_custom_name[]', '', ['placeholder' => 'Ou saisir un nom...'])
                . Form::rawInput('m_custom_price[]', '', ['type' => 'number', 'step' => '0.01', 'placeholder' => 'Prix unit.'])
                . '</div>'
                . '</td>'
                . '<td>' . Form::rawInput('m_emballage[]', '', ['placeholder' => 'Carton, Sac...']) . '</td>'
                . '<td>' . Form::rawInput('m_qte_emballage[]', '1', ['type' => 'number', 'min' => '1']) . '</td>'
                . '<td>' . Form::rawInput('m_weight[]', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) . '</td>'
                . '<td>' . Form::rawInput('m_prix_kg[]', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) . '</td>'
                . '<td style="background:rgba(0,0,0,0.02); text-align:right; font-weight:600;"><span class="ligne-total">0 FCFA</span></td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table" style="table-layout: auto;">'
            . '<thead><tr style="background:#1e3a5f; color:#fff;">'
            . '<th style="width:3%; min-width:30px;">N°</th>'
            . '<th style="width:7%; min-width:80px;">Nbre Colis</th>'
            . '<th style="width:35%; min-width:320px;">Description</th>'
            . '<th style="width:12%; min-width:110px;">Emballage</th>'
            . '<th style="width:7%; min-width:80px;">Qté Emb.</th>'
            . '<th style="width:11%; min-width:105px;">Poids (kg)</th>'
            . '<th style="width:11%; min-width:110px;">Prix / Kg</th>'
            . '<th style="width:14%; min-width:120px;">Total</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr><td colspan="7" style="text-align:right; font-weight:600;">SOUS-TOTAL</td><td style="text-align:right; font-weight:600;" id="sous_total">0 FCFA</td></tr>'
            . '<tr style="background:#1e3a5f; color:#fff;"><td colspan="7" style="background:#1e3a5f !important; text-align:right; font-weight:700; font-size:1.1rem; color:#ffffff !important;">MONTANT TOTAL</td>'
            . '<td style="background:#1e3a5f !important; text-align:right; font-weight:700; font-size:1.1rem; color:#ffffff !important;"><span id="montant_total_fcfa" style="color:#ffffff !important;">0 FCFA</span><br><small id="montant_total_eur" style="color:rgba(255,255,255,0.85) !important;">≈ 0.00 €</small></td></tr>'
            . '</tfoot></table></div>';
    }

    public static function settingsRatesTable(array $devisesRates): string
    {
        if (empty($devisesRates)) {
            return '';
        }

        $tbody = '';
        foreach ($devisesRates as $r) {
            $tbody .= '<tr>'
                . '<td>' . Ui::badge($r['devise_source']) . '</td>'
                . '<td>' . Ui::badge($r['devise_cible']) . '</td>'
                . '<td style="text-align:right; font-weight:600;">' . View::e(number_format((float) $r['taux'], 6, ',', '.')) . '</td>'
                . '<td style="color:#64748b; font-size:0.8rem;">' . View::e($r['updated_at'] ?? '—') . '</td>'
                . '</tr>';
        }

        return '<div style="margin-bottom:1.5rem;">'
            . '<h4 style="font-size:0.9rem; color:#475569; margin-bottom:0.5rem;">Taux enregistrés (table devises)</h4>'
            . '<div class="finea-table-wrapper"><table class="finea-table" style="font-size:0.85rem;">'
            . '<thead><tr style="background:#f1f5f9;"><th>Source</th><th>Cible</th><th style="text-align:right;">Taux</th><th>Mis à jour</th></tr></thead>'
            . '<tbody>' . $tbody . '</tbody></table></div></div>';
    }
}
