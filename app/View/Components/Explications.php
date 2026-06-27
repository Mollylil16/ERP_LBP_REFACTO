<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Rh\ExplicationIndexPage;

final class Explications
{
    public static function explicationsPage(
        ExplicationIndexPage $page,
        string $tab,
        array $metrics,
        string $csrfToken
    ): string {
        $countFlux = $metrics['flux'] ?? 0;
        $countSurveillance = $metrics['surveillance'] ?? 0;
        $countDecision = $metrics['decision'] ?? 0;

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

        $headerBanner = '<div class="rh-hero" style="padding: 32px; border-radius: 16px; margin-bottom: 24px; color: #ffffff; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(29, 43, 87, 0.15);">'
            . '<div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; position: relative; z-index: 2;">'
            . '<div>'
            . '<span class="rh-eyebrow" style="color: #cbd5e1; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">RESSOURCES HUMAINES</span>'
            . '<h1 style="font-size: 2.2rem; font-weight: 850; color: #ffffff; margin: 6px 0 8px 0; letter-spacing: -0.02em;">Demandes d\'explications</h1>'
            . '<p style="font-size: 0.95rem; color: #cbd5e1; margin: 0; max-width: 600px; line-height: 1.5;">'
            . 'Créez, diffusez, relancez et clôturez les dossiers RH avec PDF officiel, signatures manuscrites et historique centralisé.'
            . '</p>'
            . '</div>'
            . '<div style="display: flex; gap: 8px;">'
            . '<a href="#" onclick="alert(\'Hiérarchie des approbations en cours...\'); return false;" class="finea-btn" style="background: #ffffff; border-color: #ffffff; color: #1e2b57; font-weight: 700; font-size: 0.85rem; padding: 10px 18px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
            . 'Hiérarchie'
            . '</a>'
            . '<a href="' . View::url('rh/dashboard') . '" class="finea-btn" style="border: 1px solid rgba(255,255,255,0.3); background: transparent; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 18px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>'
            . 'Retour dashboard'
            . '</a>'
            . '</div>'
            . '</div>'
            . '</div>';

        $leftColumn = self::requestForm('rh/explications', $page->employees, $csrfToken);

        $rightColumn = self::listGroup($page->explications, $tab, $metrics, [$page, 'formatStatus'])
            . self::explanationDetails($csrfToken);

        $script = '<script>'
            . 'document.addEventListener(\'DOMContentLoaded\', function() {'
            . '    const inputSubject = document.getElementById(\'explanation_subject\');'
            . '    const inputFacts = document.getElementById(\'facts\');'
            . '    const inputExplanations = document.getElementById(\'expected_explanations\');'
            . '    const previewObj = document.getElementById(\'preview-obj\');'
            . '    const previewFacts = document.getElementById(\'preview-facts\');'
            . '    function updatePreview() {'
            . '        if (previewObj) previewObj.textContent = inputSubject.value.trim() || \'[Objet]\';'
            . '        if (previewFacts) previewFacts.textContent = inputFacts.value.trim() || \'[Faits]\';'
            . '    }'
            . '    if (inputSubject) inputSubject.addEventListener(\'input\', updatePreview);'
            . '    if (inputFacts) inputFacts.addEventListener(\'input\', updatePreview);'
            . '    const btnShowResp = document.getElementById(\'btn-show-response-form\');'
            . '    if (btnShowResp) {'
            . '        btnShowResp.addEventListener(\'click\', function() {'
            . '            const inlineForm = document.getElementById(\'inline-response-form\');'
            . '            if (inlineForm) inlineForm.style.display = \'block\';'
            . '        });'
            . '    }'
            . '});'
            . 'function selectExplanationRow(el) {'
            . '    document.querySelectorAll(\'.explanation-list-item\').forEach(item => {'
            . '        item.style.borderColor = \'#dfe6f1\';'
            . '        item.style.background = \'#ffffff\';'
            . '    });'
            . '    el.style.borderColor = \'#7c3aed\';'
            . '    el.style.background = \'#f5f3ff\';'
            . '    const data = el.dataset;'
            . '    showExplanationDetails({'
            . '        id: data.id,'
            . '        subject: data.subject,'
            . '        employee: data.employee,'
            . '        emp_number: data.empNumber,'
            . '        status: data.status,'
            . '        status_text: data.statusText,'
            . '        badge_class: data.badgeClass,'
            . '        incident_period: data.incidentPeriod,'
            . '        incident_location: data.incidentLocation,'
            . '        is_dg_copy: parseInt(data.isDgCopy) || 0,'
            . '        general_context: data.generalContext,'
            . '        facts: data.facts,'
            . '        expected_explanations: data.expectedExplanations,'
            . '        additional_elements: data.additionalElements,'
            . '        employee_response: data.employeeResponse,'
            . '        responded_at: data.respondedAt,'
            . '        action_respond: data.actionRespond,'
            . '        action_relancer: data.actionRelancer,'
            . '        action_cloturer: data.actionCloturer'
            . '    });'
            . '}'
            . 'function showExplanationDetails(data) {'
            . '    const detailsPlaceholder = document.getElementById(\'details-placeholder\');'
            . '    if (detailsPlaceholder) detailsPlaceholder.style.display = \'none\';'
            . '    const card = document.getElementById(\'details-card\');'
            . '    if (card) card.style.display = \'block\';'
            . '    const subjectEl = document.getElementById(\'details-subject\');'
            . '    if (subjectEl) subjectEl.textContent = data.subject;'
            . '    const empEl = document.getElementById(\'details-employee\');'
            . '    if (empEl) empEl.textContent = \'Collaborateur : \' + data.employee + \' (Matricule: \' + data.emp_number + \')\';'
            . '    const badge = document.getElementById(\'details-status-badge\');'
            . '    if (badge) {'
            . '        badge.className = \'finea-status-badge finea-status-badge--\' + data.badge_class;'
            . '        badge.textContent = data.status_text;'
            . '    }'
            . '    const periodEl = document.getElementById(\'details-period\');'
            . '    if (periodEl) periodEl.textContent = data.incident_period || \'Non précisée\';'
            . '    const locEl = document.getElementById(\'details-location\');'
            . '    if (locEl) locEl.textContent = data.incident_location || \'Non précisé\';'
            . '    const dgEl = document.getElementById(\'details-dg-copy\');'
            . '    if (dgEl) dgEl.textContent = data.is_dg_copy ? \'Oui (DG en copie uniquement)\' : \'Non (DG non notifié)\';'
            . '    const contextEl = document.getElementById(\'details-context\');'
            . '    if (contextEl) contextEl.textContent = data.general_context || \'N/A\';'
            . '    const factsEl = document.getElementById(\'details-facts\');'
            . '    if (factsEl) factsEl.textContent = data.facts || \'N/A\';'
            . '    const expectedEl = document.getElementById(\'details-expected\');'
            . '    if (expectedEl) expectedEl.textContent = data.expected_explanations || \'N/A\';'
            . '    const addEl = document.getElementById(\'details-additional\');'
            . '    if (addEl) addEl.textContent = data.additional_elements || \'N/A\';'
            . '    const respSec = document.getElementById(\'details-response-section\');'
            . '    const respText = document.getElementById(\'details-response-text\');'
            . '    const respMeta = document.getElementById(\'details-response-meta\');'
            . '    const btnShowRespForm = document.getElementById(\'btn-show-response-form\');'
            . '    if (data.employee_response && data.employee_response.trim() !== \'\') {'
            . '        if (respSec) respSec.style.display = \'block\';'
            . '        if (respText) respText.textContent = data.employee_response;'
            . '        if (respMeta) respMeta.textContent = \'Enregistrée le \' + data.responded_at;'
            . '        if (btnShowRespForm) btnShowRespForm.style.display = \'none\';'
            . '    } else {'
            . '        if (respSec) respSec.style.display = \'none\';'
            . '        if (btnShowRespForm) btnShowRespForm.style.display = data.status === \'closed\' ? \'none\' : \'block\';'
            . '    }'
            . '    const inlineForm = document.getElementById(\'inline-response-form\');'
            . '    if (inlineForm) inlineForm.style.display = \'none\';'
            . '    const formRespond = document.getElementById(\'form-respond\');'
            . '    if (formRespond) formRespond.action = data.action_respond;'
            . '    const formRelancer = document.getElementById(\'form-relancer\');'
            . '    if (formRelancer) {'
            . '        formRelancer.action = data.action_relancer;'
            . '        formRelancer.style.display = data.status === \'closed\' ? \'none\' : \'block\';'
            . '    }'
            . '    const formCloturer = document.getElementById(\'form-cloturer\');'
            . '    if (formCloturer) {'
            . '        formCloturer.action = data.action_cloturer;'
            . '        formCloturer.style.display = data.status === \'closed\' ? \'none\' : \'block\';'
            . '    }'
            . '}'
            . '</script>';

        return '<div class="finea-shell rh-explications-page">'
            . '<div class="finea-container">'
            . $headerBanner
            . $errorAlert
            . $successAlert
            . '<div style="display: grid; grid-template-columns: 1.1fr 1.4fr; gap: 24px; align-items: start;">'
            . '<div>' . $leftColumn . '</div>'
            . '<div>' . $rightColumn . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . $script;
    }
    /**
     * @param array<int,array<string,mixed>> $employees
     */
    public static function requestForm(string $action, array $employees, string $csrfToken): string
    {
        $employeeOptions = array_map(function($emp) {
            return [
                'value' => (int)$emp['id'],
                'label' => (string)$emp['full_name'] . ' (' . ($emp['employee_number'] ?: 'Sans') . ')',
            ];
        }, $employees);

        $employeeSelect = Form::select('employee_id', 'Collaborateur concerné', $employeeOptions, '', [
            'id' => 'employee_id',
            'required' => true,
            'class' => 'finea-form-control',
            'placeholder' => 'Rechercher un collaborateur...',
            'hint' => 'Tapez quelques lettres puis choisissez un collaborateur dans la liste.',
        ]);

        $subjectInput = Form::input('subject', [
            'label' => 'Objet',
            'placeholder' => 'Ex: Demande d\'explications sur retard chantier...',
            'required' => true,
            'id' => 'explanation_subject',
        ]);

        $dueInput = Form::input('response_due_days', [
            'label' => 'Délai de réponse',
            'type' => 'number',
            'value' => '3',
            'min' => '1',
            'required' => true,
            'id' => 'response_due_days',
        ]);

        $periodInput = Form::input('incident_period', [
            'label' => 'Date ou période concernée',
            'placeholder' => 'Ex: 05/04/2026 ou semaine du 1er avril',
            'id' => 'incident_period',
        ]);

        $locationInput = Form::input('incident_location', [
            'label' => 'Lieu ou cadre concerné',
            'placeholder' => 'Ex: Chantier, bureau, mission',
            'id' => 'incident_location',
        ]);

        $contextTextarea = Form::textarea('general_context', [
            'label' => 'Contexte de la demande',
            'placeholder' => 'Rappelez brièvement le contexte général de la demande.',
            'id' => 'general_context',
            'rows' => 3,
        ]);

        $factsTextarea = Form::textarea('facts', [
            'label' => 'Faits constatés',
            'placeholder' => 'Décrivez les faits de façon précise. Une ligne par fait si nécessaire.',
            'id' => 'facts',
            'rows' => 3,
            'required' => true,
        ]);

        $expectedTextarea = Form::textarea('expected_explanations', [
            'label' => 'Explications attendues',
            'placeholder' => 'Précisez les points sur lesquels le collaborateur doit répondre.',
            'id' => 'expected_explanations',
            'rows' => 3,
        ]);

        $additionalTextarea = Form::textarea('additional_elements', [
            'label' => 'Éléments complémentaires',
            'placeholder' => 'Pièces, témoins, consignes internes, rappel de procédure, etc.',
            'id' => 'additional_elements',
            'rows' => 3,
        ]);

        $formContent = Form::hidden('_csrf_token', $csrfToken)
            . $employeeSelect
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">'
            . '<div>' . $subjectInput . '</div>'
            . '<div>' . $dueInput . '</div>'
            . '</div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">'
            . '<div>' . $periodInput . '</div>'
            . '<div>' . $locationInput . '</div>'
            . '</div>'
            . '<div class="form-group" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">'
            . '<input type="checkbox" name="is_dg_copy" id="is_dg_copy" value="1" style="width: 16px; height: 16px; accent-color: #7c3aed; cursor: pointer;">'
            . '<label for="is_dg_copy" style="font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; user-select: none;">'
            . 'Demande adressée directement à un cadre : DG uniquement en copie'
            . '</label>'
            . '</div>'
            . $contextTextarea
            . $factsTextarea
            . $expectedTextarea
            . $additionalTextarea
            . '<div class="rh-card" style="padding: 16px; background: #fafafa; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px;">'
            . '<span style="font-size: 0.65rem; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">APERCU DU MODELE GENERE</span>'
            . '<p style="font-size: 0.75rem; color: #64748b; margin: 0 0 10px 0;">Le texte final et le PDF sont construits automatiquement à partir des champs saisis.</p>'
            . '<div id="preview-content" style="background: #ffffff; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; font-size: 0.85rem; color: #374151; line-height: 1.5; font-family: monospace;">'
            . '<p>Dans le cadre du suivi RH, nous vous prions de bien vouloir fournir vos explications sur les éléments ci-dessous.</p>'
            . '<div style="margin: 10px 0; padding-left: 10px; border-left: 2px solid #10b981;">'
            . '<strong>Objet :</strong> <span id="preview-obj">[Objet]</span><br>'
            . '<strong>Faits :</strong> <span id="preview-facts">[Faits]</span>'
            . '</div>'
            . '<p>Nous vous demandons de transmettre une réponse claire et complète dans le délai indiqué.</p>'
            . '</div>'
            . '</div>'
            . '<button type="submit" class="finea-btn" style="background: #047857; border-color: #047857; color: #ffffff; font-weight: 700; font-size: 0.85rem; padding: 10px 24px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 10px rgba(4, 120, 87, 0.15);">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>'
            . 'Envoyer la demande'
            . '</button>';

        $cardHtml = Rh::card($formContent, [
            'title' => 'Nouvelle demande',
            'eyebrow' => 'CRÉATION',
            'meta' => 'Le texte officiel est généré automatiquement à partir des champs ci-dessous, puis injecté dans le PDF et les emails.',
        ]);

        return Rh::form($action, $cardHtml);
    }

