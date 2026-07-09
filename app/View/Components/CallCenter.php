<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Auth;

final class CallCenter
{
    /**
     * Rendu du tableau de bord Call Center.
     */
    public static function dashboardPage(array $kpis, array $recentAppels, array $recentLitiges): string
    {
        $header = Ui::pageHeader(
            'Call Center & Support Client',
            'Pilotage de la relation client, des appels de suivi et de la résolution des litiges.',
            [
                'eyebrow' => 'Customer Care & Claims Dashboard',
                'class' => 'rh-hero-white',
            ]
        );

        // Cartes KPIs
        $kpisHtml = '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem;">'
            . Ui::card('Appels de Suivi', '<strong>' . $kpis['total_appels'] . '</strong> appels enregistrés', ['icon' => '📞'])
            . Ui::card('Satisfaction Client', '<strong>' . $kpis['avg_satisfaction'] . ' / 5</strong> note moyenne', ['icon' => '⭐'])
            . Ui::card('Litiges Actifs', '<strong>' . $kpis['open_litiges'] . '</strong> réclamations en cours', ['icon' => '🚨'])
            . Ui::card('Taux de Résolution', '<strong>' . $kpis['resolution_rate'] . '%</strong> litiges résolus', ['icon' => '✅'])
            . '</div>';

        // 1. Liste des appels récents
        $appelsRows = '';
        if ($recentAppels === []) {
            $appelsRows = '<tr><td colspan="5" style="text-align:center; color:#64748b;">Aucun appel récent.</td></tr>';
        } else {
            foreach ($recentAppels as $a) {
                $stars = str_repeat('⭐', (int)$a['satisfaction_score']) ?: '<span style="color:#94a3b8;">—</span>';
                $appelsRows .= '<tr>'
                    . '<td>' . View::e($a['created_at']) . '</td>'
                    . '<td style="font-weight:600; color:#1e293b;">' . View::e($a['client_name']) . '</td>'
                    . '<td>' . strtoupper(View::e($a['type_appel'])) . '</td>'
                    . '<td>' . $stars . '</td>'
                    . '<td>' . View::e($a['agent_name']) . '</td>'
                    . '</tr>';
            }
        }

        $appelsTable = '<div class="finea-table-wrapper">'
            . '<table class="finea-table">'
            . '<thead>'
            . '<tr>'
            . '<th>Date</th>'
            . '<th>Client</th>'
            . '<th>Type</th>'
            . '<th>Satisfaction</th>'
            . '<th>Agent</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $appelsRows . '</tbody>'
            . '</table>'
            . '</div>';

        // 2. Liste des réclamations récentes
        $litigesRows = '';
        if ($recentLitiges === []) {
            $litigesRows = '<tr><td colspan="6" style="text-align:center; color:#64748b;">Aucune réclamation récente.</td></tr>';
        } else {
            foreach ($recentLitiges as $l) {
                $badgeTone = match($l['statut']) {
                    'resolu' => 'success',
                    'en_cours' => 'primary',
                    'annule' => 'neutral',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($l['statut']), $badgeTone);

                $graviteTone = match($l['gravite']) {
                    'critique', 'haute' => 'danger',
                    'moyenne' => 'warning',
                    default => 'neutral'
                };
                $graviteBadge = Ui::badge(strtoupper($l['gravite']), $graviteTone);

                $tracking = $l['tracking_number'] ? '<span style="font-family:monospace; font-weight:600;">' . View::e($l['tracking_number']) . '</span>' : '<span style="color:#94a3b8;">Aucun</span>';

                $litigesRows .= '<tr>'
                    . '<td>' . View::e($l['date_ouverture']) . '</td>'
                    . '<td style="font-weight:600; color:#1e293b;">' . View::e($l['client_name']) . '</td>'
                    . '<td>' . $tracking . '</td>'
                    . '<td>' . $graviteBadge . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . View::e($l['agent_name']) . '</td>'
                    . '</tr>';
            }
        }

        $litigesTable = '<div class="finea-table-wrapper">'
            . '<table class="finea-table">'
            . '<thead>'
            . '<tr>'
            . '<th>Date</th>'
            . '<th>Client</th>'
            . '<th>Colis Tracking</th>'
            . '<th>Gravité</th>'
            . '<th>Statut</th>'
            . '<th>Agent</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $litigesRows . '</tbody>'
            . '</table>'
            . '</div>';

        $layout = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">'
            . '<div class="finea-section-card">'
            . '<div class="finea-section-heading" style="display:flex; justify-content:space-between; align-items:center;">'
            . '<h2 class="finea-section-title">📞 Appels Récents</h2>'
            . Ui::button('Nouveau suivi', ['href' => '/call-center/appels', 'variant' => 'primary', 'class' => 'finea-button-sm'])
            . '</div>'
            . $appelsTable
            . '</div>'
            . '<div class="finea-section-card">'
            . '<div class="finea-section-heading" style="display:flex; justify-content:space-between; align-items:center;">'
            . '<h2 class="finea-section-title">🚨 Réclamations Récentes</h2>'
            . Ui::button('Ouvrir litige', ['href' => '/call-center/litiges', 'variant' => 'primary', 'class' => 'finea-button-sm'])
            . '</div>'
            . $litigesTable
            . '</div>'
            . '</div>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $kpisHtml
            . $layout
            . '</div></div>';
    }

