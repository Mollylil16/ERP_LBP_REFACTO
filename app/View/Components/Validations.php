<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Rh\ValidationIndexPage;

final class Validations
{
    public static function validationsPage(
        ValidationIndexPage $page,
        string $tab,
        string $csrfToken
    ): string {
        $header = self::validationsHeader(
            'Demandes employé — Validation RH',
            'En attente — absences, prêt, avance salaire, HS...',
            $tab
        );

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

        $table = self::requestsTable($page->requests, [$page, 'formatType'], [$page, 'formatStep']);
        $workflows = self::workflowsList($page->workflows, $csrfToken, [$page, 'formatStep']);
        $modal = self::validationModal($csrfToken);

        return '<div class="finea-shell rh-validations-page">'
            . '<div class="finea-container">'
            . $header
            . $errorAlert
            . $successAlert
            . $table
            . $workflows
            . '</div>'
            . '</div>'
            . $modal;
    }
    public static function validationsHeader(string $title, string $subtitle, string $activeTab): string
    {
        $tabs = [
            'pending' => 'En attente',
            'approved' => 'Approuvées',
            'rejected' => 'Refusées',
            'cancelled' => 'Annulées',
            'all' => 'Toutes'
        ];

        $tabsHtml = '';
        foreach ($tabs as $key => $label) {
            $active = ($activeTab === $key);
            $style = $active 
                ? 'background: #0f172a; border-color: #0f172a; color: #ffffff;' 
                : 'background: #ffffff; border: 1px solid #cbd5e1; color: #475569;';
            $tabsHtml .= '<a href="' . View::url('rh/validations?tab=' . $key) . '" class="finea-btn" style="' . $style . ' font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border-radius: 999px; text-decoration: none; margin-right: 10px; margin-bottom: 10px;">'
                . View::e($label)
                . '</a>';
        }

        return '<div class="rh-card" style="padding: 24px; margin-bottom: 24px; border-radius: 16px;">'
            . '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">'
            . '<div>'
            . '<span class="rh-eyebrow">R H</span>'
            . '<h1 style="font-size: 2rem; font-weight: 850; color: #1e2b57; margin: 5px 0 8px 0; letter-spacing: -0.02em;">' . View::e($title) . '</h1>'
            . '<p style="font-size: 0.9rem; color: #64748b; margin: 0; line-height: 1.5;">' . View::e($subtitle) . '</p>'
            . '</div>'
            . '<div style="display: flex; gap: 8px; flex-wrap: wrap;">'
            . '<a href="#" onclick="alert(\'Export PDF en cours...\'); return false;" class="finea-btn" style="background: #0f172a; border-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>'
            . 'Export PDF'
            . '</a>'
            . '<a href="#" onclick="alert(\'Export Excel en cours...\'); return false;" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px;">'
            . 'Export Excel'
            . '</a>'
            . '<a href="' . View::url('rh/dashboard') . '" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px;">'
            . 'Tableau de bord RH'
            . '</a>'
            . '</div>'
            . '</div>'
            . '<div style="display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap;">'
            . $tabsHtml
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<int,array<string,mixed>> $requests
     */
    public static function requestsTable(array $requests, callable $formatType, callable $formatStep): string
    {
        $rowsHtml = '';
        if ($requests === []) {
            $rowsHtml = '<tr>'
                . '<td colspan="7" style="padding: 48px; text-align: center; color: #64748b;">'
                . '<div style="background: #f8fafc; border-radius: 8px; padding: 24px; border: 1.5px dashed #cbd5e1; display: inline-block;">'
                . '<p style="margin: 0; font-weight: 700; color: #1e2b57;">Aucune demande</p>'
                . '<p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #64748b;">Aucune demande n\'a été trouvée pour ce statut.</p>'
                . '</div>'
                . '</td>'
                . '</tr>';
        } else {
            foreach ($requests as $req) {
                $reqId = (int) ($req['id'] ?? 0);
                $status = (string) ($req['status'] ?? 'submitted');
                $requestTypeStr = $formatType((string)$req['request_type']);

                // Determine badge state
                $badgeClass = 'neutral';
                $statusText = $formatStep((string)$req['current_step']);
                if ($status === 'approved') {
                    $badgeClass = 'success';
                    $statusText = 'Approuvée';
                } elseif ($status === 'rejected') {
                    $badgeClass = 'danger';
                    $statusText = 'Refusée';
                } elseif ($status === 'cancelled') {
                    $badgeClass = 'neutral';
                    $statusText = 'Annulée';
                } else {
                    $badgeClass = 'warning';
                    $statusText = 'En attente';
                }

                $periodStr = '';
                if ($req['start_date'] || $req['end_date']) {
                    $startDate = $req['start_date'] ? date('d/m/Y', strtotime((string)$req['start_date'])) : '?';
                    $endDate = $req['end_date'] ? date('d/m/Y', strtotime((string)$req['end_date'])) : '?';
                    $periodStr = '<small style="color: #64748b;">Période : ' . $startDate . ' au ' . $endDate . '</small>';
                }

                $amountStr = '';
                if ((float)($req['amount'] ?? 0) > 0) {
                    $amountStr = '<small style="display: block; color: #0284c7; font-weight: 600;">Montant : ' . number_format((float)$req['amount'], 0, ',', ' ') . ' XOF</small>';
                }

                $actionLink = '';
                if ($status !== 'approved' && $status !== 'rejected' && $status !== 'cancelled') {
                    $escRef = View::e((string)($req['reference'] ?: $req['id']));
                    $escEmp = View::e((string)$req['employee_name']);
                    $escType = View::e($requestTypeStr);
                    $escTitle = View::e((string)$req['reason']);
                    $startDateVal = $req['start_date'] ? date('d/m/Y', strtotime((string)$req['start_date'])) : '';
                    $endDateVal = $req['end_date'] ? date('d/m/Y', strtotime((string)$req['end_date'])) : '';
                    $amountVal = (float)$req['amount'] > 0 ? number_format((float)$req['amount'], 0, ',', ' ') : '';
                    $urlVal = View::url('rh/validations/decide/' . $reqId);

                    $actionLink = '<a href="#" onclick="openValidationModal({'
                        . 'ref: \'#' . addslashes($escRef) . '\','
                        . 'employee: \'' . addslashes($escEmp) . '\','
                        . 'type: \'' . addslashes($escType) . '\','
                        . 'title: \'' . addslashes($escTitle) . '\','
                        . 'start_date: \'' . $startDateVal . '\','
                        . 'end_date: \'' . $endDateVal . '\','
                        . 'amount: \'' . $amountVal . '\','
                        . 'action: \'' . $urlVal . '\''
                        . '}); return false;" style="color: #10b981; font-weight: 700; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">'
                        . 'Traiter'
                        . '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>'
                        . '</a>';
                }

                $rowsHtml .= '<tr style="border-bottom: 1px solid #edf1f7; transition: background 0.18s;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'transparent\'">'
                    . '<td style="padding: 16px 20px; font-weight: 700; color: #64748b;">'
                    . '#' . View::e((string)($req['reference'] ?: $req['id']))
                    . '</td>'
                    . '<td style="padding: 16px 20px; font-weight: 600; color: #1e2b57;">'
                    . View::e((string)$req['employee_name'])
                    . '</td>'
                    . '<td style="padding: 16px 20px; color: #475569;">'
                    . View::e($requestTypeStr)
                    . '</td>'
                    . '<td style="padding: 16px 20px; color: #1e293b;">'
                    . '<div style="font-weight: 500;">' . View::e((string)$req['reason']) . '</div>'
                    . $periodStr
                    . $amountStr
                    . '</td>'
                    . '<td style="padding: 16px 20px;">'
                    . '<span class="finea-status-badge finea-status-badge--' . $badgeClass . '" style="font-weight: 700; padding: 4px 8px; font-size: 0.75rem;">'
                    . View::e($statusText)
                    . '</span>'
                    . '</td>'
                    . '<td style="padding: 16px 20px; color: #64748b; font-size: 0.85rem;">'
                    . date('Y-m-d H:i:s', strtotime((string)$req['submitted_at']))
                    . '</td>'
                    . '<td style="padding: 16px 20px; text-align: right;">'
                    . $actionLink
                    . '</td>'
                    . '</tr>';
            }
        }

        return '<div class="rh-card" style="padding: 0; overflow: hidden; border-radius: 16px; margin-bottom: 24px;">'
            . '<div style="overflow-x: auto;">'
            . '<table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">'
            . '<thead>'
            . '<tr style="border-bottom: 2px solid #e2e8f0; background: #f8fafc; color: #475569; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">'
            . '<th style="padding: 16px 20px;">Réf.</th>'
            . '<th style="padding: 16px 20px;">Demandeur</th>'
            . '<th style="padding: 16px 20px;">Type</th>'
            . '<th style="padding: 16px 20px;">Titre</th>'
            . '<th style="padding: 16px 20px;">Statut</th>'
            . '<th style="padding: 16px 20px;">Date</th>'
            . '<th style="padding: 16px 20px; text-align: right;"></th>'
            . '</tr>'
            . '</thead>'
            . '<tbody style="color: #1e293b;">'
            . $rowsHtml
            . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<int,array<string,mixed>> $workflows
     */
    public static function workflowsList(array $workflows, string $csrfToken, callable $formatStep): string
    {
        $listHtml = '';
        if ($workflows === []) {
            $listHtml = '<div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 24px; text-align: center; color: #64748b;">'
                . 'Aucun workflow contractuel ou d\'évaluation n\'est en attente de visa.'
                . '</div>';
        } else {
            foreach ($workflows as $wf) {
                $wfId = (int) ($wf['id'] ?? 0);
                $processTypeStr = str_replace('_', ' ', (string)$wf['process_type']);
                $employeeName = View::e((string)($wf['employee_name'] ?: 'Général'));
                $stepStr = $formatStep((string)$wf['current_step']);
                $createdAt = date('d/m/Y', strtotime((string)$wf['created_at']));
                $actionUrl = View::url('rh/validations/decide-workflow/' . $wfId);

                $listHtml .= '<div style="display: flex; justify-content: space-between; align-items: center; padding: 18px; border: 1px solid #dfe6f1; border-radius: 12px; background: #ffffff; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">'
                    . '<div>'
                    . '<span style="font-size: 0.75rem; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.05em; text-transform: uppercase;">'
                    . View::e($processTypeStr)
                    . '</span>'
                    . '<h3 style="font-size: 1rem; font-weight: 750; color: #1e2b57; margin: 8px 0 4px 0;">'
                    . 'Candidat / Salarié : ' . $employeeName
                    . '</h3>'
                    . '<small style="color: #64748b; font-weight: 500;">'
                    . 'Étape : <strong>' . $stepStr . '</strong> • Créé le ' . $createdAt
                    . '</small>'
                    . '</div>'
                    . '<form method="post" action="' . $actionUrl . '" style="display: flex; gap: 8px;">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . '<button type="submit" name="decision" value="approve" class="finea-btn" style="background: #10b981; border-color: #10b981; color: white; font-weight: 700; font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; cursor: pointer;">'
                    . 'Valider l\'étape'
                    . '</button>'
                    . '<button type="submit" name="decision" value="reject" class="finea-btn" style="background: #ef4444; border-color: #ef4444; color: white; font-weight: 700; font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; cursor: pointer;">'
                    . 'Refuser'
                    . '</button>'
                    . '</form>'
                    . '</div>';
            }
            $listHtml = '<div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: 15px;">' . $listHtml . '</div>';
        }

        return '<div class="rh-card" style="padding: 24px; border-radius: 16px;">'
            . '<h2 class="rh-card-title">Workflows en attente</h2>'
            . '<p class="rh-card-subtitle">Événements de cycle de vie et signatures de documents requis.</p>'
            . $listHtml
            . '</div>';
    }

    public static function validationModal(string $csrfToken): string
    {
        $modalHtml = '<div id="validation-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">'
            . '<div class="rh-card" style="width: 100%; max-width: 500px; margin: 0; position: relative; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">'
            . '<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dfe6f1; padding-bottom: 12px; margin-bottom: 16px;">'
            . '<h3 style="margin: 0; font-size: 1.2rem; font-weight: 850; color: #1e2b57;">Traiter la demande</h3>'
            . '<button type="button" onclick="closeValidationModal()" style="background: none; border: none; cursor: pointer; color: #64748b;">'
            . '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>'
            . '</button>'
            . '</div>'
            . '<form id="modal-decision-form" method="post" action="">'
            . Form::hidden('_csrf_token', $csrfToken)
            . '<div style="background: #f8fafc; border: 1px solid #dfe6f1; border-radius: 8px; padding: 12px; font-size: 0.85rem; margin-bottom: 16px; color: #475569;">'
            . '<div style="margin-bottom: 4px;"><strong>Réf :</strong> <span id="modal-ref-text"></span></div>'
            . '<div style="margin-bottom: 4px;"><strong>Collaborateur :</strong> <span id="modal-emp-text"></span></div>'
            . '<div style="margin-bottom: 4px;"><strong>Type :</strong> <span id="modal-type-text"></span></div>'
            . '<div style="margin-bottom: 4px;"><strong>Titre :</strong> <span id="modal-title-text"></span></div>'
            . '<div id="modal-extra-details"></div>'
            . '</div>'
            . '<div class="form-group" style="margin-bottom: 20px;">'
            . '<label for="modal-comment" class="finea-form-label" style="font-weight: 700; margin-bottom: 6px; display: block; font-size: 0.85rem; color: #1e2b57;">Commentaire / Motif de la décision</label>'
            . '<textarea name="comment" id="modal-comment" placeholder="Ajouter un motif ou commentaire (optionnel)..." class="finea-form-control" style="width: 100%; height: 80px; border: 1px solid #dfe6f1; border-radius: 8px; padding: 10px; font-size: 0.9rem; resize: vertical;"></textarea>'
            . '</div>'
            . '<div style="display: flex; justify-content: flex-end; gap: 8px;">'
            . '<button type="submit" name="decision" value="approve" class="finea-btn" style="background: #10b981; border-color: #10b981; color: white; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; cursor: pointer;">'
            . 'Approuver'
            . '</button>'
            . '<button type="submit" name="decision" value="reject" class="finea-btn" style="background: #ef4444; border-color: #ef4444; color: white; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; cursor: pointer;">'
            . 'Rejeter'
            . '</button>'
            . '<button type="button" onclick="closeValidationModal()" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; cursor: pointer;">'
            . 'Annuler'
            . '</button>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';

        $scriptHtml = '<script>'
            . 'function openValidationModal(data) {'
            . '    document.getElementById(\'modal-ref-text\').textContent = data.ref;'
            . '    document.getElementById(\'modal-emp-text\').textContent = data.employee;'
            . '    document.getElementById(\'modal-type-text\').textContent = data.type;'
            . '    document.getElementById(\'modal-title-text\').textContent = data.title;'
            . '    const extraDiv = document.getElementById(\'modal-extra-details\');'
            . '    extraDiv.innerHTML = \'\';'
            . '    if (data.start_date || data.end_date) {'
            . '        extraDiv.innerHTML += `<div style="margin-bottom: 4px;"><strong>Période :</strong> du ${data.start_date} au ${data.end_date}</div>`;'
            . '    }'
            . '    if (data.amount && data.amount.trim() !== \'\') {'
            . '        extraDiv.innerHTML += `<div style="margin-bottom: 4px;"><strong>Montant :</strong> ${data.amount} XOF</div>`;'
            . '    }'
            . '    document.getElementById(\'modal-decision-form\').action = data.action;'
            . '    document.getElementById(\'validation-modal\').style.display = \'flex\';'
            . '}'
            . 'function closeValidationModal() {'
            . '    document.getElementById(\'validation-modal\').style.display = \'none\';'
            . '    document.getElementById(\'modal-comment\').value = \'\';'
            . '}'
            . '</script>';

        return $modalHtml . $scriptHtml;
    }
}
