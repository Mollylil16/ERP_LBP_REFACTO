<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Employee\DashboardPage;
use App\View\Pages\Employee\RequestShowPage;

final class Employee
{
    /** @param array<int,array<string,mixed>> $rows */
    public static function attendance(array $rows): string
    {
        if ($rows === []) return Ui::emptyState('Aucun pointage disponible pour ce mois.');
        $html = '<div class="employee-attendance-list">';
        foreach ($rows as $row) {
            $html .= '<article><time>' . self::date($row['attendance_date'] ?? null)
                . '</time><strong>' . View::e((string) ($row['attendance_status'] ?? ''))
                . '</strong><span>' . View::e(substr((string) ($row['check_in_time'] ?? ''), 0, 5) ?: '—')
                . ' → ' . View::e(substr((string) ($row['check_out_time'] ?? ''), 0, 5) ?: '—')
                . '</span><small>' . number_format((float) ($row['worked_hours'] ?? 0), 1, ',', ' ')
                . ' h</small></article>';
        }
        return $html . '</div>';
    }

    public static function explanations(DashboardPage $page): string
    {
        if ($page->explanations === []) return Ui::emptyState('Aucune demande d’explication.');
        $html = '<div class="employee-explanation-list">';
        foreach ($page->explanations as $row) {
            $status = (string) ($row['status'] ?? '');
            $html .= '<article><header><strong>' . View::e((string) ($row['subject'] ?? ''))
                . '</strong><span class="employee-status status-' . View::e($status) . '">'
                . View::e($status) . '</span></header><p>' . View::e((string) ($row['facts'] ?? ''))
                . '</p><small>Réponse attendue avant le ' . self::date($row['response_due_date'] ?? null) . '</small>';
            if (in_array($status, ['pending_response', 'complement_requested'], true)) {
                $content = Form::hidden('_csrf_token', $page->csrfToken)
                    . Form::textarea('response', 'Votre réponse circonstanciée', '', [
                        'rows' => 7, 'minlength' => 20, 'required' => true,
                    ])
                    . Ui::button('Transmettre ma réponse', ['variant' => 'accent', 'type' => 'submit']);
                $form = '<form method="post" action="'
                    . View::url('espace-employe/explications/' . (int) ($row['id'] ?? 0) . '/repondre')
                    . '">' . $content . '</form>';
                $html .= Modal::render(
                    'explanation-' . (int) ($row['id'] ?? 0),
                    'Répondre à la demande d’explication',
                    $form,
                    'Répondre',
                    ['eyebrow' => 'Droit de réponse']
                );
            } elseif (!empty($row['employee_response'])) {
                $html .= '<blockquote>' . View::e((string) $row['employee_response']) . '</blockquote>';
            }
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $documents */
    public static function documents(array $documents): string
    {
        if ($documents === []) return Ui::emptyState('Aucun document disponible.');
        $html = '<div class="employee-document-grid">';
        foreach ($documents as $document) {
            $html .= '<a href="' . View::url('public/' . ltrim((string) ($document['stored_path'] ?? ''), '/'))
                . '" target="_blank" rel="noopener"><strong>'
                . View::e((string) ($document['original_name'] ?? 'Document')) . '</strong><small>'
                . View::e((string) ($document['document_type'] ?? '')) . '</small></a>';
        }
        return $html . '</div>';
    }

    public static function requestDetails(RequestShowPage $page): string
    {
        $request = $page->request;
        $html = EmployeeRequestSummary::details($request);
        if (!empty($request['attachment_path'])) {
            $html .= '<a class="employee-attachment-link" href="'
                . View::url('public/' . ltrim((string) $request['attachment_path'], '/'))
                . '" target="_blank" rel="noopener">Consulter le justificatif · '
                . View::e((string) (($request['attachment_original_name'] ?? '') ?: 'Pièce jointe')) . '</a>';
        }
        if ($page->canCancel()) {
            $html .= '<form method="post" action="'
                . View::url('espace-employe/demandes/' . (int) ($request['id'] ?? 0) . '/annuler')
                . '">' . Form::hidden('_csrf_token', $page->csrfToken)
                . Ui::button('Annuler cette demande', ['type' => 'submit', 'variant' => 'danger'])
                . '</form>';
        }
        return $html;
    }

    /** @param array<int,array<string,mixed>> $events */
    public static function timeline(array $events): string
    {
        $html = '<ol class="employee-timeline">';
        foreach ($events as $event) {
            $html .= '<li><strong>' . View::e((string) ($event['status'] ?? ''))
                . '</strong><span>' . View::e((string) ($event['comment'] ?? ''))
                . '</span><time>' . self::dateTime($event['created_at'] ?? null) . '</time></li>';
        }
        return $html . '</ol>';
    }

    private static function date(?string $value): string
    {
        $time = $value ? strtotime($value) : false;
        return $time ? date('d/m/Y', $time) : '—';
    }

    private static function dateTime(?string $value): string
    {
        $time = $value ? strtotime($value) : false;
        return $time ? date('d/m/Y H:i', $time) : '—';
    }
}