    /**
     * @param array<int,array<string,mixed>> $explications
     * @param array<string,int> $metrics
     */
    public static function listGroup(array $explications, string $tab, array $metrics, callable $formatStatus): string
    {
        $tabs = [
            'open' => 'Ouvertes',
            'closed' => 'Cloturees',
            'cancelled' => 'Annulees',
            'all' => 'Toutes'
        ];

        $tabsHtml = '<div style="display: flex; gap: 6px; flex-wrap: wrap;">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key);
            $style = $active 
                ? 'background: #0f172a; border-color: #0f172a; color: #ffffff;' 
                : 'background: #ffffff; border: 1px solid #cbd5e1; color: #475569;';
            
            $tabsHtml .= '<a href="' . View::url('rh/explications?tab=' . $key) . '" class="finea-btn" style="' . $style . ' font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 999px; text-decoration: none;">'
                . View::e($label)
                . '</a>';
        }
        $tabsHtml .= '</div>';

        $metricsHtml = '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">'
            . '<div style="background: #f8fafc; border: 1px solid #dfe6f1; border-radius: 12px; padding: 12px;">'
            . '<span style="font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; display: block;">FLUX</span>'
            . '<strong style="font-size: 1.6rem; color: #1e2b57; display: block; margin-top: 4px;">' . (int)($metrics['flux'] ?? 0) . '</strong>'
            . '<span style="font-size: 0.75rem; color: #64748b; display: block; margin-top: 2px;">Dossiers filtrés.</span>'
            . '</div>'
            . '<div style="background: #f8fafc; border: 1px solid #dfe6f1; border-radius: 12px; padding: 12px;">'
            . '<span style="font-size: 0.65rem; font-weight: 700; color: #b45309; text-transform: uppercase; display: block;">SOUS SURVEILLANCE</span>'
            . '<strong style="font-size: 1.6rem; color: #b45309; display: block; margin-top: 4px;">' . (int)($metrics['surveillance'] ?? 0) . '</strong>'
            . '<span style="font-size: 0.75rem; color: #b45309; display: block; margin-top: 2px;">Réponses attendues.</span>'
            . '</div>'
            . '<div style="background: #f8fafc; border: 1px solid #dfe6f1; border-radius: 12px; padding: 12px;">'
            . '<span style="font-size: 0.65rem; font-weight: 700; color: #047857; text-transform: uppercase; display: block;">PRETS DECISION</span>'
            . '<strong style="font-size: 1.6rem; color: #047857; display: block; margin-top: 4px;">' . (int)($metrics['decision'] ?? 0) . '</strong>'
            . '<span style="font-size: 0.75rem; color: #047857; display: block; margin-top: 2px;">Réponses reçues.</span>'
            . '</div>'
            . '</div>';

