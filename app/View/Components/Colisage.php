<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Colisage\ColisageIndexPage;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Colisage
{
    /** @return array<int, array{value: string, label: string}> */
    private static function emballageOptions(): array
    {
        return [
            ['value' => '', 'label' => '-- Sélectionner --'],
            ['value' => 'Carton', 'label' => 'Carton'],
            ['value' => 'Sac', 'label' => 'Sac'],
            ['value' => 'Caisse en bois', 'label' => 'Caisse en bois'],
            ['value' => 'Palette', 'label' => 'Palette'],
            ['value' => 'Bidon/Fût', 'label' => 'Bidon / Fût'],
            ['value' => 'Valise', 'label' => 'Valise'],
            ['value' => 'Enveloppe', 'label' => 'Enveloppe'],
            ['value' => 'Autre', 'label' => 'Autre'],
        ];
    }

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
            . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
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
            . '<div style="grid-column: span 3; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.8rem 1.2rem; border-radius: 8px; margin-top: 0.5rem;">'
            . Form::checkbox('assurance_souscrite', '1', false, ['label' => 'Souscrire à l\'Assurance Colis (+2% de la valeur déclarée — Couverture jusqu\'à 100% de la valeur)'])
            . '</div></div></div>'
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
            . '            const qtyInput = row.querySelector(\'input[name="m_nbre_colis[]"]\');'
            . '            const weightInput = row.querySelector(\'input[name="m_weight[]"]\');'
            . '            const priceInput = row.querySelector(\'input[name="m_prix_kg[]"]\');'
            . '            const totalInput = row.querySelector(\'.ligne-total\');'
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
            . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">'
            . Form::select('type_transport', [
                ['value' => 'AÉRIEN', 'label' => 'AÉRIEN (Fret aérien rapide)'],
                ['value' => 'MARITIME', 'label' => 'MARITIME (Fret maritime conteneur)'],
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
                    Ui::button('Manifeste de Douane (MAWB)', [
                        'href' => 'colisage/groupage/' . $exp['id'] . '/manifeste',
                        'variant' => 'accent',
                        'target' => '_blank'
                    ]),
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
                    . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
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
                    Ui::button('+ Nouvelle demande de fournitures', [
                        'type' => 'button',
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
            . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
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
            . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
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
                $id = (int) ($p['id'] ?? 0);
                $tracking = (string) ($p['numero_tracking'] ?? '—');
                $expName = (string) ($p['expediteur_name'] ?? 'Client');
                $statut = (string) ($p['statut'] ?? 'N/A');
                $tone = (string) ($p['status_tone'] ?? 'neutral');

                $html .= '<tr>'
                    . '<td><strong><a href="' . View::url('colisage/parcels/' . $id) . '">' . View::e($tracking) . '</a></strong></td>'
                    . '<td>' . View::e($expName) . '</td>'
                    . '<td>' . Ui::badge($statut, $tone) . '</td>'
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
                $id = (int) ($e['id'] ?? 0);
                $ref = (string) ($e['reference'] ?? '—');
                $arrName = (string) ($e['agence_arrivee_name'] ?? 'Bobigny (France)');
                $statut = (string) ($e['statut'] ?? 'N/A');
                $tone = (string) ($e['status_tone'] ?? 'neutral');

                $html .= '<tr>'
                    . '<td><strong><a href="' . View::url('colisage/groupage/' . $id) . '">' . View::e($ref) . '</a></strong></td>'
                    . '<td>' . View::e($arrName) . '</td>'
                    . '<td>' . Ui::badge($statut, $tone) . '</td>'
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
            . '<strong>Europe</strong><p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Paris 17 chemin des Vignes 93000 Bobigny</p></div>'
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
        $actionHtml = '<a href="' . View::url('colisage/parcels/nouveau') . '" class="rh-filter-btn rh-filter-btn--primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #fabd02 0%, #eab308 100%); color: #0f172a; font-weight: 800; border-radius: 10px; text-decoration: none; box-shadow: 0 4px 14px rgba(250,189,2,0.35); transition: all 0.2s ease;">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
            . 'Enregistrer un colis'
            . '</a>';

        $header = Ui::pageHeader(
            'Gestion des Colis',
            'Saisie, suivi temps réel et groupage des colis clients.',
            [
                'eyebrow' => 'Opérations de Colisage',
                'class' => 'rh-hero-white',
                'actions' => [
                    $actionHtml,
                ],
            ]
        );

        // Compute KPIs for Stats Bar
        $totalCount = $page->total;
        $pagePoids = array_sum(array_column($page->parcels, 'poids_total'));
        $pageValeur = array_sum(array_column($page->parcels, 'valeur_declaree'));
        $inTransitCount = count(array_filter($page->parcels, static function(array $p): bool {
            return in_array($p['statut'], ['EN_TRANSIT', 'EN_PRÉPARATION', 'RÉCEPTIONNÉ'], true);
        }));

        $statsGrid = '
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Total Fiches Colis</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1;">' . number_format($totalCount) . '</div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Poids Cumulé (Page)</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1;">' . number_format($pagePoids, 2, ',', ' ') . ' <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">kg</span></div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Valeur Déclarée</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1;">' . number_format($pageValeur, 0, ',', ' ') . ' <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">XOF</span></div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">En Cours / Transit</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #4f46e5; line-height: 1;">' . number_format($inTransitCount) . '</div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </div>
            </div>
        </div>';

        // Filters form
        $q = Form::input('q', [
            'label' => 'Recherche',
            'value' => (string) ($page->filters['q'] ?? ''),
            'placeholder' => 'N° Tracking, expéditeur, destinataire...',
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

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary" style="background: #0f172a; color: #ffffff; border: none; font-weight: 700; border-radius: 8px; padding: 10px 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Rechercher'
            . '</button>';

        $resetBtn = '<a href="' . View::url('colisage/parcels') . '" class="rh-filter-btn rh-filter-btn--reset" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; border-radius: 8px; padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>';

        // Quick status pills
        $currentStatut = (string) ($page->filters['statut'] ?? '');
        $statusPills = [
            '' => 'Tous les colis',
            'RÉCEPTIONNÉ' => 'Réceptionné',
            'EN_PRÉPARATION' => 'En préparation',
            'EN_TRANSIT' => 'En transit',
            'ARRIVÉ' => 'Arrivé',
            'LIVRÉ' => 'Livré',
            'RETIRÉ' => 'Retiré',
        ];

        $pillsHtml = '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">';
        foreach ($statusPills as $val => $lbl) {
            $isActive = $currentStatut === $val;
            $pillBg = $isActive ? '#0f172a' : '#f8fafc';
            $pillColor = $isActive ? '#ffffff' : '#64748b';
            $pillBorder = $isActive ? '#0f172a' : '#e2e8f0';

            $queryParams = array_filter(array_merge($page->filters, ['statut' => $val, 'page' => 1]), fn($v) => $v !== '');
            $pillUrl = View::url('colisage/parcels' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : ''));

            $pillsHtml .= '<a href="' . $pillUrl . '" style="padding: 6px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; text-decoration: none; background: ' . $pillBg . '; color: ' . $pillColor . '; border: 1px solid ' . $pillBorder . '; transition: all 0.2s ease;">' . View::e($lbl) . '</a>';
        }
        $pillsHtml .= '</div>';

        $form = '<form method="get" action="' . View::url('colisage/parcels') . '" class="rh-personnel-filters" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); margin-bottom: 1.75rem;">' . $filterGrid . $filterActions . $pillsHtml . '</form>';

        // Table
        $tableHtml = '';
        if ($page->parcels === []) {
            $tableHtml = Ui::emptyState(
                'Aucun colis trouvé',
                'Aucune fiche ne correspond aux critères sélectionnés.'
            );
        } else {
            $canDelete = \App\Helpers\Auth::isAdmin() || \App\Helpers\Auth::hasRole('dg');
            $rows = '';
            foreach ($page->parcels as $p) {
                $categoryBadge = match($p['type_expediteur']) {
                    'export_aerien' => '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.2.5l-.6.6c-.3.4-.2 1 .2 1.3L8 12l-3.5 3.5-2.2-.7c-.4-.1-.8.1-1 .4l-.3.3c-.3.4-.2.9.2 1.2l3 2.5 2.5 3c.3.4.8.5 1.2.2l.3-.3c.3-.2.5-.6.4-1l-.7-2.2L12 16l2.9 4.3c.3.4.9.5 1.3.2l.6-.6c.4-.3.6-.7.5-1.2z"/></svg> Export Aérien</span>',
                    'export_maritime' => '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: #f0fdf4; color: #15803d;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/></svg> Export Maritime</span>',
                    'import_aerien' => '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: #faf5ff; color: #7e22ce;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.2.5l-.6.6c-.3.4-.2 1 .2 1.3L8 12l-3.5 3.5-2.2-.7c-.4-.1-.8.1-1 .4l-.3.3c-.3.4-.2.9.2 1.2l3 2.5 2.5 3c.3.4.8.5 1.2.2l.3-.3c.3-.2.5-.6.4-1l-.7-2.2L12 16l2.9 4.3c.3.4.9.5 1.3.2l.6-.6c.4-.3.6-.7.5-1.2z"/></svg> Import Aérien</span>',
                    'import_maritime' => '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: #fff7ed; color: #c2410c;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/></svg> Import Maritime</span>',
                    default => '<span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: #f1f5f9; color: #475569;">' . View::e($p['type_expediteur']) . '</span>'
                };

                $badge = match($p['statut']) {
                    'RETIRÉ', 'LIVRÉ' => '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;"><span style="width: 7px; height: 7px; border-radius: 50%; background: #16a34a; display: inline-block;"></span> ' . View::e($p['statut']) . '</span>',
                    'EN_TRANSIT' => '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;"><span style="width: 7px; height: 7px; border-radius: 50%; background: #4f46e5; display: inline-block;"></span> ' . View::e($p['statut']) . '</span>',
                    'EN_PRÉPARATION' => '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;"><span style="width: 7px; height: 7px; border-radius: 50%; background: #d97706; display: inline-block;"></span> ' . View::e($p['statut']) . '</span>',
                    'RÉCEPTIONNÉ' => '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;"><span style="width: 7px; height: 7px; border-radius: 50%; background: #0284c7; display: inline-block;"></span> ' . View::e($p['statut']) . '</span>',
                    default => '<span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">' . View::e($p['statut']) . '</span>'
                };

                $trackingBadge = '<div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 0.85rem; font-weight: 800; color: #0f172a;">'
                    . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>'
                    . View::e($p['numero_tracking'])
                    . '</div>';

                $expInitial = mb_strtoupper(mb_substr($p['expediteur_name'] !== '' ? $p['expediteur_name'] : 'E', 0, 1));
                $expCell = '<div style="display: flex; align-items: center; gap: 10px;">'
                    . '<div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #2563eb); color: #ffffff; font-weight: 800; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(14,165,233,0.3);">' . $expInitial . '</div>'
                    . '<span style="font-weight: 600; color: #1e293b;">' . View::e($p['expediteur_name']) . '</span>'
                    . '</div>';

                $destInitial = mb_strtoupper(mb_substr($p['destinataire_name'] !== '' ? $p['destinataire_name'] : 'D', 0, 1));
                $destCell = '<div style="display: flex; align-items: center; gap: 10px;">'
                    . '<div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #ffffff; font-weight: 800; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(139,92,246,0.3);">' . $destInitial . '</div>'
                    . '<span style="font-weight: 600; color: #1e293b;">' . View::e($p['destinataire_name']) . '</span>'
                    . '</div>';

                $actionsStr = '';
                foreach ($p['actions'] as $act) {
                    $actionsStr .= '<a href="' . View::url($act['href']) . '" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #0f172a; color: #ffffff; font-size: 0.78rem; font-weight: 700; border-radius: 6px; text-decoration: none; transition: background 0.2s;">'
                        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
                        . View::e($act['label'])
                        . '</a>';
                }
                if ($canDelete) {
                    $actionsStr .= ' ' . Ui::deleteForm(
                        'colisage/parcels/' . $p['id'] . '/supprimer',
                        'Supprimer définitivement le colis ' . $p['numero_tracking'] . ' ? Cette action est irréversible.'
                    );
                }

                $rows .= '<tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'transparent\'">'
                    . '<td style="padding: 14px 16px;">' . $trackingBadge . '</td>'
                    . '<td style="padding: 14px 16px;">' . $expCell . '</td>'
                    . '<td style="padding: 14px 16px;">' . $destCell . '</td>'
                    . '<td style="padding: 14px 16px;">' . $categoryBadge . '</td>'
                    . '<td style="padding: 14px 16px; font-weight: 700; color: #334155;">' . View::e((string) $p['poids_total']) . ' kg</td>'
                    . '<td style="padding: 14px 16px; font-weight: 800; color: #0f172a;">' . View::e(number_format((float) $p['valeur_declaree'], 0, ',', ' ')) . ' <small style="color: #64748b;">' . View::e($p['devise']) . '</small></td>'
                    . '<td style="padding: 14px 16px;">' . $badge . '</td>'
                    . '<td style="padding: 14px 16px;">' . $actionsStr . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper" style="overflow-x: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 4px 12px rgba(15,23,42,0.03);">'
                . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">'
                . '<thead style="background: #0f172a; color: #ffffff;">'
                . '<tr>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">N° Tracking</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Expéditeur</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Destinataire</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Catégorie</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Poids</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Valeur Décl.</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Statut</th>'
                . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>'
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
            . $statsGrid
            . $form
            . '<div class="finea-section-card" style="margin-top: 1rem; padding: 0; background: transparent; border: none; box-shadow: none;">'
            . $tableHtml
            . '</div>'
            . '<div style="margin-top: 1.5rem;">' . $pagination . '</div>'
            . '</div></div>';
    }

    /**
     * @param array<string,mixed>|null $trajet Trajet verrouillé (sous-menu Opération à trajet fixe).
     *        Quand fourni, le formulaire est identique en tout point à la saisie générique,
     *        à la seule différence que le trajet est imposé et non modifiable (Règle 3.4).
     */
    public static function createPage(array $sites, array $clients, array $products = [], float $tauxChangeEur = 655.957, ?array $trajet = null): string
    {
        $header = Ui::pageHeader(
            $trajet !== null ? 'Enregistrer un Colis — ' . $trajet['code'] : 'Enregistrer un Colis',
            $trajet !== null
                ? 'Le trajet est déterminé par le sous-menu Opération emprunté et reste fixe pour cette saisie.'
                : 'Saisie de la fiche de colisage et des marchandises.',
            [
                'eyebrow' => $trajet !== null ? 'Opération — Trajet verrouillé' : 'Nouveau Colis',
                'class' => 'rh-hero-white',
            ]
        );

        $trajetLockNotice = '';
        if ($trajet !== null) {
            $typeTone = match ($trajet['type_transport'] ?? '') {
                'maritime', 'cargo' => 'primary',
                'rapide' => 'warning',
                'aerien' => 'accent',
                default => 'neutral',
            };
            $lockIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
            $trajetLockNotice = '<div class="finea-section-card" style="border-left: 6px solid #2563eb; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">'
                . '<div style="display:flex; align-items:center; gap:0.85rem;">'
                . '<span style="width:38px; height:38px; border-radius:50%; background:#dbeafe; color:#1d4ed8; display:inline-flex; align-items:center; justify-content:center;">' . $lockIcon . '</span>'
                . '<div><p class="rh-eyebrow" style="margin-bottom:0.25rem;">Trajet imposé par le sous-menu — non modifiable</p>'
                . '<h3 style="margin:0;">' . View::e($trajet['code']) . ' — ' . View::e($trajet['libelle']) . '</h3></div>'
                . '</div>'
                . Ui::badge(strtoupper((string) $trajet['type_transport']), $typeTone)
                . '</div>';
        }

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
        if ($trajet !== null) {
            // Trajet verrouillé : pas de dropdown de catégorie de fret, valeur imposée par le trajet.
            $typeExp = Form::field(
                'Catégorie de Fret',
                '<div class="finea-input" style="display:flex; align-items:center; gap:0.5rem; background:#f8fafc; color:#334155; font-weight:600;">'
                . View::e(strtoupper((string) $trajet['type_transport'])) . '<small style="font-weight:500; color:#64748b;">(verrouillé par le trajet ' . View::e($trajet['code']) . ')</small>'
                . '</div>'
            );
        } else {
            $fretOpts = [
                ['value' => 'export_aerien', 'label' => 'Export Aérien'],
                ['value' => 'export_maritime', 'label' => 'Export Maritime'],
                ['value' => 'import_aerien', 'label' => 'Import Aérien'],
                ['value' => 'import_maritime', 'label' => 'Import Maritime'],
            ];
            $typeExp = Form::select('type_expediteur', $fretOpts, 'export_aerien', ['label' => 'Catégorie de Fret']);
        }
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
                . '<td>' . Form::rawSelect('m_emballage[]', self::emballageOptions(), '') . '</td>'
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
            . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
            . ($trajet !== null ? Form::hidden('trajet_code', $trajet['code']) : '')
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">'
            . Ui::section('Informations Expéditeur', $expChoice . $expQuick)
            . Ui::section('Informations Destinataire', $destChoice . $destQuick)
            . '</div>'
            . '<div style="margin-top:2rem;">'
            . Ui::section('Détails de l\'expédition', $colisGrid . $marchandisesHtml)
            . '</div>'
            . '<div style="margin-top: 2rem; display:flex; gap:1rem; justify-content:flex-end;">'
            . Ui::button('Annuler', ['href' => 'colisage/parcels', 'variant' => 'secondary'])
            . '<button type="submit" class="finea-button finea-button--accent">'
            . ($trajet !== null ? 'Enregistrer le colis sur ' . View::e($trajet['code']) : 'Enregistrer le colis')
            . '</button>'
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
            . '                + \'<td><select class="finea-select" name="m_emballage[]">'
            . '<option value="">-- Sélectionner --</option>'
            . '<option value="Carton">Carton</option>'
            . '<option value="Sac">Sac</option>'
            . '<option value="Caisse en bois">Caisse en bois</option>'
            . '<option value="Palette">Palette</option>'
            . '<option value="Bidon/Fût">Bidon / Fût</option>'
            . '<option value="Valise">Valise</option>'
            . '<option value="Enveloppe">Enveloppe</option>'
            . '<option value="Autre">Autre</option>'
            . '</select></td>\''
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
            . $trajetLockNotice
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

        $headerActions = [
            Ui::qrCodeBadge((string) $colis['numero_tracking'], 60),
            Ui::badge($colis['statut'], $badgeTone),
            '<form method="post" action="' . View::url('colisage/parcels/' . $colis['id'] . '/facturer') . '" style="display:inline;">' . Ui::button('Facturer (1-Clic)', ['type' => 'submit', 'variant' => 'accent']) . '</form>',
            Ui::button('Facture', ['href' => 'colisage/parcels/' . $colis['id'] . '/facture', 'variant' => 'secondary', 'target' => '_blank']),
            Ui::button('Étiquette Thermique', ['href' => 'colisage/parcels/' . $colis['id'] . '/etiquette', 'variant' => 'secondary', 'target' => '_blank']),
            Ui::button('Retour à la liste', ['href' => 'colisage/parcels', 'variant' => 'secondary']),
        ];
        if (\App\Helpers\Auth::isAdmin() || \App\Helpers\Auth::hasRole('dg')) {
            $headerActions[] = Ui::deleteForm(
                'colisage/parcels/' . $colis['id'] . '/supprimer',
                'Supprimer définitivement le colis ' . $colis['numero_tracking'] . ' ? Cette action est irréversible.',
                ['label' => 'Supprimer', 'class' => '']
            );
        }

        $header = Ui::pageHeader(
            'Colis ' . $colis['numero_tracking'],
            'Visualisation et suivi opérationnel du colis.',
            [
                'eyebrow' => 'Suivi de Colis',
                'class' => 'rh-hero-white',
                'actions' => $headerActions,
            ]
        );

        $colisInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>N° Tracking :</strong> ' . View::e($colis['numero_tracking']) . '</p>'
            . '<p><strong>Poids total :</strong> ' . View::e((string) $colis['poids_total']) . ' kg</p>'
            . '<p><strong>Valeur déclarée :</strong> ' . View::e(number_format((float) $colis['valeur_declaree'], 0, ',', ' ')) . ' ' . View::e($colis['devise']) . '</p>'
            . '<p><strong>Statut Assurance :</strong> ' . (!empty($colis['assurance_souscrite']) ? '<span style="background:#dcfce7; color:#15803d; font-weight:700; padding:2px 8px; border-radius:4px;">ASSURÉ (Prime: ' . number_format((float)($colis['montant_assurance'] ?? 0), 0, ',', ' ') . ' FCFA - Couverture: ' . number_format((float)$colis['valeur_declaree'], 0, ',', ' ') . ' FCFA)</span>' : '<span style="background:#f1f5f9; color:#64748b; font-weight:600; padding:2px 8px; border-radius:4px;">Non souscrite</span>') . '</p>'
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
                . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
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
                    'export_aerien' => 'Export Aérien',
                    'export_maritime' => 'Export Maritime',
                    'import_aerien' => 'Import Aérien',
                    'import_maritime' => 'Import Maritime',
                    'colis_rapide_export' => 'Colis Rapide Export',
                    'colis_rapide_import' => 'Colis Rapide Import',
                    'dhl' => 'DHL Express',
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
            . '<h3 class="rh-step-title" style="margin-bottom:1rem; border:none; padding-bottom:0; color:#b45309;">Factures Impayées / En cours</h3>'
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
                    'AÉRIEN' => 'Aérien',
                    'MARITIME' => 'Maritime',
                    'TERRESTRE' => 'Route',
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
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Informations du module</h2></div>'
            . '<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.5rem;">' . $cards . '</div></section>';
    }

    // ─── GROUPAGE ────────────────────────────────────────────────────

    public static function groupageDetail(array $exp): string
    {
        $icon = match ($exp['type_transport']) {
            'AÉRIEN' => 'AÉRIEN', 'MARITIME' => 'MARITIME', 'TERRESTRE' => 'TERRESTRE', default => $exp['type_transport']
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
                . '<button type="submit" class="finea-button finea-button--accent" ' . $disabled . ' data-label="Démarrer l\'expédition (Départ du voyage)">Démarrer l\'expédition (Départ du voyage)</button></form>';
        } elseif ($exp['statut'] === 'EN_TRANSIT') {
            $workflowBtn = '<form method="post" action="' . View::url('colisage/groupage/' . $exp['id'] . '/arriver') . '" class="js-protect-form">'
                . '<button type="submit" class="finea-button finea-button--success" data-label="Marquer comme Arrivé à Destination (Dégroupage)">Marquer comme Arrivé à Destination (Dégroupage)</button></form>';
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
            . Form::selectSearch('statut', 'Statut du Colis', [
                ['value' => '', 'label' => 'Tous les statuts'],
                ['value' => 'EN_PRÉPARATION', 'label' => 'En préparation'],
                ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
                ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                ['value' => 'ARRIVÉ', 'label' => 'Arrivé en agence'],
                ['value' => 'RETIRÉ', 'label' => 'Retiré par le client']
            ], $filters['statut'] ?? '')
            . Form::selectSearch('type_expediteur', 'Catégorie Fret', [
                ['value' => '', 'label' => 'Toutes les catégories'],
                ['value' => 'export_aerien', 'label' => 'Export Aérien'],
                ['value' => 'export_maritime', 'label' => 'Export Maritime'],
                ['value' => 'import_aerien', 'label' => 'Import Aérien'],
                ['value' => 'import_maritime', 'label' => 'Import Maritime']
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
                . '<td>' . Form::rawSelect('m_emballage[]', self::emballageOptions(), '') . '</td>'
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

    public static function thermalLabelPage(array $colis): string
    {
        $trackingNum = (string) ($colis['numero_tracking'] ?? '');
        
        $rawDepAgency = (string) ($colis['agence_depart_name'] ?? '');
        $depAgency = !empty($rawDepAgency) && !in_array($rawDepAgency, ['Agence Départ', '—', 'N/A'], true)
            ? mb_strtoupper($rawDepAgency, 'UTF-8')
            : 'ABIDJAN SIÈGE';
        
        $rawArrAgency = (string) ($colis['agence_arrivee_name'] ?? '');
        $arrAgency = !empty($rawArrAgency) && !in_array($rawArrAgency, ['Agence Arrivée', '—', 'N/A'], true)
            ? mb_strtoupper($rawArrAgency, 'UTF-8')
            : 'BOBIGNY (FRANCE)';

        $destName = (string) ($colis['destinataire_name'] ?? 'Client Destinataire');
        $destPhone = (string) ($colis['destinataire_phone'] ?? '—');
        $weight = number_format((float) ($colis['poids_total'] ?? 0.0), 2, '.', '');
        $pkgCount = (int) ($colis['nombre_colis'] ?? 1);
        $trafic = (string) ($colis['trafic'] ?? $colis['type_expediteur'] ?? 'Groupage Aérien');
        $rayonCode = (string) ($colis['code_rayon'] ?? '');
        $statutRaw = (string) ($colis['statut'] ?? 'en_transit');
        $createdAt = !empty($colis['created_at']) ? date('d/m/Y H:i', strtotime((string) $colis['created_at'])) : date('d/m/Y H:i');

        $siteUrl = 'https://labelleporte.net';
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($siteUrl);

        $barcodePattern = '';
        for ($i = 0; $i < strlen($trackingNum); $i++) {
            $charVal = ord($trackingNum[$i]);
            $w1 = ($charVal % 3) + 1;
            $w2 = (($charVal + 1) % 2) + 1;
            $barcodePattern .= "<rect x='" . ($i * 11) . "' y='0' width='{$w1}' height='50' fill='#000'/>";
            $barcodePattern .= "<rect x='" . ($i * 11 + $w1 + 1) . "' y='0' width='{$w2}' height='50' fill='#000'/>";
        }

        $rayonText = !empty($rayonCode)
            ? 'RAYON DE L\'AGENCE : ' . View::e($rayonCode)
            : 'RAYON : EN ATTENTE AFFECTATION';

        $statusBannerText = match(strtolower($statutRaw)) {
            'livre' => 'COLIS LIVRÉ AU DESTINATAIRE',
            'recupere' => 'COLIS RETIRÉ EN AGENCE',
            'arrive' => 'ARRIVÉ EN AGENCE / DISPONIBLE',
            default => 'EN TRANSIT / EXPÉDITION EN COURS'
        };

        return '<!DOCTYPE html>'
            . '<html lang="fr">'
            . '<head><meta charset="UTF-8"><title>Etiquette_' . View::e($trackingNum) . '</title>'
            . '<style>'
            . '@page { size: 100mm 150mm; margin: 0; }'
            . '* { box-sizing: border-box; margin: 0; padding: 0; }'
            . 'body { font-family: "Inter", Arial, sans-serif; color: #000000; background-color: #f1f5f9; padding: 10px; display: flex; justify-content: center; }'
            . '.etiquette-card { width: 100mm; height: 150mm; background: #ffffff; border: 2px solid #000; padding: 8px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }'
            . '.etiquette-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; }'
            . '.logo-title { font-size: 16px; font-weight: 900; letter-spacing: 0.5px; }'
            . '.logo-subtitle { font-size: 8px; font-weight: 700; text-transform: uppercase; }'
            . '.trafic-badge { background: #000; color: #fff; padding: 3px 6px; font-size: 9px; font-weight: 800; border-radius: 3px; text-transform: uppercase; }'
            
            // Rayon Banner à haut du tableau
            . '.rayon-banner { background: #ffcc00; border: 2px solid #000; padding: 5px; text-align: center; font-weight: 900; font-size: 11px; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }'
            
            // Tableau Départ / Destination divisé en 2
            . '.route-table { display: flex; border: 2px solid #000; margin-top: 4px; background: #ffffff; }'
            . '.route-cell { flex: 1; padding: 6px; text-align: center; min-height: 48px; display: flex; flex-direction: column; justify-content: center; }'
            . '.route-depart { border-right: 2px solid #000; background: #f8fafc; }'
            . '.route-dest { background: #ffffff; }'
            . '.route-label { font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 2px; }'
            . '.route-city { font-size: 12px; font-weight: 900; text-transform: uppercase; line-height: 1.15; color: #000000; }'
            
            // Code-barres centré
            . '.tracking-block { text-align: center; border: 2px solid #000; padding: 6px 4px; margin-top: 4px; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }'
            . '.tracking-code { font-size: 22px; font-weight: 900; letter-spacing: 1.5px; font-family: "Arial Black", "Impact", "Inter", sans-serif; text-transform: uppercase; margin-bottom: 2px; text-align: center; }'
            . '.barcode-container { display: flex; justify-content: center; align-items: center; margin: 4px auto; overflow: hidden; height: 45px; width: 100%; text-align: center; }'
            . '.barcode-container svg { display: block; margin: 0 auto; }'
            . '.barcode-sub { font-size: 7px; font-weight: 800; text-align: center; letter-spacing: 0.5px; }'
            
            . '.status-banner { background: #ffcc00; border: 2px solid #000; padding: 4px; text-align: center; font-weight: 900; font-size: 10px; margin-top: 4px; text-transform: uppercase; }'
            . '.middle-grid { display: flex; justify-content: center; align-items: center; margin-top: 4px; border: 2px solid #000; padding: 8px; min-height: 85px; }'
            . '.qr-box { text-align: center; }'
            . '.qr-img { width: 70px; height: 70px; display: block; margin: 0 auto; }'
            . '.qr-sub { font-size: 8px; font-weight: 800; margin-top: 4px; letter-spacing: 0.5px; }'
            . '.metrics-row { display: flex; border: 2px solid #000; margin-top: 4px; text-align: center; font-size: 9px; font-weight: 700; }'
            . '.metric-cell { flex: 1; padding: 4px; border-right: 1px solid #000; }'
            . '.metric-cell:last-child { border-right: none; }'
            . '.metric-value { font-size: 13px; font-weight: 900; }'
            . '.print-btn-bar { position: fixed; top: 15px; right: 15px; display: flex; gap: 10px; }'
            . '.print-btn { background: #0f172a; color: #fff; border: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.15); }'
            . '.print-btn:hover { background: #1e293b; }'
            . '@media print { body { background: #ffffff; padding: 0; } .etiquette-card { border: 1px solid #000; box-shadow: none; width: 100mm; height: 150mm; } .print-btn-bar { display: none; } }'
            . '</style></head>'
            . '<body>'
            . '<div class="print-btn-bar"><button class="print-btn" onclick="window.print()">Imprimer l\'Étiquette (4x6")</button></div>'
            . '<div class="etiquette-card">'
            . '<div class="etiquette-header"><div><div class="logo-title">LBP LOGISTICS</div><div class="logo-subtitle">Fret & Colisage International</div></div><div class="trafic-badge">' . View::e($trafic) . '</div></div>'
            
            // 1. Rayon de l'agence au-dessus du tableau Départ / Destination
            . '<div class="rayon-banner">' . $rayonText . '</div>'
            
            // 2. Tableau Départ & Destination divisé en 2 (tous deux en gras)
            . '<div class="route-table">'
            . '<div class="route-cell route-depart"><div class="route-label">DÉPART :</div><div class="route-city">' . View::e($depAgency) . '</div></div>'
            . '<div class="route-cell route-dest"><div class="route-label">DESTINATION :</div><div class="route-city">' . View::e($arrAgency) . '</div></div>'
            . '</div>'
            
            // 3. Code-barres centré
            . '<div class="tracking-block">'
            . '<div class="tracking-code">' . View::e($trackingNum) . '</div>'
            . '<div class="barcode-container"><svg width="220" height="45" viewBox="0 0 220 45">' . $barcodePattern . '</svg></div>'
            . '<div class="barcode-sub">CODE-BARRES OFFICIEL LBP</div>'
            . '</div>'
            
            // Bannière Statut
            . '<div class="status-banner">' . $statusBannerText . '</div>'
            
            // QR Code
            . '<div class="middle-grid"><div class="qr-box"><img src="' . $qrCodeUrl . '" class="qr-img" alt="QR Code Web"><div class="qr-sub">LABELLEPORTE.NET</div></div></div>'
            
            // Pied de fiche (Metriques)
            . '<div class="metrics-row">'
            . '<div class="metric-cell"><div>POIDS TOTAL</div><div class="metric-value">' . $weight . ' kg</div></div>'
            . '<div class="metric-cell"><div>SÉQUENCE</div><div class="metric-value">1 / ' . $pkgCount . '</div></div>'
            . '<div class="metric-cell"><div>DATE SAISIE</div><div style="font-size:9.5px; margin-top:2px;">' . $createdAt . '</div></div>'
            . '</div>'
            
            . '</div>'
            . '<script>if (window.location.search.indexOf("autoprint") !== -1) { window.addEventListener("load", function() { window.print(); }); }</script>'
            . '</body></html>';
    }

    public static function groupageManifestPage(array $exp): string
    {
        $ref = (string) ($exp['reference'] ?? '');
        $typeTransport = strtoupper((string) ($exp['type_transport'] ?? 'AÉRIEN'));
        $depAgency = (string) ($exp['agence_depart_name'] ?? 'Agence Départ');
        $arrAgency = (string) ($exp['agence_arrivee_name'] ?? 'Agence Destination');
        $dateDepart = !empty($exp['date_depart_prevue']) ? date('d/m/Y H:i', strtotime((string) $exp['date_depart_prevue'])) : 'Prévue sous peu';
        $dateArrivee = !empty($exp['date_arrivee_estimee']) ? date('d/m/Y H:i', strtotime((string) $exp['date_arrivee_estimee'])) : 'En cours';
        $parcels = $exp['parcels'] ?? [];
        $createdAt = !empty($exp['created_at']) ? date('d/m/Y à H:i', strtotime((string) $exp['created_at'])) : date('d/m/Y H:i');

        $totalColisCount = 0;
        $totalPoids = 0.0;
        $totalValeurXof = 0.0;
        $totalValeurEur = 0.0;

        foreach ($parcels as $p) {
            $totalColisCount += (int) ($p['nombre_colis'] ?? 1);
            $totalPoids += (float) ($p['poids_total'] ?? 0.0);
            $val = (float) ($p['montant_total'] ?? $p['valeur_declaree'] ?? 0.0);
            $valEur = (float) ($p['montant_total_eur'] ?? 0.0);
            $totalValeurXof += $val;
            $totalValeurEur += $valEur;
        }

        $verificationUrl = View::url('site/tracking?ref=' . urlencode($ref));
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($verificationUrl);

        $tableRows = '';
        if (empty($parcels)) {
            $tableRows = '<tr><td colspan="7" style="text-align:center; padding: 20px; color:#64748b;">Aucun colis dans cette expédition.</td></tr>';
        } else {
            $i = 1;
            foreach ($parcels as $p) {
                $pWeight = (float) ($p['poids_total'] ?? 0.0);
                $pValXof = (float) ($p['montant_total'] ?? $p['valeur_declaree'] ?? 0.0);
                $pValEur = (float) ($p['montant_total_eur'] ?? 0.0);

                $tableRows .= '<tr>'
                    . '<td>' . $i++ . '</td>'
                    . '<td><strong>' . View::e($p['numero_tracking']) . '</strong></td>'
                    . '<td>' . View::e($p['expediteur_name'] ?? 'Expéditeur') . '</td>'
                    . '<td>' . View::e($p['destinataire_name'] ?? 'Destinataire') . ' (' . View::e($p['destinataire_phone'] ?? '') . ')</td>'
                    . '<td style="text-align:center;">' . ((int) ($p['nombre_colis'] ?? 1)) . '</td>'
                    . '<td style="text-align:right;"><strong>' . number_format($pWeight, 2, ',', ' ') . ' kg</strong></td>'
                    . '<td style="text-align:right;">' . number_format($pValXof, 0, ',', ' ') . ' XOF' . ($pValEur > 0 ? '<br><small>(' . number_format($pValEur, 2, ',', ' ') . ' €)</small>' : '') . '</td>'
                    . '</tr>';
            }
        }

        return '<!DOCTYPE html>'
            . '<html lang="fr">'
            . '<head><meta charset="UTF-8"><title>Manifeste_Douane_' . View::e($ref) . '</title>'
            . '<style>'
            . '@page { size: A4 portrait; margin: 12mm; }'
            . '* { box-sizing: border-box; margin: 0; padding: 0; }'
            . 'body { font-family: "Helvetica Neue", Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 20px; font-size: 11px; }'
            . '.manifest-card { background: #ffffff; border: 2px solid #0f172a; padding: 20px; width: 100%; max-width: 210mm; margin: 0 auto; min-height: 270mm; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }'
            . '.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px double #0f172a; padding-bottom: 12px; }'
            . '.brand-title { font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: 0.5px; }'
            . '.brand-sub { font-size: 9px; font-weight: 700; color: #475569; text-transform: uppercase; }'
            . '.doc-title-box { text-align: right; }'
            . '.doc-title { font-size: 16px; font-weight: 900; color: #b91c1c; text-transform: uppercase; letter-spacing: 0.5px; }'
            . '.doc-ref { font-size: 12px; font-weight: 800; font-family: monospace; color: #0f172a; margin-top: 2px; }'
            . '.grid-info { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-top: 15px; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 12px; border-radius: 6px; }'
            . '.info-item { margin-bottom: 4px; }'
            . '.info-label { font-weight: 800; color: #475569; text-transform: uppercase; font-size: 9px; }'
            . '.info-val { font-weight: 700; color: #0f172a; font-size: 11px; }'
            . '.transport-badge { display: inline-block; background: #0f172a; color: #fff; padding: 4px 8px; font-size: 10px; font-weight: 900; border-radius: 4px; text-transform: uppercase; margin-top: 4px; }'
            . '.table-section { margin-top: 15px; flex: 1; }'
            . '.manifest-table { width: 100%; border-collapse: collapse; margin-top: 8px; }'
            . '.manifest-table th { background: #0f172a; color: #ffffff; padding: 6px 8px; text-align: left; font-size: 9px; font-weight: 800; text-transform: uppercase; border: 1px solid #0f172a; }'
            . '.manifest-table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; vertical-align: middle; }'
            . '.manifest-table tr:nth-child(even) { background: #f8fafc; }'
            . '.summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: #0f172a; color: #fff; padding: 10px; border-radius: 6px; margin-top: 15px; text-align: center; }'
            . '.summary-item { font-size: 10px; }'
            . '.summary-val { font-size: 14px; font-weight: 900; color: #facc15; margin-top: 2px; }'
            . '.signatures-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 20px; border-top: 2px solid #0f172a; padding-top: 12px; }'
            . '.sig-box { border: 1px dashed #94a3b8; height: 75px; padding: 6px; border-radius: 4px; text-align: center; position: relative; }'
            . '.sig-title { font-size: 8px; font-weight: 800; text-transform: uppercase; color: #475569; }'
            . '.qr-container { display: flex; align-items: center; justify-content: center; height: 50px; margin-top: 4px; }'
            . '.print-btn-bar { position: fixed; top: 15px; right: 15px; display: flex; gap: 10px; }'
            . '.print-btn { background: #0f172a; color: #fff; border: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.15); }'
            . '@media print { body { background: #fff; padding: 0; } .manifest-card { border: none; box-shadow: none; padding: 0; width: 100%; max-width: none; } .print-btn-bar { display: none; } }'
            . '</style></head>'
            . '<body>'
            . '<div class="print-btn-bar"><button class="print-btn" onclick="window.print()">🖨️ Imprimer le Manifeste Douane (A4)</button></div>'
            . '<div class="manifest-card">'
            . '<div class="header"><div><div class="brand-title">LA BELLE PORTE LOGISTICS</div><div class="brand-sub">Compagnie de Transit & Fret International — MAWB / LTA</div></div><div class="doc-title-box"><div class="doc-title">MANIFESTE DE CHARGE</div><div class="doc-ref">N° REF : ' . View::e($ref) . '</div></div></div>'
            . '<div class="grid-info"><div><div class="info-item"><span class="info-label">ITINÉRAIRE D\'EXPÉDITION :</span> <span class="info-val">' . View::e($depAgency) . ' ➔ ' . View::e($arrAgency) . '</span></div>'
            . '<div class="info-item"><span class="info-label">DATES :</span> <span class="info-val">Départ : ' . $dateDepart . ' | Arrivée estimée : ' . $dateArrivee . '</span></div></div>'
            . '<div><div class="info-label">MODE DE TRANSPORT :</div><div class="transport-badge">✈️ FRET ' . View::e($typeTransport) . '</div></div></div>'
            . '<div class="table-section"><div style="font-size:11px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">RELEVÉ CONSOLIDÉ DES COLIS & MARCHANDISES (' . count($parcels) . ' EXPÉDITIONS)</div>'
            . '<table class="manifest-table"><thead><tr><th style="width:30px;">N°</th><th>N° TRACKING</th><th>EXPÉDITEUR</th><th>DESTINATAIRE & CONTACT</th><th style="text-align:center;">NBRE COLIS</th><th style="text-align:right;">POIDS (KG)</th><th style="text-align:right;">VALEUR DÉCLARÉE</th></tr></thead>'
            . '<tbody>' . $tableRows . '</tbody></table></div>'
            . '<div class="summary-box"><div class="summary-item">TOTAL EXPÉDITIONS<div class="summary-val">' . count($parcels) . '</div></div>'
            . '<div class="summary-item">TOTAL COLIS (UNIS)<div class="summary-val">' . $totalColisCount . '</div></div>'
            . '<div class="summary-item">POIDS BRUT CONSOLIDÉ<div class="summary-val">' . number_format($totalPoids, 2, ',', ' ') . ' kg</div></div>'
            . '<div class="summary-item">VALEUR TOTALE DÉCLARÉE<div class="summary-val">' . number_format($totalValeurXof, 0, ',', ' ') . ' XOF</div></div></div>'
            . '<div class="signatures-grid"><div class="sig-box"><div class="sig-title">RÉCEPTION & SCEAU COMPAGNIE LBP</div></div>'
            . '<div class="sig-box"><div class="sig-title">INSPECTION & VISATEUR DOUANE</div></div>'
            . '<div class="sig-box"><div class="sig-title">AUTHENTIFICATION NUMÉRIQUE</div><div class="qr-container"><img src="' . $qrCodeUrl . '" style="height:45px; width:45px;" alt="QR Code Verification"></div></div></div>'
            . '<div style="margin-top:10px; font-size:8px; color:#64748b; text-align:center;">Document officiel de charge généré le ' . $createdAt . ' — ERP La Belle Porte Logistics</div>'
            . '</div></body></html>';
    }
}