    /**
     * Rendu de la liste des appels de suivi et formulaire de création.
     */
    public static function appelsPage(array $appels, array $clients): string
    {
        $header = Ui::pageHeader(
            'Journal des Appels',
            'Enregistrement et suivi de la satisfaction client lors des échanges téléphoniques.',
            [
                'eyebrow' => 'Call Tracking Registry',
                'class' => 'rh-hero-white',
            ]
        );

        // Formulaire d'enregistrement
        $form = '';
        if (Auth::can('call_center_manage', 'create')) {
            $clientOpts = [['value' => '', 'label' => '-- Sélectionner le client --']];
            foreach ($clients as $c) {
                $clientOpts[] = ['value' => (string)$c['id'], 'label' => $c['name']];
            }

            $form = '<form method="post" action="' . View::url('call-center/appels/enregistrer') . '" class="js-protect-form finea-section-card" style="margin-bottom:2rem;">'
                . '<h3>Enregistrer un nouvel Appel</h3>'
                . '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::select('client_id', $clientOpts, '', ['label' => 'Client', 'required' => true])
                . Form::select('type_appel', [
                    ['value' => 'suivi', 'label' => 'Appel de Suivi'],
                    ['value' => 'reclamation', 'label' => 'Réclamation Client'],
                    ['value' => 'information', 'label' => 'Demande d\'Information'],
                    ['value' => 'autre', 'label' => 'Autre']
                ], 'suivi', ['label' => 'Type d\'Appel', 'required' => true])
                . Form::select('satisfaction_score', [
                    ['value' => '', 'label' => '-- Évaluer --'],
                    ['value' => '5', 'label' => '⭐⭐⭐⭐⭐ Excellent (5)'],
                    ['value' => '4', 'label' => '⭐⭐⭐⭐ Très bon (4)'],
                    ['value' => '3', 'label' => '⭐⭐⭐ Moyen (3)'],
                    ['value' => '2', 'label' => '⭐⭐ Mauvais (2)'],
                    ['value' => '1', 'label' => '⭐ Critique (1)']
                ], '', ['label' => 'Note de satisfaction'])
                . '</div>'
                . '<div style="display:grid; grid-template-columns:3fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::input('description', ['label' => 'Détails de la conversation / Notes de l\'appel', 'required' => true])
                . Form::select('statut', [
                    ['value' => 'traite', 'label' => 'Traité'],
                    ['value' => 'a_rappeler', 'label' => 'À rappeler'],
                    ['value' => 'en_attente', 'label' => 'En attente']
                ], 'traite', ['label' => 'Statut', 'required' => true])
                . '</div>'
                . '<div style="margin-top:1rem; display:flex; justify-content:flex-end;">'
                . '<button type="submit" class="finea-button finea-button--accent">Enregistrer l\'appel</button>'
                . '</div>'
                . '</form>';
        }

        // Tableau des appels
        $rows = '';
        if ($appels === []) {
            $tableHtml = Ui::emptyState('Aucun appel enregistré', 'Commencez par enregistrer un nouvel appel ci-dessus.');
        } else {
            foreach ($appels as $a) {
                $badgeTone = match($a['statut']) {
                    'traite' => 'success',
                    'a_rappeler' => 'warning',
                    default => 'neutral'
                };
                $badge = Ui::badge(strtoupper($a['statut']), $badgeTone);
                $stars = str_repeat('⭐', (int)$a['satisfaction_score']) ?: '<span style="color:#94a3b8;">Non évalué</span>';

                $rows .= '<tr>'
                    . '<td>' . View::e($a['created_at']) . '</td>'
                    . '<td style="font-weight:600; color:#1e293b;">' . View::e($a['client_name']) . '</td>'
                    . '<td>' . strtoupper(View::e($a['type_appel'])) . '</td>'
                    . '<td>' . View::e($a['description']) . '</td>'
                    . '<td>' . $stars . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . View::e($a['agent_name']) . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Client</th>'
                . '<th>Type</th>'
                . '<th>Notes de la conversation</th>'
                . '<th>Satisfaction</th>'
                . '<th>Statut</th>'
                . '<th>Agent</th>'
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
            . '<div class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Historique des appels de suivi</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Rendu de la liste des réclamations et litiges.
     */
    public static function litigesPage(array $litiges, array $clients, array $colis): string
    {
        $header = Ui::pageHeader(
            'Réclamations & Litiges Clients',
            'Gestion, escalade et résolution des incidents opérationnels liés aux colis.',
            [
                'eyebrow' => 'Claims & Dispute Settlement',
                'class' => 'rh-hero-white',
            ]
        );

        // Formulaire de déclaration
        $form = '';
        if (Auth::can('call_center_manage', 'create')) {
            $clientOpts = [['value' => '', 'label' => '-- Sélectionner le client --']];
            foreach ($clients as $c) {
                $clientOpts[] = ['value' => (string)$c['id'], 'label' => $c['name']];
            }

            $colisOpts = [['value' => '', 'label' => '-- Aucun colis associé --']];
            foreach ($colis as $co) {
                $colisOpts[] = ['value' => (string)$co['id'], 'label' => $co['tracking_number']];
            }

            $form = '<form method="post" action="' . View::url('call-center/litiges/enregistrer') . '" class="js-protect-form finea-section-card" style="margin-bottom:2rem;">'
                . '<h3>Déclarer une Réclamation / Litige</h3>'
                . '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::select('client_id', $clientOpts, '', ['label' => 'Client', 'required' => true])
                . Form::select('colis_id', $colisOpts, '', ['label' => 'Colis associé (Tracking)'])
                . Form::select('type_litige', [
                    ['value' => 'perte', 'label' => 'Perte de colis'],
                    ['value' => 'retard', 'label' => 'Retard important'],
                    ['value' => 'endommage', 'label' => 'Colis cassé / endommagé'],
                    ['value' => 'facturation', 'label' => 'Divergence de facturation'],
                    ['value' => 'autre', 'label' => 'Autre litige']
                ], 'perte', ['label' => 'Type de réclamation', 'required' => true])
                . '</div>'
                . '<div style="display:grid; grid-template-columns:3fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::input('description', ['label' => 'Description détaillée de l\'anomalie / réclamation', 'required' => true])
                . Form::select('gravite', [
                    ['value' => 'basse', 'label' => 'Basse'],
                    ['value' => 'moyenne', 'label' => 'Moyenne'],
                    ['value' => 'haute', 'label' => 'Haute'],
                    ['value' => 'critique', 'label' => '🚨 Critique']
                ], 'moyenne', ['label' => 'Gravité / Priorité'])
                . '</div>'
                . '<div style="margin-top:1rem; display:flex; justify-content:flex-end;">'
                . '<button type="submit" class="finea-button finea-button--accent">Ouvrir le dossier litige</button>'
                . '</div>'
                . '</form>';
        }

        // Tableau des litiges
        $rows = '';
        if ($litiges === []) {
            $tableHtml = Ui::emptyState('Aucune réclamation ouverte', 'Aucun litige ou réclamation client déclaré pour le moment.');
        } else {
            foreach ($litiges as $l) {
                $badgeTone = match($l['statut']) {
                    'resolu' => 'success',
                    'en_cours' => 'primary',
                    'annule' => 'neutral',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($l['statut']), $badgeTone);

                $graviteTone = match($l['gravite']) {
                    'critique', 'haute' => 'danger',
                    'moyenne' => 'warning',
                    default => 'neutral'
                };
                $graviteBadge = Ui::badge(strtoupper($l['gravite']), $graviteTone);

                $tracking = $l['tracking_number'] ? '<span style="font-family:monospace; font-weight:600;">' . View::e($l['tracking_number']) . '</span>' : '<span style="color:#94a3b8;">—</span>';

                // Bouton de traitement inline si non résolu/annulé
                $actionHtml = '—';
                if (in_array($l['statut'], ['nouveau', 'en_cours'], true) && Auth::can('call_center_manage', 'update')) {
                    $actionHtml = '<button onclick="toggleResolveForm(' . $l['id'] . ')" class="finea-button finea-button--primary finea-button-sm">Traiter</button>';
                }

                $solutionHtml = $l['solution_apporte'] ? '<div style="font-size:0.85rem; color:#475569; margin-top:0.25rem;"><strong>Solution :</strong> ' . View::e($l['solution_apporte']) . '</div>' : '';

                $rows .= '<tr>'
                    . '<td>' . View::e($l['date_ouverture']) . '</td>'
                    . '<td style="font-weight:600; color:#1e293b;">' . View::e($l['client_name']) . '</td>'
                    . '<td>' . $tracking . '</td>'
                    . '<td>' . strtoupper(View::e($l['type_litige'])) . '</td>'
                    . '<td>'
                    . '<div>' . View::e($l['description']) . '</div>'
                    . $solutionHtml
                    . '</td>'
                    . '<td>' . $graviteBadge . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . View::e($l['agent_name']) . '</td>'
                    . '<td>' . $actionHtml . '</td>'
                    . '</tr>';

                // Formulaire de résolution caché qui apparaît sous la ligne
                if (in_array($l['statut'], ['nouveau', 'en_cours'], true) && Auth::can('call_center_manage', 'update')) {
                    $rows .= '<tr id="resolve-row-' . $l['id'] . '" style="display:none; background:#f0f9ff;">'
                        . '<td colspan="9" style="padding:1rem;">'
                        . '<form method="post" action="' . View::url('call-center/litiges/' . $l['id'] . '/resoudre') . '" class="js-protect-form" style="display:flex; gap:1rem; align-items:flex-end;">'
                        . '<div style="flex:1;">'
                        . Form::input('solution_apporte', ['label' => 'Décrire la solution finale / Compensation apportée au client', 'required' => true])
                        . '</div>'
                        . '<div style="width:150px;">'
                        . Form::select('statut', [
                            ['value' => 'resolu', 'label' => 'Résolu'],
                            ['value' => 'en_cours', 'label' => 'En cours'],
                            ['value' => 'annule', 'label' => 'Annulé']
                        ], 'resolu', ['label' => 'Statut final'])
                        . '</div>'
                        . '<button type="submit" class="finea-button finea-button--success finea-button-sm">Enregistrer la solution</button>'
                        . '<button type="button" onclick="toggleResolveForm(' . $l['id'] . ')" class="finea-button finea-button--neutral finea-button-sm">Annuler</button>'
                        . '</form>'
                        . '</td>'
                        . '</tr>';
                }
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Client</th>'
                . '<th>Tracking</th>'
                . '<th>Type</th>'
                . '<th>Détails & Solution</th>'
                . '<th>Gravité</th>'
                . '<th>Statut</th>'
                . '<th>Agent</th>'
                . '<th>Action</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        // Script JS simple pour afficher/masquer le formulaire de résolution inline
        $js = '<script>'
            . 'function toggleResolveForm(id) {'
            . '  var row = document.getElementById("resolve-row-" + id);'
            . '  if(row.style.display === "none") {'
            . '    row.style.display = "table-row";'
            . '  } else {'
            . '    row.style.display = "none";'
            . '  }'
            . '}'
            . '</script>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Registre des réclamations clients</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>'
            . $js;
    }
}