        $itemsHtml = '';
        if ($explications === []) {
            $itemsHtml = '<div style="text-align: center; color: #64748b; padding: 20px; font-size: 0.85rem;">'
                . 'Aucun dossier trouvé dans cette catégorie.'
                . '</div>';
        } else {
            foreach ($explications as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? 'pending_response');
                $statusText = $formatStatus($status);

                $badgeClass = 'danger';
                if ($status === 'closed') {
                    $badgeClass = 'neutral';
                } elseif ($status === 'responded') {
                    $badgeClass = 'success';
                } elseif ($status === 'complement_requested') {
                    $badgeClass = 'warning';
                }

                $itemsHtml .= '<div class="explanation-list-item" '
                    . 'data-id="' . $rowId . '" '
                    . 'data-subject="' . View::e((string)$row['subject']) . '" '
                    . 'data-employee="' . View::e((string)$row['employee_name']) . '" '
                    . 'data-emp-number="' . View::e((string)($row['employee_number'] ?: 'N/A')) . '" '
                    . 'data-status="' . $status . '" '
                    . 'data-status-text="' . View::e($statusText) . '" '
                    . 'data-badge-class="' . $badgeClass . '" '
                    . 'data-incident-period="' . View::e((string)$row['incident_period']) . '" '
                    . 'data-incident-location="' . View::e((string)$row['incident_location']) . '" '
                    . 'data-is-dg-copy="' . (int)$row['is_dg_copy'] . '" '
                    . 'data-general-context="' . View::e((string)$row['general_context']) . '" '
                    . 'data-facts="' . View::e((string)$row['facts']) . '" '
                    . 'data-expected-explanations="' . View::e((string)$row['expected_explanations']) . '" '
                    . 'data-additional-elements="' . View::e((string)$row['additional_elements']) . '" '
                    . 'data-employee-response="' . View::e((string)$row['employee_response']) . '" '
                    . 'data-responded-at="' . ($row['responded_at'] ? date('d/m/Y \a\t H:i', strtotime((string)$row['responded_at'])) : '') . '" '
                    . 'data-action-respond="' . View::url('rh/explications/respond/' . $rowId) . '" '
                    . 'data-action-relancer="' . View::url('rh/explications/relancer/' . $rowId) . '" '
                    . 'data-action-cloturer="' . View::url('rh/explications/cloturer/' . $rowId) . '" '
                    . 'onclick="selectExplanationRow(this)" '
                    . 'style="border: 1px solid #dfe6f1; border-radius: 8px; padding: 12px; background: #ffffff; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">'
                    . '<div>'
                    . '<strong style="display: block; font-size: 0.9rem; color: #1e2b57;">' . View::e((string)$row['subject']) . '</strong>'
                    . '<span style="font-size: 0.8rem; color: #64748b;">Collaborateur : ' . View::e((string)$row['employee_name']) . '</span>'
                    . '</div>'
                    . '<span class="finea-status-badge finea-status-badge--' . $badgeClass . '" style="font-size: 0.75rem; padding: 2px 6px;">'
                    . View::e($statusText)
                    . '</span>'
                    . '</div>';
            }
        }

        $listSectionHtml = '<div style="background: #f8fafc; border: 1px solid #dfe6f1; border-radius: 12px; padding: 16px; margin-bottom: 20px;">'
            . '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">'
            . '<span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">FILE ACTIVE</span>'
            . '<span style="font-size: 0.75rem; font-weight: 700; background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 999px;">'
            . count($explications) . ' dossier(s)'
            . '</span>'
            . '</div>'
            . '<h3 style="font-size: 0.95rem; font-weight: 800; color: #1e2b57; margin: 0 0 12px 0;">Dossiers du filtre sélectionné</h3>'
            . '<div style="display: flex; flex-direction: column; gap: 10px; max-height: 350px; overflow-y: auto; padding: 4px;">'
            . $itemsHtml
            . '</div>'
            . '</div>';

        $headerHtml = '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">'
            . '<div>'
            . '<span style="font-size: 0.7rem; font-weight: 800; color: #7c3aed; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">SUIVI</span>'
            . '<h2 class="rh-card-title" style="margin-bottom: 4px;">Centre de pilotage des dossiers</h2>'
            . '<p class="rh-card-subtitle" style="margin-bottom: 0;">Consultez les cas actifs, contrôlez les signatures, renvoyez le PDF et pilotez chaque décision RH depuis une seule vue.</p>'
            . '</div>'
            . $tabsHtml
            . '</div>';

        return Rh::card($headerHtml . $metricsHtml . $listSectionHtml, [
            'class' => 'rh-explications-list-card',
        ]);
    }

    public static function explanationDetails(string $csrfToken): string
    {
        $inlineResponseForm = '<div id="inline-response-form" style="display: none; margin-top: 16px; padding: 16px; border: 1px solid #dfe6f1; border-radius: 12px; background: #f8fafc;">'
            . '<form id="form-respond" method="post" action="">'
            . Form::hidden('_csrf_token', $csrfToken)
            . '<div class="form-group" style="margin-bottom: 12px;">'
            . '<label class="finea-form-label" style="font-weight: 700; margin-bottom: 6px; display: block; font-size: 0.85rem; color: #1e2b57;">Saisir la réponse écrite du collaborateur</label>'
            . '<textarea name="response" required class="finea-form-control" style="width: 100%; height: 80px; padding: 8px; border: 1px solid #dfe6f1; border-radius: 8px; font-size: 0.9rem; resize: vertical; background: #ffffff;" placeholder="Copier-coller ou résumer la réponse reçue..."></textarea>'
            . '</div>'
            . '<div style="display: flex; gap: 8px; justify-content: flex-end;">'
            . '<button type="button" onclick="document.getElementById(\'inline-response-form\').style.display=\'none\'" class="finea-btn" style="border: 1px solid #cbd5e1; background: transparent; color: #64748b; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 8px; cursor: pointer;">Annuler</button>'
            . '<button type="submit" class="finea-btn" style="background: #10b981; border-color: #10b981; color: white; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 8px; cursor: pointer;">Enregistrer</button>'
            . '</div>'
            . '</form>'
            . '</div>';

        $detailsCard = '<div id="details-placeholder" class="history-card-placeholder" style="padding: 32px; border: 1.5px dashed #cbd5e1; border-radius: 12px; text-align: center; color: #64748b; font-size: 0.9rem;">'
            . 'Sélectionnez un dossier pour afficher le détail.'
            . '</div>'
            . '<div id="details-card" class="rh-card" style="display: none; border-radius: 12px; padding: 20px; border: 1px solid #dfe6f1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">'
            . '<div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #edf1f7; padding-bottom: 12px; margin-bottom: 16px;">'
            . '<div>'
            . '<h3 id="details-subject" style="margin: 0; font-size: 1.15rem; font-weight: 850; color: #1e2b57;"></h3>'
            . '<span id="details-employee" style="font-size: 0.85rem; color: #64748b; font-weight: 600;"></span>'
            . '</div>'
            . '<span id="details-status-badge" class="finea-status-badge" style="font-size: 0.8rem; padding: 4px 8px; font-weight: 700;"></span>'
            . '</div>'
            . '<div style="font-size: 0.875rem; color: #475569; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">'
            . '<div><strong>Date de l\'incident / Période :</strong> <span id="details-period" style="font-weight: 600; color: #1e293b;"></span></div>'
            . '<div><strong>Lieu / Cadre :</strong> <span id="details-location" style="font-weight: 600; color: #1e293b;"></span></div>'
            . '<div><strong>DG en copie :</strong> <span id="details-dg-copy" style="font-weight: 600; color: #1e293b;"></span></div>'
            . '<div><strong>Contexte :</strong><p id="details-context" style="margin: 4px 0 0; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.4; color: #1e293b;"></p></div>'
            . '<div><strong>Faits constatés :</strong><p id="details-facts" style="margin: 4px 0 0; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.4; color: #1e293b;"></p></div>'
            . '<div><strong>Explications attendues :</strong><p id="details-expected" style="margin: 4px 0 0; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.4; color: #1e293b;"></p></div>'
            . '<div><strong>Éléments complémentaires :</strong><p id="details-additional" style="margin: 4px 0 0; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.4; color: #1e293b;"></p></div>'
            . '</div>'
            . '<div id="details-response-section" style="display: none; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1); border-radius: 8px; padding: 14px; margin-bottom: 20px;">'
            . '<strong style="color: #047857; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 6px;">Réponse du collaborateur</strong>'
            . '<p id="details-response-text" style="margin: 0; font-size: 0.9rem; color: #1e293b; line-height: 1.5; font-style: italic;"></p>'
            . '<small id="details-response-meta" style="display: block; color: #64748b; margin-top: 6px; font-weight: 500;"></small>'
            . '</div>'
            . '<div style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid #edf1f7; padding-top: 16px;">'
            . '<button type="button" id="btn-show-response-form" class="finea-btn" style="background: #10b981; border-color: #10b981; color: white; font-weight: 700; font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">'
            . '<svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'
            . 'Saisir réponse'
            . '</button>'
            . '<form id="form-relancer" method="post" action="" style="margin: 0;">'
            . Form::hidden('_csrf_token', $csrfToken)
            . '<button type="submit" class="finea-btn" style="background: #f59e0b; border-color: #f59e0b; color: white; font-weight: 700; font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">'
            . '<svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></svg>'
            . 'Relancer'
            . '</button>'
            . '</form>'
            . '<form id="form-cloturer" method="post" action="" style="margin: 0;">'
            . Form::hidden('_csrf_token', $csrfToken)
            . '<button type="submit" class="finea-btn" style="background: #64748b; border-color: #64748b; color: white; font-weight: 700; font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">'
            . '<svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>'
            . 'Clôturer'
            . '</button>'
            . '</form>'
            . '</div>'
            . $inlineResponseForm
            . '</div>';

        return $detailsCard;
    }
}
