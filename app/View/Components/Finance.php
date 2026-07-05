<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Auth;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Finance
{
    /**
     * Rendu de la liste des factures.
     */
    public static function facturesTable(array $factures, array $filters = [], array $agences = []): string
    {
        $header = Ui::pageHeader(
            'Gestion de la Facturation',
            'Saisie et suivi des factures clients, relances et états de paiement.',
            [
                'eyebrow' => 'Invoicing & Receivables',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Créer une Facture', [
                        'href' => 'finance/factures/nouveau',
                        'variant' => 'accent',
                    ])
                ]
            ]
        );

        // Formulaire de filtre
        $q = Form::input('q', [
            'label' => 'Recherche',
            'value' => (string) ($filters['q'] ?? ''),
            'placeholder' => 'N° Facture, Client...',
        ]);

        $statusOpts = [
            ['value' => '', 'label' => 'Tous les statuts'],
            ['value' => 'emise', 'label' => 'Émise'],
            ['value' => 'partiellement_payee', 'label' => 'Partiellement Payée'],
            ['value' => 'payee', 'label' => 'Payée'],
            ['value' => 'en_retard', 'label' => 'En Retard'],
            ['value' => 'annulee', 'label' => 'Annulée'],
        ];
        $status = Form::selectSearch('statut', $statusOpts, $filters['statut'] ?? '', ['label' => 'Statut']);

        $agenceOpts = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($agences as $a) {
            $agenceOpts[] = ['value' => (string) $a['id'], 'label' => $a['name']];
        }
        $agenceSelect = Form::selectSearch('agence_id', $agenceOpts, $filters['agence_id'] ?? '', ['label' => 'Agence']);

        $filterGrid = '<div class="rh-personnel-filter-grid">' . $q . $status . $agenceSelect . '</div>';

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Filtrer'
            . '</button>';

        $resetBtn = '<a href="' . View::url('finance/factures') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>';
        $form = '<form method="get" action="' . View::url('finance/factures') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . '</form>';

        // Tableau
        $tableHtml = '';
        if ($factures === []) {
            $tableHtml = Ui::emptyState(
                'Aucune facture trouvée',
                'Aucune fiche ne correspond aux critères sélectionnés.'
            );
        } else {
            $rows = '';
            foreach ($factures as $f) {
                $badgeTone = match($f->statut) {
                    'payee' => 'success',
                    'partiellement_payee' => 'warning',
                    'emise' => 'info',
                    'en_retard' => 'danger',
                    default => 'secondary'
                };
                $badge = Ui::badge(str_replace('_', ' ', ucfirst($f->statut)), $badgeTone);

                $actionsStr = Ui::button('Consulter / Encaisser', [
                    'href' => 'finance/factures/' . $f->id,
                    'variant' => 'secondary',
                    'class' => 'finea-button-sm'
                ]);

                $tauxStr = $f->tauxChange !== null && $f->devise !== 'XOF' ? ' <small style="color:#64748b;">(Taux: ' . number_format($f->tauxChange, 2, ',', '.') . ')</small>' : '';

                $rows .= '<tr>'
                    . '<td><strong>' . View::e($f->numeroFacture) . '</strong></td>'
                    . '<td>' . View::e($f->colis_tracking ?? 'Colis ID: ' . $f->colisId) . '</td>'
                    . '<td>' . View::e($f->client_name ?? 'Client ID: ' . $f->clientId) . '</td>'
                    . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($f->montantTotal, 2, ',', ' ')) . ' ' . View::e($f->devise) . $tauxStr . '</td>'
                    . '<td style="text-align:right; color:#15803d;">' . View::e(number_format($f->montantEncaisse, 2, ',', ' ')) . ' ' . View::e($f->devise) . '</td>'
                    . '<td style="text-align:right; color:#b91c1c; font-weight:600;">' . View::e(number_format($f->montantRestant, 2, ',', ' ')) . ' ' . View::e($f->devise) . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsStr . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>N° Facture</th>'
                . '<th>Colis</th>'
                . '<th>Client</th>'
                . '<th style="text-align:right;">Montant Total</th>'
                . '<th style="text-align:right;">Montant Encaissé</th>'
                . '<th style="text-align:right;">Reste à payer</th>'
                . '<th>Statut</th>'
                . '<th>Actions</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Formulaire de création de facture.
     */
    public static function factureCreateForm(array $colisSansFacture): string
    {
        $header = Ui::pageHeader(
            'Créer une Facture',
            'Générer une nouvelle facture à partir d\'un colis réceptionné.',
            [
                'eyebrow' => 'Nouvelle Facture',
                'class' => 'rh-hero-white',
            ]
        );

        $colisOpts = [['value' => '', 'label' => '-- Sélectionner un colis à facturer --']];
        foreach ($colisSansFacture as $c) {
            $colisOpts[] = [
                'value' => (string) $c['id'],
                'label' => $c['numero_tracking'] . ' - ' . $c['expediteur_name'] . ' (' . number_format((float)$c['montant_total'], 2, ',', ' ') . ' ' . $c['devise'] . ')'
            ];
        }

        $colisSelect = Form::selectSearch('colis_id', $colisOpts, '', [
            'label' => 'Colis à facturer',
            'required' => true,
            'id' => 'colis_id_selector'
        ]);

        $currencyOpts = [
            ['value' => 'XOF', 'label' => 'Franc CFA (XOF)'],
            ['value' => 'EUR', 'label' => 'Euro (EUR)'],
        ];
        $currencySelect = Form::select('devise', $currencyOpts, 'XOF', [
            'label' => 'Devise de facturation',
            'required' => true,
            'id' => 'devise_selector'
        ]);

        $tauxChangeInput = Form::input('taux_change', [
            'label' => 'Taux de conversion (si devise étrangère)',
            'type' => 'number',
            'step' => '0.000001',
            'min' => '0.000001',
            'placeholder' => 'Ex: 655.957',
            'id' => 'taux_change_input'
        ]);

        $formContent = '<form method="post" action="' . View::url('finance/factures/enregistrer') . '" class="js-protect-form">'
            . '<div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">'
            . $colisSelect
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">'
            . $currencySelect . $tauxChangeInput
            . '</div>'
            . '</div>'
            . '<div style="margin-top:2.5rem; display:flex; gap:1rem; justify-content:flex-end;">'
            . Ui::button('Annuler', ['href' => 'finance/factures', 'variant' => 'secondary'])
            . '<button type="submit" class="finea-button finea-button--accent">Générer la Facture</button>'
            . '</div>'
            . '</form>';

        $js = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const devSelect = document.getElementById('devise_selector');
                const tauxInput = document.getElementById('taux_change_input');
                
                function toggleTaux() {
                    if (devSelect.value === 'XOF') {
                        tauxInput.value = '';
                        tauxInput.disabled = true;
                        tauxInput.removeAttribute('required');
                    } else {
                        tauxInput.disabled = false;
                        tauxInput.setAttribute('required', 'required');
                        tauxInput.value = '655.957000';
                    }
                }
                
                if (devSelect) {
                    devSelect.addEventListener('change', toggleTaux);
                    toggleTaux();
                }
            });
        </script>";

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . Ui::section('Détails de la facturation', $formContent)
            . '</div></div>' . $js;
    }

    /**
     * Fiche d'une facture + encaissement.
     */
    public static function factureShowPage(Facture $facture, array $paiements, array $callbacks, array $colis, array $client): string
    {
        $badgeTone = match($facture->statut) {
            'payee' => 'success',
            'partiellement_payee' => 'warning',
            'emise' => 'info',
            'en_retard' => 'danger',
            default => 'secondary'
        };
        $badge = Ui::badge(str_replace('_', ' ', ucfirst($facture->statut)), $badgeTone);

        $header = Ui::pageHeader(
            'Facture ' . $facture->numeroFacture,
            'Consultation, encaissement physique et suivi en temps réel.',
            [
                'eyebrow' => 'Détails Facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    $badge,
                    Ui::button('Retour', ['href' => 'finance/factures', 'variant' => 'secondary'])
                ]
            ]
        );

        // Colonne 1: Infos générales de la facture
        $tauxStr = $facture->tauxChange !== null && $facture->devise !== 'XOF' ? '<p><strong>Taux de change figé :</strong> 1 EUR = ' . number_format($facture->tauxChange, 4, ',', '.') . ' FCFA</p>' : '';
        $factureInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>N° Facture :</strong> ' . View::e($facture->numeroFacture) . '</p>'
            . '<p><strong>Date d\'émission :</strong> ' . View::e($facture->dateEmission) . '</p>'
            . '<p><strong>Date d\'échéance du solde :</strong> ' . View::e($facture->dateEcheanceSolde ?? 'Aucune échéance') . '</p>'
            . $tauxStr
            . '</div>'
            . '<div>'
            . '<p><strong>Montant Total :</strong> <span style="font-size:1.15rem; font-weight:700; color:#1e293b;">' . View::e(number_format($facture->montantTotal, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '<p><strong>Montant Encaissé :</strong> <span style="font-size:1.1rem; font-weight:700; color:#16a34a;">' . View::e(number_format($facture->montantEncaisse, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '<p><strong>Reste à payer (Solde) :</strong> <span style="font-size:1.25rem; font-weight:700; color:#dc2626;">' . View::e(number_format($facture->montantRestant, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '</div>'
            . '</div>';

        // Infos colis
        $colisInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>Colis :</strong> ' . View::e($colis['numero_tracking']) . '</p>'
            . '<p><strong>Description :</strong> ' . View::e($colis['description'] ?? '—') . '</p>'
            . '<p><strong>Poids total :</strong> ' . View::e((string) $colis['poids_total']) . ' kg</p>'
            . '</div>'
            . '<div>'
            . '<p><strong>Client :</strong> ' . View::e($client['name']) . '</p>'
            . '<p><strong>Téléphone client :</strong> ' . View::e($client['phone'] ?? '—') . '</p>'
            . '<p><strong>Adresse client :</strong> ' . View::e($client['address'] ?? '—') . '</p>'
            . '</div>'
            . '</div>';

        // Liste des encaissements physiques/comptant
        $payRows = '';
        foreach ($paiements as $p) {
            $payRows .= '<tr>'
                . '<td>' . View::e($p->datePaiement) . '</td>'
                . '<td>' . View::e(strtoupper($p->mode)) . '</td>'
                . '<td>' . View::e(ucfirst($p->type)) . '</td>'
                . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($p->montant, 2, ',', ' ')) . ' ' . View::e($p->devise) . '</td>'
                . '<td>' . Ui::button('Imprimer Reçu', ['href' => 'finance/paiements/' . $p->id . '/recu', 'variant' => 'secondary', 'class' => 'finea-button-sm']) . '</td>'
                . '</tr>';
        }
        $payTable = '<table class="finea-table" style="margin-top:0.5rem;">'
            . '<thead><tr><th>Date</th><th>Mode</th><th>Type</th><th style="text-align:right;">Montant</th><th>Actions</th></tr></thead>'
            . '<tbody>' . ($payRows ?: '<tr><td colspan="5">Aucun encaissement enregistré.</td></tr>') . '</tbody>'
            . '</table>';

        // Liste des callbacks Mobile Money
        $callbackRows = '';
        foreach ($callbacks as $cb) {
            $statusBadge = match($cb->statut) {
                'success' => 'success',
                'failed' => 'danger',
                'unmatched' => 'warning',
                default => 'secondary'
            };
            $callbackRows .= '<tr>'
                . '<td>' . View::e($cb->createdAt) . '</td>'
                . '<td>' . View::e(strtoupper($cb->provider)) . '</td>'
                . '<td><code>' . View::e($cb->transactionReference) . '</code></td>'
                . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($cb->montant, 2, ',', ' ')) . ' ' . View::e($cb->devise) . '</td>'
                . '<td>' . Ui::badge(strtoupper($cb->statut), $statusBadge) . '</td>'
                . '</tr>';
        }
        $callbackTable = '<table class="finea-table" style="margin-top:0.5rem; font-size:0.85rem;">'
            . '<thead><tr><th>Horodatage</th><th>Opérateur</th><th>ID Transaction</th><th style="text-align:right;">Montant</th><th>État</th></tr></thead>'
            . '<tbody>' . ($callbackRows ?: '<tr><td colspan="5">Aucun callback reçu.</td></tr>') . '</tbody>'
            . '</table>';

        // Formulaire d'encaissement physique (si la facture n'est pas entièrement payée ou annulée)
        $encaissementForm = '';
        if ($facture->statut !== 'payee' && $facture->statut !== 'annulee') {
            $modeOpts = [
                ['value' => 'especes', 'label' => 'Espèces (Physique)'],
                ['value' => 'mobile_money', 'label' => 'Mobile Money (Wave, Orange, MTN)'],
                ['value' => 'carte', 'label' => 'Carte Bancaire'],
            ];

            $formFields = '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">'
                . Form::input('montant', [
                    'label' => 'Montant à encaisser (' . $facture->devise . ')',
                    'type' => 'number',
                    'step' => '0.01',
                    'max' => $facture->montantRestant,
                    'value' => $facture->montantRestant,
                    'required' => true
                ])
                . Form::select('mode', $modeOpts, 'especes', ['label' => 'Mode d\'encaissement', 'required' => true])
                . Form::input('date_echeance_solde', [
                    'label' => 'Nouvelle échéance de solde (si partiel)',
                    'type' => 'date',
                    'value' => $facture->dateEcheanceSolde ? date('Y-m-d', strtotime($facture->dateEcheanceSolde)) : ''
                ])
                . '</div>';

            $encaissementForm = '<form method="post" action="' . View::url('finance/factures/' . $facture->id . '/encaisser') . '" class="js-protect-form" style="margin-top:2rem;">'
                . '<h3>Enregistrer un Encaissement Physique</h3>'
                . '<p style="font-size:0.85rem; color:#64748b; margin-bottom:1rem;">Remplir ce formulaire si le client règle directement au guichet.</p>'
                . $formFields
                . '<div style="margin-top: 1rem; display:flex; justify-content:flex-end;">'
                . '<button type="submit" class="finea-button finea-button--accent">Valider l\'encaissement</button>'
                . '</div>'
                . '</form>';
        }

        // Section QR Code & Lien de Relance
        $qrCodeSection = '';
        if ($facture->statut !== 'payee' && $facture->statut !== 'annulee') {
            $qrUrl = View::url('api/paiements/qrcode/' . $facture->id);
            $qrCodeSection = '<div style="display:grid; grid-template-columns: 1fr 2fr; gap:2rem; margin-top:2rem;">'
                . '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem; display:flex; flex-direction:column; align-items:center; justify-content:center;">'
                . '<h4 style="margin-bottom:0.5rem; text-align:center;">QR Code de Paiement</h4>'
                . '<div style="background:#fff; padding:0.75rem; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:0.75rem;">'
                // Image factice/générée par l'API de QR code
                . '<img src="' . $qrUrl . '" alt="QR Code" style="width:140px; height:140px;">'
                . '</div>'
                . '<small style="color:#64748b; text-align:center; font-size:0.75rem;">Valable jusqu\'au ' . ($facture->dateExpirationQr ? date('d/m/Y à H:i', strtotime($facture->dateExpirationQr)) : '—') . '</small>'
                . '</div>'
                . '<div>'
                . '<h4>Rappels & Relances automatiques</h4>'
                . '<p style="color:#475569; font-size:0.85rem; margin-bottom:1rem;">Envoyer un lien de paiement dynamique et un avis de relance par SMS/WhatsApp au client pour régler le solde restant.</p>'
                . '<form method="post" action="' . View::url('finance/factures/' . $facture->id . '/relancer') . '" class="js-protect-form">'
                . '<div style="display:flex; gap:0.5rem; align-items:center;">'
                . Form::select('canal', [
                    ['value' => 'sms', 'label' => '💬 SMS Pro'],
                    ['value' => 'whatsapp', 'label' => '🟢 WhatsApp Business'],
                    ['value' => 'email', 'label' => '📧 Courriel (Email)'],
                ], 'whatsapp', ['required' => true])
                . '<button type="submit" class="finea-button finea-button--primary">Envoyer le Rappel de Solde</button>'
                . '</div>'
                . '</form>'
                . '</div>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">'
            . Ui::section('Informations Financières', $factureInfo)
            . Ui::section('Colis & Client Associés', $colisInfo)
            . $qrCodeSection
            . Ui::section('Historique des Encaissements Physiques', $payTable)
            . Ui::section('Transactions et Webhooks Mobile Money', $callbackTable)
            . $encaissementForm
            . '</div>'
            . '</div></div>';
    }

    /**
     * Rendu des demandes de dépenses prestataires (Séparation des tâches).
     */
    public static function demandesPaiementPage(array $demandes, array $prestataires): string
    {
        $header = Ui::pageHeader(
            'Dépenses Prestataires',
            'Demandes de règlements de prestataires régionaux et décaissements de caisse centrale.',
            [
                'eyebrow' => 'Supplier Payouts & Expenditures',
                'class' => 'rh-hero-white',
            ]
        );

        // Section création de demande (Superviseur Régional)
        $formCreate = '';
        if (Auth::hasAnyRole(['superviseur_regional', 'superviseur_general'])) {
            $prestOpts = [['value' => '', 'label' => '-- Sélectionner le prestataire --']];
            foreach ($prestataires as $p) {
                $prestOpts[] = ['value' => (string) $p['id'], 'label' => $p['nom'] . ' (' . $p['type_prestation'] . ')'];
            }

            $formCreate = '<form method="post" action="' . View::url('finance/depenses/enregistrer') . '" class="js-protect-form" style="margin-bottom:2rem;">'
                . '<h3>Nouvelle Demande de Règlement</h3>'
                . '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:0.75rem;">'
                . Form::select('prestataire_id', $prestOpts, '', ['label' => 'Prestataire', 'required' => true])
                . Form::input('montant', ['label' => 'Montant à payer', 'type' => 'number', 'step' => '0.01', 'required' => true])
                . Form::select('devise', [['value' => 'XOF', 'label' => 'XOF (Franc CFA)'], ['value' => 'EUR', 'label' => 'EUR (Euro)']], 'XOF', ['label' => 'Devise', 'required' => true])
                . '</div>'
                . '<div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::input('motif', ['label' => 'Motif détaillé de la dépense', 'required' => true])
                . Form::input('justificatif_url', ['label' => 'Lien de la facture justificative'])
                . '</div>'
                . '<div style="margin-top:1rem; display:flex; justify-content:flex-end;">'
                . '<button type="submit" class="finea-button finea-button--accent">Soumettre la demande</button>'
                . '</div>'
                . '</form>';
        }

        // Tableau des demandes de dépenses
        $tableHtml = '';
        if ($demandes === []) {
            $tableHtml = Ui::emptyState(
                'Aucune demande de paiement',
                'Aucun mouvement de décaissement enregistré.'
            );
        } else {
            $rows = '';
            foreach ($demandes as $d) {
                $badgeTone = match($d->statut) {
                    'payee' => 'success',
                    'approuvee' => 'primary',
                    'rejetee' => 'danger',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($d->statut), $badgeTone);

                $justifLink = $d->justificatifUrl ? '<a href="' . View::e($d->justificatifUrl) . '" target="_blank" style="color:#0284c7; text-decoration:underline;">Visualiser</a>' : '<span style="color:#94a3b8;">Aucun</span>';

                // Gestion des actions (Séparation des tâches)
                $actionsHtml = '—';
                if ($d->statut === 'en_attente') {
                    if (Auth::hasRole('caissiere_principale')) {
                        // On vérifie que la caissière principale n'est pas le superviseur qui a fait la demande !
                        if ($d->superviseurRegionalId !== Auth::id()) {
                            $actionsHtml = '<div style="display:flex; gap:0.5rem;">'
                                . '<form method="post" action="' . View::url('finance/depenses/' . $d->id . '/valider') . '" class="js-protect-form">'
                                . '<input type="hidden" name="decision" value="approuver">'
                                . '<button type="submit" class="finea-button finea-button--success finea-button-sm">Payer</button>'
                                . '</form>'
                                . '<form method="post" action="' . View::url('finance/depenses/' . $d->id . '/valider') . '" class="js-protect-form">'
                                . '<input type="hidden" name="decision" value="rejeter">'
                                . '<button type="submit" class="finea-button finea-button--danger finea-button-sm">Rejeter</button>'
                                . '</form>'
                                . '</div>';
                        } else {
                            $actionsHtml = '<span style="color:#e11d48; font-weight:600; font-size:0.8rem;">🚨 Blocage SoD (Auteur)</span>';
                        }
                    } else {
                        $actionsHtml = '<span style="color:#64748b; font-size:0.8rem;">En attente de caisse</span>';
                    }
                }

                $rows .= '<tr>'
                    . '<td>' . View::e($d->dateDemande) . '</td>'
                    . '<td>' . View::e($d->prestataire_name ?? 'Prestataire ID: ' . $d->prestataireId) . '</td>'
                    . '<td>' . View::e($d->motif) . '</td>'
                    . '<td style="text-align:right; font-weight:700; color:#b91c1c;">- ' . View::e(number_format($d->montant, 2, ',', ' ')) . ' ' . View::e($d->devise) . '</td>'
                    . '<td>' . $justifLink . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsHtml . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Prestataire</th>'
                . '<th>Motif</th>'
                . '<th style="text-align:right;">Montant</th>'
                . '<th>Justificatif</th>'
                . '<th>Statut</th>'
                . '<th>Actions (SoD)</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . ($formCreate !== '' ? Ui::section('Créer une Demande de Paiement', $formCreate) : '')
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Décaissements et Règlements</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Point de caisse et états journaliers.
     */
    public static function etatsJournaliersPage(array $reports, array $agences, ?array $activeReport = null): string
    {
        $header = Ui::pageHeader(
            'Points de Caisse',
            'Saisie des états de caisse quotidiens d\'agence, soumissions à 15h et verrous de consolidation.',
            [
                'eyebrow' => 'Daily Cash Closures',
                'class' => 'rh-hero-white',
            ]
        );

        $submissionForm = '';
        if (Auth::hasAnyRole(['caissiere', 'chef_agence'])) {
            $submissionForm = '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem; margin-bottom:2rem;">'
                . '<h3>Votre point de caisse du jour</h3>'
                . '<p style="font-size:0.85rem; color:#64748b; margin-bottom:1.5rem;">Veuillez soumettre ou mettre à jour votre point de caisse avant 15h locales.</p>';

            if ($activeReport) {
                $statutBadge = match($activeReport['statut']) {
                    'consolide' => 'success',
                    'soumis' => 'primary',
                    default => 'warning'
                };
                $submissionForm .= '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">'
                    . '<div><small style="color:#64748b;">Statut actuel :</small><br>' . Ui::badge(strtoupper($activeReport['statut']), $statutBadge) . '</div>'
                    . '<div><small style="color:#64748b;">Totaux facturés :</small><br><strong>' . number_format($activeReport['total_facture_xof'], 2, ',', ' ') . ' XOF</strong> / <strong>' . number_format($activeReport['total_facture_eur'], 2, ',', ' ') . ' EUR</strong></div>'
                    . '<div><small style="color:#64748b;">Totaux encaissés (solde caisse) :</small><br><strong style="color:#16a34a;">' . number_format($activeReport['total_encaisse_xof'], 2, ',', ' ') . ' XOF</strong> / <strong style="color:#16a34a;">' . number_format($activeReport['total_encaisse_eur'], 2, ',', ' ') . ' EUR</strong></div>'
                    . '</div>';

                if ($activeReport['statut'] === 'brouillon') {
                    $submissionForm .= '<form method="post" action="' . View::url('finance/clotures/soumettre') . '" class="js-protect-form">'
                        . '<button type="submit" class="finea-button finea-button--accent">Soumettre le point (Verrouillage à 15h)</button>'
                        . '</form>';
                } else {
                    $submissionForm .= '<p style="color:#16a34a; font-weight:600;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline; margin-right:0.25rem;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Point soumis et verrouillé.</p>';
                }
            } else {
                $submissionForm .= '<form method="post" action="' . View::url('finance/clotures/soumettre') . '" class="js-protect-form">'
                    . '<p style="color:#64748b; margin-bottom:1rem;">Aucun colis ou facture n\'a encore été enregistré aujourd\'hui pour votre agence. Soumettre un état à 0.</p>'
                    . '<button type="submit" class="finea-button finea-button--accent">Initialiser et soumettre le point</button>'
                    . '</form>';
            }
            $submissionForm .= '</div>';
        }

        // Liste globale des états soumis (pour consolidateurs/caissière principale)
        $tableHtml = '';
        if ($reports === []) {
            $tableHtml = Ui::emptyState(
                'Aucun point de caisse',
                'Aucun état journalier n\'a été soumis pour le moment.'
            );
        } else {
            $rows = '';
            foreach ($reports as $r) {
                $badgeTone = match($r->statut) {
                    'consolide' => 'success',
                    'soumis' => 'primary',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($r->statut), $badgeTone);

                $actionsHtml = '—';
                if ($r->statut === 'soumis' && Auth::hasRole('caissiere_principale')) {
                    $actionsHtml = '<form method="post" action="' . View::url('finance/clotures/' . $r->id . '/consolider') . '" class="js-protect-form">'
                        . '<button type="submit" class="finea-button finea-button--success finea-button-sm">Consolider</button>'
                        . '</form>';
                }

                $agenceName = '';
                foreach ($agences as $a) {
                    if ($a['id'] === $r->agenceId) {
                        $agenceName = $a['name'];
                        break;
                    }
                }

                $rows .= '<tr>'
                    . '<td>' . View::e($r->dateJour) . '</td>'
                    . '<td><strong>' . View::e($agenceName) . '</strong></td>'
                    . '<td>' . View::e($r->nbColisEnregistres) . ' / ' . View::e($r->nbFacturesEmises) . '</td>'
                    . '<td style="text-align:right;">' . View::e(number_format($r->totalFactureXof, 2, ',', ' ')) . ' XOF<br><span style="color:#64748b; font-size:0.8rem;">' . View::e(number_format($r->totalFactureEur, 2, ',', ' ')) . ' EUR</span></td>'
                    . '<td style="text-align:right; font-weight:600; color:#16a34a;">' . View::e(number_format($r->totalEncaisseXof, 2, ',', ' ')) . ' XOF<br><span style="font-size:0.8rem;">' . View::e(number_format($r->totalEncaisseEur, 2, ',', ' ')) . ' EUR</span></td>'
                    . '<td>' . ($r->dateSoumission ? date('d/m/Y à H:i', strtotime($r->dateSoumission)) : '—') . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsHtml . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Agence</th>'
                . '<th>Colis / Factures</th>'
                . '<th style="text-align:right;">Total Facturé</th>'
                . '<th style="text-align:right;">Solde Caisse (Encaissé)</th>'
                . '<th>Heure Soumission</th>'
                . '<th>Statut</th>'
                . '<th>Actions</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $submissionForm
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Historique des points de caisse</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Rendu du grand livre comptable.
     */
    public static function comptabilitePage(array $ecritures, array $accounts, array $filters = []): string
    {
        $header = Ui::pageHeader(
            'Comptabilité Générale',
            'Livre-journal, comptes Syscohada et écritures comptables générées automatiquement.',
            [
                'eyebrow' => 'General Ledger & Accounts',
                'class' => 'rh-hero-white',
            ]
        );

        // Section filtres
        $jourOpts = [
            ['value' => '', 'label' => 'Tous les journaux'],
            ['value' => 'ventes', 'label' => 'Journal des Ventes'],
            ['value' => 'caisses', 'label' => 'Journal de Caisse'],
            ['value' => 'achats', 'label' => 'Journal des Achats'],
            ['value' => 'banque', 'label' => 'Journal de Banque'],
            ['value' => 'OD', 'label' => 'Opérations Diverses (OD)'],
        ];
        $journal = Form::selectSearch('journal', $jourOpts, $filters['journal'] ?? '', ['label' => 'Journal']);

        $compteOpts = [['value' => '', 'label' => 'Tous les comptes']];
        foreach ($accounts as $a) {
            $compteOpts[] = ['value' => $a['code'], 'label' => $a['code'] . ' - ' . $a['libelle']];
        }
        $compte = Form::selectSearch('compte', $compteOpts, $filters['compte'] ?? '', ['label' => 'Compte (Débit/Crédit)']);

        $debut = Form::input('date_debut', [
            'label' => 'Date Début',
            'type' => 'date',
            'value' => $filters['date_debut'] ?? ''
        ]);

        $fin = Form::input('date_fin', [
            'label' => 'Date Fin',
            'type' => 'date',
            'value' => $filters['date_fin'] ?? ''
        ]);

        $filterGrid = '<div class="rh-personnel-filter-grid">' . $journal . $compte . $debut . $fin . '</div>';

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Rechercher'
            . '</button>';

        $resetBtn = '<a href="' . View::url('finance/comptabilite') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>';
        $form = '<form method="get" action="' . View::url('finance/comptabilite') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . '</form>';

        // Tableau
        $tableHtml = '';
        if ($ecritures === []) {
            $tableHtml = Ui::emptyState(
                'Aucune écriture comptable',
                'Aucune écriture ne correspond à vos critères.'
            );
        } else {
            $rows = '';
            foreach ($ecritures as $e) {
                $compteDebName = '';
                $compteCredName = '';
                foreach ($accounts as $a) {
                    if ($a['code'] === $e->compteDebit) {
                        $compteDebName = $a['libelle'];
                    }
                    if ($a['code'] === $e->compteCredit) {
                        $compteCredName = $a['libelle'];
                    }
                }

                $rows .= '<tr>'
                    . '<td>' . View::e($e->dateEcriture) . '</td>'
                    . '<td><span class="finea-badge">' . View::e(strtoupper($e->journal)) . '</span></td>'
                    . '<td><strong>' . View::e($e->compteDebit) . '</strong><br><small style="color:#64748b;">' . View::e($compteDebName) . '</small></td>'
                    . '<td><strong>' . View::e($e->compteCredit) . '</strong><br><small style="color:#64748b;">' . View::e($compteCredName) . '</small></td>'
                    . '<td style="text-align:right; font-weight:700; color:#1e293b;">' . View::e(number_format($e->montant, 2, ',', ' ')) . ' ' . View::e($e->devise) . '</td>'
                    . '<td>' . View::e($e->pieceJustificativeId) . '</td>'
                    . '<td>' . View::e($e->libelle) . '</td>'
                    . '<td><span style="font-family:monospace; background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px;">' . View::e($e->lettrage ?? '—') . '</span></td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Journal</th>'
                . '<th>Débit (+)</th>'
                . '<th>Crédit (-)</th>'
                . '<th style="text-align:right;">Montant</th>'
                . '<th>Pièce Réf</th>'
                . '<th>Libellé écriture</th>'
                . '<th>Lettrage</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Livre-journal des écritures</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }
}
