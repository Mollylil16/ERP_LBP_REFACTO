<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Pages\Rh\MissionIndexPage;

final class Missions
{
    public static function missionsIndexPage(
        MissionIndexPage $page,
        string $searchVal,
        string $statusVal,
        string $csrfToken
    ): string {
        $header = self::missionHeader(
            'Ordres de mission',
            'Brouillons, validation, depart et retour de mission — avec saisie des frais en lignes type devis.',
            'rh/dashboard',
            [
                '<a href="#" onclick="alert(\'Export Excel en cours...\'); return false;" class="finea-btn" style="border: 1px solid #10b981; background: transparent; color: #10b981; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; text-decoration: none; margin-right: 8px;">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Exporter le recap Excel</a>',
                '<a href="#" onclick="alert(\'Export PDF en cours...\'); return false;" class="finea-btn" style="border: 1px solid #ef4444; background: transparent; color: #ef4444; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; text-decoration: none; margin-right: 8px;">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Exporter le recap PDF</a>',
                Ui::button('Nouvel ordre', [
                    'href' => 'rh/missions/nouveau',
                    'variant' => 'plain',
                    'style' => 'background: #4f46e5; border-color: #4f46e5; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.15);'
                ])
            ]
        );

        $filter = self::filterForm('rh/missions', $searchVal, $statusVal);

        $flashError = \App\Helpers\Session::getFlash('error');
        $errorAlert = '';
        if ($flashError) {
            $errorAlert = '<div class="finea-alert finea-alert--danger" style="margin-bottom: 20px; padding: 14px; border-radius: 8px;">'
                . View::e($flashError)
                . '</div>';
        }

        $flashSuccess = \App\Helpers\Session::getFlash('success');
        $successAlert = '';
        if ($flashSuccess) {
            $successAlert = '<div class="finea-alert finea-alert--success" style="margin-bottom: 20px; padding: 14px; border-radius: 8px;">'
                . View::e($flashSuccess)
                . '</div>';
        }

        $list = self::missionsList($page->missions, [$page, 'formatStatus'], $csrfToken);

        return '<div class="finea-shell rh-missions-page">'
            . '<div class="finea-container">'
            . $header
            . $filter
            . $errorAlert
            . $successAlert
            . $list
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<int,array<string,mixed>> $employees
     * @param array<string,mixed>|null $mission
     * @param array<int,array<string,mixed>> $expenses
     */
    public static function missionFormPage(
        array $employees,
        ?array $mission,
        string $csrfToken,
        int $id,
        string $status,
        array $expenses
    ): string {
        $header = self::missionHeader(
            $mission ? 'Modifier l\'ordre de mission' : 'Ordre de mission',
            'Liaison chantier/prospection/autre + saisie des frais en lignes type devis.',
            'rh/missions'
        );

        $leftColumn = self::formFields($employees, $mission)
            . self::expensesTable($expenses)
            . '<div style="display: flex; gap: 12px; margin-bottom: 40px;">'
            . '<button type="submit" class="finea-btn" style="background: #4f46e5; border-color: #4f46e5; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 24px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>'
            . 'Enregistrer'
            . '</button>'
            . '<a href="' . View::url('rh/missions') . '" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 10px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center;">'
            . 'Retour liste'
            . '</a>'
            . '</div>';

        $rightColumn = '<div style="position: sticky; top: 20px;">'
            . self::historyTimeline($mission)
            . '</div>';

        $formContent = Csrf::input()
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="status" value="' . View::e($status) . '">'
            . '<div class="rh-mission-form-layout">'
            . '<div>' . $leftColumn . '</div>'
            . '<div>' . $rightColumn . '</div>'
            . '</div>';

        $form = Rh::form('rh/missions', $formContent, ['class' => 'rh-mission-form']);

        $expensesCount = count($expenses) ?: 3;

        $script = '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    const container = document.getElementById(\'expenses-rows\');'
            . '    const btnAdd = document.getElementById(\'btn-add-expense\');'
            . '    const totalDisplay = document.getElementById(\'total-expenses-display\');'
            . '    let rowIndex = ' . $expensesCount . ';'
            . '    function formatNumber(num) {'
            . '        return new Intl.NumberFormat(\'fr-FR\', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);'
            . '    }'
            . '    function calculateTotals() {'
            . '        let grandTotal = 0;'
            . '        if (!container) return;'
            . '        const rows = container.querySelectorAll(\'.expense-row-item\');'
            . '        rows.forEach(row => {'
            . '            const qteInput = row.querySelector(\'.expense-qte\');'
            . '            const puInput = row.querySelector(\'.expense-pu\');'
            . '            const rowTotalDisplay = row.querySelector(\'.expense-row-total-value\');'
            . '            const qte = parseFloat(qteInput.value) || 0;'
            . '            const pu = parseFloat(puInput.value) || 0;'
            . '            const rowTotal = qte * pu;'
            . '            grandTotal += rowTotal;'
            . '            if (rowTotalDisplay) rowTotalDisplay.textContent = formatNumber(rowTotal);'
            . '        });'
            . '        if (totalDisplay) totalDisplay.textContent = formatNumber(grandTotal);'
            . '    }'
            . '    function createRowHtml(index, designation = \'\', unite = \'\', qte = 0, pu = 0) {'
            . '        const item = document.createElement(\'div\');'
            . '        item.className = \'expense-row-item\';'
            . '        item.innerHTML = `'
            . '            <div class="expense-row-inputs">'
            . '                <div>'
            . '                    <input type="text" name="expenses[\${index}][designation]" value="\${designation}" class="finea-form-control" placeholder="Ex: Hotel, taxi, repas, carburant..." style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem;">'
            . '                </div>'
            . '                <div>'
            . '                    <input type="text" name="expenses[\${index}][unite]" value="\${unite}" class="finea-form-control" placeholder="j, nuit, u" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem;">'
            . '                </div>'
            . '                <div>'
            . '                    <input type="number" name="expenses[\${index}][qte]" value="\${qte}" step="any" min="0" class="finea-form-control expense-qte" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem; text-align: right;">'
            . '                </div>'
            . '                <div>'
            . '                    <input type="number" name="expenses[\${index}][pu]" value="\${pu}" step="any" min="0" class="finea-form-control expense-pu" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem; text-align: right;">'
            . '                </div>'
            . '                <div>'
            . '                    <button type="button" class="btn-delete-expense-row">'
            . '                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>'
            . '                    </button>'
            . '                </div>'
            . '            </div>'
            . '            <div class="expense-row-total-line">'
            . '                <span>Total ligne</span>'
            . '                <strong><span class="expense-row-total-value">\${formatNumber(qte * pu)}</span> XOF</strong>'
            . '            </div>'
            . '        \`;'
            . '        item.querySelector(\'.expense-qte\').addEventListener(\'input\', calculateTotals);'
            . '        item.querySelector(\'.expense-pu\').addEventListener(\'input\', calculateTotals);'
            . '        item.querySelector(\'.btn-delete-expense-row\').addEventListener(\'click\', function() {'
            . '            item.remove();'
            . '            calculateTotals();'
            . '        });'
            . '        return item;'
            . '    }'
            . '    if (btnAdd) {'
            . '        btnAdd.addEventListener(\'click\', function() {'
            . '            const item = createRowHtml(rowIndex, \'\', \'\', 0, 0);'
            . '            if (container) container.appendChild(item);'
            . '            rowIndex++;'
            . '            calculateTotals();'
            . '        });'
            . '    }'
            . '    function initRowsEvents() {'
            . '        if (!container) return;'
            . '        const rows = container.querySelectorAll(\'.expense-row-item\');'
            . '        rows.forEach(item => {'
            . '            const qteInput = item.querySelector(\'.expense-qte\');'
            . '            const puInput = item.querySelector(\'.expense-pu\');'
            . '            const btnDel = item.querySelector(\'.btn-delete-expense-row\');'
            . '            if (qteInput) qteInput.removeEventListener(\'input\', calculateTotals);'
            . '            if (puInput) puInput.removeEventListener(\'input\', calculateTotals);'
            . '            if (qteInput) qteInput.addEventListener(\'input\', calculateTotals);'
            . '            if (puInput) puInput.addEventListener(\'input\', calculateTotals);'
            . '            if (btnDel) {'
            . '                btnDel.onclick = function() {'
            . '                    item.remove();'
            . '                    calculateTotals();'
            . '                };'
            . '            }'
            . '        });'
            . '    }'
            . '    initRowsEvents();'
            . '    calculateTotals();'
            . '});'
            . '</script>';

        return '<div class="finea-shell rh-mission-form-page">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '</div>'
            . '</div>'
            . $script;
    }
    /**
     * @param array<int,string> $actions
     */
    public static function missionHeader(string $title, string $subtitle, string $backUrl, array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $act) {
            $actionsHtml .= $act;
        }

        return '<div class="rh-card" style="padding: 24px; margin-bottom: 24px; border-radius: 16px;">'
            . '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">'
            . '<div>'
            . '<span class="rh-eyebrow">R H</span>'
            . '<h1 style="font-size: 2rem; font-weight: 850; color: #1e2b57; margin: 5px 0 8px 0; letter-spacing: -0.02em;">' . View::e($title) . '</h1>'
            . '<p style="font-size: 0.9rem; color: #64748b; margin: 0; line-height: 1.5;">' . View::e($subtitle) . '</p>'
            . '</div>'
            . '<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">'
            . $actionsHtml
            . '<a href="' . View::url(ltrim($backUrl, '/')) . '" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>'
            . 'Retour'
            . '</a>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    public static function filterForm(string $action, string $searchVal, string $statusVal): string
    {
        $statusOptions = [
            ['value' => 'Tous', 'label' => 'Tous'],
            ['value' => 'draft', 'label' => 'Brouillon'],
            ['value' => 'submitted', 'label' => 'Soumis'],
            ['value' => 'approved', 'label' => 'Approuve'],
            ['value' => 'rejected', 'label' => 'Rejete'],
            ['value' => 'cancelled', 'label' => 'Annule'],
        ];

        $searchInput = Form::input('search', [
            'label' => 'Recherche',
            'value' => $searchVal,
            'placeholder' => 'Code, titre, destination, nom...',
            'id' => 'search_input',
        ]);

        $statusSelect = Form::select('status', 'Statut', $statusOptions, $statusVal, [
            'id' => 'status_filter',
            'class' => 'finea-form-control',
        ]);

        $button = '<button type="submit" class="finea-btn" style="background: #0f172a; border-color: #0f172a; color: #ffffff; font-weight: 700; min-height: 42px; padding: 0 24px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Filtrer'
            . '</button>';

        $content = '<div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 16px; align-items: end;">'
            . '<div>' . $searchInput . '</div>'
            . '<div>' . $statusSelect . '</div>'
            . '<div>' . $button . '</div>'
            . '</div>';

        return Rh::form($action, $content, ['method' => 'get', 'class' => 'rh-card', 'style' => 'padding: 20px; margin-bottom: 24px; border-radius: 16px;']);
    }

    /**
     * @param array<int,array<string,mixed>> $missions
     */
    public static function missionsList(array $missions, callable $formatStatus, string $csrfToken): string
    {
        if ($missions === []) {
            return '<div class="missions-empty-state" style="text-align: center; padding: 48px; background: var(--finea-background-light); border-radius: 16px; border: 1.5px dashed var(--finea-border);">'
                . '<div style="background: #eef2ff; color: #4f46e5; border-radius: 50%; width: 64px; height: 64px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">'
                . '<svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>'
                . '</div>'
                . '<h3>Aucun ordre de mission</h3>'
                . '<p style="color: var(--finea-text-muted);">Créez un premier ordre pour démarrer.</p>'
                . '<a href="' . View::url('rh/missions/nouveau') . '" class="finea-btn" style="background: #4f46e5; border-color: #4f46e5; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 24px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; text-decoration: none;">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
                . 'Nouvel ordre'
                . '</a>'
                . '</div>';
        }

        $html = '<div style="display: grid; grid-template-columns: 1fr; gap: 16px;">';
        foreach ($missions as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? 'draft');
            $statusText = $formatStatus($status);

            $borderColors = [
                'approved' => '#10b981',
                'rejected' => '#ef4444',
                'submitted' => '#f59e0b',
                'draft' => '#64748b'
            ];
            $border = $borderColors[$status] ?? '#64748b';

            $badgeClass = 'neutral';
            if ($status === 'approved') {
                $badgeClass = 'success';
            } elseif ($status === 'rejected') {
                $badgeClass = 'danger';
            } elseif ($status === 'submitted') {
                $badgeClass = 'warning';
            }

            $actionsHtml = '';
            if ($status === 'draft' || $status === 'submitted') {
                $actionsHtml .= '<a href="' . View::url('rh/missions/modifier/' . $rowId) . '" class="finea-btn" style="padding: 6px 12px; font-size: 0.75rem; font-weight: 700; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; text-decoration: none;">'
                    . '<svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>'
                    . 'Modifier'
                    . '</a> ';
            }

            if ($status === 'draft') {
                $actionsHtml .= '<form method="post" action="' . View::url('rh/missions/decide/' . $rowId) . '" style="display: inline;">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('status', 'submitted')
                    . '<button type="submit" class="finea-btn" style="padding: 6px 12px; font-size: 0.75rem; font-weight: 700; background: #4f46e5; border-color: #4f46e5; color: white; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;">'
                    . '<svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>'
                    . 'Soumettre'
                    . '</button>'
                    . '</form>';
            }

            if ($status === 'submitted') {
                $actionsHtml .= '<form method="post" action="' . View::url('rh/missions/decide/' . $rowId) . '" style="display: inline; margin-right: 4px;">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('status', 'approved')
                    . '<button type="submit" class="finea-btn" style="padding: 6px 12px; font-size: 0.75rem; font-weight: 700; background: #10b981; border-color: #10b981; color: white; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;">'
                    . 'Approuver'
                    . '</button>'
                    . '</form>'
                    . '<form method="post" action="' . View::url('rh/missions/decide/' . $rowId) . '" style="display: inline;">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('status', 'rejected')
                    . '<button type="submit" class="finea-btn" style="padding: 6px 12px; font-size: 0.75rem; font-weight: 700; background: #ef4444; border-color: #ef4444; color: white; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;">'
                    . 'Refuser'
                    . '</button>'
                    . '</form>';
            }

            $approvedBy = '';
            if ($row['approved_by_name']) {
                $approvedBy = '<div style="margin-top: 6px; font-size: 0.8rem; color: #059669;"><strong>Valide par :</strong> ' . View::e((string)$row['approved_by_name']) . '</div>';
            }

            $but = $row['but_contexte'] ? '<div style="margin-bottom: 4px;"><strong>But / contexte :</strong> ' . View::e((string)$row['but_contexte']) . '</div>' : '';
            $liaison = $row['liaison_type'] ? '<div style="margin-bottom: 4px;"><strong>Liaison :</strong> ' . View::e((string)$row['liaison_type']) . '</div>' : '';
            $notes = $row['notes'] ? '<div style="margin-bottom: 4px;"><strong>Notes :</strong> ' . View::e((string)$row['notes']) . '</div>' : '';

            $cardBody = '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">'
                . '<div style="flex: 1; min-width: 250px;">'
                . '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">'
                . '<span style="font-size: 0.75rem; font-weight: 800; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.05em;">OM-' . str_pad((string)$rowId, 4, '0', STR_PAD_LEFT) . '</span>'
                . '<h2 style="font-size: 1.1rem; font-weight: 800; color: #1e2b57; margin: 0;">' . View::e((string)$row['destination']) . '</h2>'
                . '</div>'
                . '<span style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">'
                . 'Agent : <strong>' . View::e((string)$row['employee_name']) . '</strong> (' . View::e((string)($row['employee_number'] ?: 'N/A')) . ')'
                . '</span>'
                . '<div style="font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 10px;">'
                . '<div style="margin-bottom: 4px;"><strong>Objet :</strong> ' . View::e((string)$row['purpose']) . '</div>'
                . $but
                . $liaison
                . $notes
                . $approvedBy
                . '</div>'
                . '</div>'
                . '<div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 12px;">'
                . '<div>'
                . '<span class="finea-status-badge finea-status-badge--' . $badgeClass . '" style="font-weight: 700; font-size: 0.8rem; padding: 4px 10px;">'
                . View::e($statusText)
                . '</span>'
                . '<small style="display: block; font-size: 0.75rem; color: #64748b; margin-top: 6px; font-weight: 600;">'
                . 'Du ' . date('d/m/Y', strtotime((string)$row['start_date'])) . ' au ' . date('d/m/Y', strtotime((string)$row['end_date']))
                . '</small>'
                . '</div>'
                . '<div style="font-size: 1.15rem; font-weight: 850; color: #1e2b57;">'
                . number_format((float)$row['budget'], 0, ',', ' ') . ' <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">XOF HT</span>'
                . '</div>'
                . '<div style="display: flex; gap: 8px; margin-top: 6px;">'
                . $actionsHtml
                . '</div>'
                . '</div>'
                . '</div>';

            $html .= Rh::card($cardBody, [
                'tag' => 'article',
                'class' => 'rh-mission-card',
                'style' => 'border-left: 5px solid ' . $border . '; margin-bottom: 0;',
            ]);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $employees
     */
    public static function formFields(array $employees, ?array $mission): string
    {
        $employeeId = $mission ? (int)$mission['employee_id'] : 0;
        $destination = $mission ? (string)$mission['destination'] : '';
        $startDate = $mission ? (string)$mission['start_date'] : '';
        $endDate = $mission ? (string)$mission['end_date'] : '';
        $purpose = $mission ? (string)$mission['purpose'] : '';
        $butContexte = $mission ? (string)$mission['but_contexte'] : '';
        $liaisonType = $mission ? (string)$mission['liaison_type'] : 'Aucune';
        $notes = $mission ? (string)$mission['notes'] : '';

        $employeeOptions = array_map(function($emp) {
            return [
                'value' => (int)$emp['id'],
                'label' => (string)$emp['full_name'] . ' (' . ($emp['employee_number'] ?: 'Sans') . ')',
            ];
        }, $employees);

        $purposeInput = Form::input('purpose', [
            'label' => 'Objet / titre',
            'value' => $purpose,
            'placeholder' => 'Ex: Visite de chantier, reunion client, prospection...',
            'required' => true,
            'id' => 'purpose',
        ]);

        $employeeSelect = Form::select('employee_id', 'Collaborateur', $employeeOptions, $employeeId, [
            'id' => 'employee_id',
            'required' => true,
            'class' => 'finea-form-control',
            'placeholder' => 'Sélectionner',
        ]);

        $destinationInput = Form::input('destination', [
            'label' => 'Destination',
            'value' => $destination,
            'placeholder' => 'Ville / site / pays',
            'required' => true,
            'id' => 'destination',
        ]);

        $startInput = Form::input('start_date', [
            'label' => 'Debut prevu',
            'type' => 'date',
            'value' => $startDate,
            'required' => true,
            'id' => 'start_date',
        ]);

        $endInput = Form::input('end_date', [
            'label' => 'Fin prevue',
            'type' => 'date',
            'value' => $endDate,
            'required' => true,
            'id' => 'end_date',
        ]);

        $contextTextarea = Form::textarea('but_contexte', [
            'label' => 'But / contexte',
            'value' => $butContexte,
            'placeholder' => 'Objectif de la mission, interlocuteurs, agenda...',
            'id' => 'but_contexte',
            'rows' => 4,
        ]);

        $liaisonOptions = [
            ['value' => 'Aucune', 'label' => 'Aucune'],
            ['value' => 'Chantier', 'label' => 'Chantier'],
            ['value' => 'Prospection', 'label' => 'Prospection'],
            ['value' => 'Formation', 'label' => 'Formation'],
            ['value' => 'Autre', 'label' => 'Autre'],
        ];

        $liaisonSelect = Form::select('liaison_type', 'Type de liaison', $liaisonOptions, $liaisonType, [
            'id' => 'liaison_type',
            'class' => 'finea-form-control',
        ]);

        $notesTextarea = Form::textarea('notes', [
            'label' => 'Notes de mission',
            'value' => $notes,
            'placeholder' => 'Infos pratiques, contacts, moyens de transport, consignes...',
            'id' => 'notes',
            'rows' => 4,
        ]);

        $fields = $purposeInput
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">'
            . '<div>' . $employeeSelect . '</div>'
            . '<div>' . $destinationInput . '</div>'
            . '</div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">'
            . '<div>' . $startInput . '</div>'
            . '<div>' . $endInput . '</div>'
            . '</div>'
            . $contextTextarea;

        $cardInfo = Rh::card($fields, [
            'title' => 'Infos mission',
            'eyebrow' => 'Details',
            'meta' => 'Identifiez le collaborateur, le lieu et les dates prévues.',
        ]);

        $cardLiaison = Rh::card($liaisonSelect, [
            'title' => 'Liaison',
            'eyebrow' => 'Contexte',
            'meta' => 'Chantier, prospection, ou autre contexte.',
        ]);

        $cardNotes = Rh::card($notesTextarea, [
            'title' => 'Notes',
            'eyebrow' => 'Informations complémentaires',
        ]);

        return $cardInfo . $cardLiaison . $cardNotes;
    }

    /**
     * @param array<int,array<string,mixed>> $expenses
     */
    public static function expensesTable(array $expenses): string
    {
        $rowsHtml = '';
        $hasExpenses = $expenses !== [];
        $count = $hasExpenses ? count($expenses) : 3;

        for ($i = 0; $i < $count; $i++) {
            $item = $expenses[$i] ?? null;
            $designation = $item ? (string)$item['designation'] : '';
            $unite = $item ? (string)$item['unite'] : '';
            $qte = $item ? (float)$item['qte'] : 0;
            $pu = $item ? (float)$item['pu'] : 0;
            $rowTotal = $qte * $pu;

            $rowsHtml .= '<div class="expense-row-item">'
                . '<div class="expense-row-inputs">'
                . '<div><input type="text" name="expenses[' . $i . '][designation]" value="' . View::e($designation) . '" class="finea-form-control" placeholder="Ex: Hotel, taxi, repas, carburant..." style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem;"></div>'
                . '<div><input type="text" name="expenses[' . $i . '][unite]" value="' . View::e($unite) . '" class="finea-form-control" placeholder="j, nuit, u" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem;"></div>'
                . '<div><input type="number" name="expenses[' . $i . '][qte]" value="' . $qte . '" step="any" min="0" class="finea-form-control expense-qte" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem; text-align: right;"></div>'
                . '<div><input type="number" name="expenses[' . $i . '][pu]" value="' . $pu . '" step="any" min="0" class="finea-form-control expense-pu" style="width: 100%; border: 1px solid #dfe6f1; border-radius: 8px; padding: 8px; font-size: 0.875rem; text-align: right;"></div>'
                . '<div>'
                . '<button type="button" class="btn-delete-expense-row">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>'
                . '</button>'
                . '</div>'
                . '</div>'
                . '<div class="expense-row-total-line">'
                . '<span>Total ligne</span>'
                . '<strong><span class="expense-row-total-value">' . number_format($rowTotal, 2, ',', ' ') . '</span> XOF</strong>'
                . '</div>'
                . '</div>';
        }

        $gridHtml = '<div class="expenses-table-container">'
            . '<div class="expenses-table-header"><h3>Tableau des frais</h3><span>Auto-calcul des totaux</span></div>'
            . '<div style="background: #f1f5f9; padding: 8px 16px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; font-weight: 800; font-size: 0.75rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #dfe6f1;">'
            . '<div>Designation</div><div>Unite</div><div style="text-align: right;">Qte</div><div style="text-align: right;">PU</div><div></div>'
            . '</div>'
            . '<div id="expenses-rows">'
            . $rowsHtml
            . '</div>'
            . '</div>'
            . '<div class="total-expenses-summary">'
            . '<div class="total-label-block"><span>TOTAL FRAIS</span><strong><span id="total-expenses-display">0,00</span></strong></div>'
            . '<div class="total-currency-info">HT (sans TVA)</div>'
            . '</div>';

        $headerActions = '<button type="button" id="btn-add-expense" class="finea-btn" style="background: #0f172a; border-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
            . 'Ajouter une ligne'
            . '</button>';

        return Rh::card($gridHtml, [
            'title' => 'Frais de mission',
            'eyebrow' => 'Estimation budget',
            'meta' => 'Saisie en lignes (désignation, unité, quantité, PU) — comme un devis.',
            'actions' => $headerActions,
        ]);
    }

    public static function historyTimeline(?array $mission): string
    {
        $content = '';
        if (!$mission) {
            $content = '<div class="history-card-placeholder">Enregistrez le brouillon pour voir l\'historique.</div>';
        } else {
            $status = (string)$mission['status'];
            $items = '<div class="history-timeline-item">'
                . '<div class="history-timeline-item-title">Mission creee (Brouillon)</div>'
                . '<div class="history-timeline-item-meta">Par le service RH le ' . date('d/m/Y à H:i', strtotime((string)$mission['created_at'])) . '</div>'
                . '</div>';

            if ($status !== 'draft') {
                $items .= '<div class="history-timeline-item submitted">'
                    . '<div class="history-timeline-item-title">Soumis pour validation</div>'
                    . '<div class="history-timeline-item-meta">En attente de signature</div>'
                    . '</div>';
            }

            if ($status === 'approved') {
                $items .= '<div class="history-timeline-item approved">'
                    . '<div class="history-timeline-item-title">Approuve & Signe</div>'
                    . '<div class="history-timeline-item-meta">Par ' . View::e((string)($mission['approved_by_name'] ?: 'Manager')) . ($mission['approved_at'] ? ' le ' . date('d/m/Y à H:i', strtotime((string)$mission['approved_at'])) : '') . '</div>'
                    . '</div>';
            } elseif ($status === 'rejected') {
                $items .= '<div class="history-timeline-item rejected">'
                    . '<div class="history-timeline-item-title">Refuse</div>'
                    . '<div class="history-timeline-item-meta">Par ' . View::e((string)($mission['approved_by_name'] ?: 'Manager')) . ($mission['approved_at'] ? ' le ' . date('d/m/Y à H:i', strtotime((string)$mission['approved_at'])) : '') . '</div>'
                    . '</div>';
            } elseif ($status === 'cancelled') {
                $items .= '<div class="history-timeline-item cancelled">'
                    . '<div class="history-timeline-item-title">Annule</div>'
                    . '<div class="history-timeline-item-meta">Statut annule</div>'
                    . '</div>';
            }

            $content = '<div class="history-timeline">' . $items . '</div>';
        }

        return Rh::card($content, [
            'title' => 'Historique',
            'eyebrow' => 'Journal de bord',
            'meta' => 'Evenements de workflow et mises a jour.',
        ]);
    }
}
