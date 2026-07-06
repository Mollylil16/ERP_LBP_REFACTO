<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Colisage\ColisageIndexPage;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Colisage
{
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

    public static function createPage(array $sites, array $clients, array $products = []): string
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

        // Prep options for products dropdown
        $prodOptions = [['value' => '', 'label' => '-- Sélectionner un produit existant --']];
        foreach ($products as $p) {
            $prodOptions[] = [
                'value' => (string) $p['id'],
                'label' => $p['nom'] . ' (' . number_format((float) $p['prix_unitaire'], 0, ',', ' ') . ' XOF/' . $p['unite'] . ')'
            ];
        }

        // Marchandises list
        $marchandisesHtml = '<div style="margin-top: 1.5rem;">'
            . '<h3>Marchandises contenues dans le colis</h3>'
            . '<table class="finea-table" style="margin-top:0.5rem;" id="marchandises-table">'
            . '<thead>'
            . '<tr><th>Description / Sélection du produit</th><th style="width: 15%;">Quantité</th><th style="width: 20%;">Poids Unitaire (kg)</th></tr>'
            . '</thead>'
            . '<tbody>';

        for ($i = 0; $i < 3; $i++) {
            $selectHtml = Form::select('m_product_id[]', $prodOptions, '', [
                'class' => 'finea-select',
                'id' => 'm_product_id_' . $i
            ]);

            $customNameInput = '<input type="text" name="m_custom_name[]" class="finea-input" placeholder="Ou nom du nouveau produit...">';
            $customPriceInput = '<input type="number" name="m_custom_price[]" class="finea-input" step="0.01" placeholder="Prix unitaire XOF...">';

            $marchandisesHtml .= '<tr>'
                . '<td style="vertical-align: top;">' 
                . $selectHtml 
                . '<div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">' 
                . $customNameInput 
                . $customPriceInput 
                . '</div>'
                . '</td>'
                . '<td style="vertical-align: top;"><input type="number" name="m_qty[]" class="finea-input" value="1"></td>'
                . '<td style="vertical-align: top;"><input type="number" name="m_weight[]" class="finea-input" step="0.01" value="0.00"></td>'
                . '</tr>';
        }
        $marchandisesHtml .= '</tbody></table></div>';

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

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $formContent
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
}
