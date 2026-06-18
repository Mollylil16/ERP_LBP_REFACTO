<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Employee\EmployeeRequestCatalog;

final class EmployeeRequestForms
{
    public static function render(string $csrfToken, string $selected = ''): string
    {
        $catalog = EmployeeRequestCatalog::all();
        if (!isset($catalog[$selected])) $selected = (string) array_key_first($catalog);
        $html = '<div class="employee-request-workspace" data-request-workspace>'
            . '<aside class="employee-request-catalog" aria-label="Types de demandes">';
        foreach ($catalog as $type => $config) {
            $html .= '<button type="button" class="employee-request-choice tone-' . View::e($config['tone'])
                . ($selected === $type ? ' is-active' : '') . '" data-request-choice="' . View::e($type) . '">'
                . '<span>' . View::e($config['icon']) . '</span><strong>' . View::e($config['label'])
                . '</strong><small>' . View::e($config['description']) . '</small></button>';
        }
        $html .= '</aside><div class="employee-request-panels">';
        foreach ($catalog as $type => $config) {
            $html .= self::form($type, $config, $csrfToken, $selected === $type);
        }
        return $html . '</div></div>';
    }

    private static function form(string $type, array $config, string $csrfToken, bool $active): string
    {
        $content = match ($type) {
            'leave' => self::leave(),
            'absence' => self::absence(),
            'lateness' => self::lateness(),
            'attendance_correction' => self::attendanceCorrection(),
            'salary_advance' => self::salaryAdvance(),
            'document' => self::document(),
            default => self::other(),
        };

        return '<form method="post" enctype="multipart/form-data" action="' . View::url('espace-employe/demandes')
            . '" class="finea-section-card employee-specialized-form' . ($active ? ' is-active' : '')
            . '" data-request-panel="' . View::e($type) . '"' . ($active ? '' : ' hidden') . '>'
            . '<header><span class="employee-form-icon tone-' . View::e($config['tone']) . '">' . View::e($config['icon']) . '</span><div><p>Nouvelle demande</p><h2>'
            . View::e($config['label']) . '</h2><small>' . View::e($config['description']) . '</small></div></header>'
            . Form::hidden('_csrf_token', $csrfToken) . Form::hidden('request_type', $type)
            . '<div class="employee-specialized-fields">' . $content . '</div>'
            . '<footer><span>Transmission sécurisée · Manager → RH → Direction selon la catégorie</span>'
            . Ui::button('Soumettre la demande', ['variant' => 'accent', 'type' => 'submit']) . '</footer></form>';
    }

    private static function leave(): string
    {
        return '<div class="employee-field-grid">'
            . Form::input('start_date', 'Premier jour de congé', '', ['type' => 'date', 'required' => true])
            . Form::input('end_date', 'Dernier jour de congé', '', ['type' => 'date', 'required' => true])
            . Form::select('leave_kind', 'Nature du congé', [
                ['value' => 'annual', 'label' => 'Congé annuel'],
                ['value' => 'family', 'label' => 'Événement familial'],
                ['value' => 'maternity_paternity', 'label' => 'Maternité / paternité'],
                ['value' => 'unpaid', 'label' => 'Congé sans solde'],
            ], null, ['required' => true])
            . Form::input('handover', 'Relais pendant l’absence', '', ['hint' => 'Nom du collègue ou organisation prévue'])
            . '</div>' . Form::textarea('reason', 'Précisions', '', ['rows' => 4, 'required' => true]);
    }

    private static function absence(): string
    {
        return '<div class="employee-field-grid">'
            . Form::input('start_date', 'Début de l’absence', '', ['type' => 'date', 'required' => true])
            . Form::input('end_date', 'Fin de l’absence', '', ['type' => 'date'])
            . Form::select('absence_kind', 'Type d’absence', [
                ['value' => 'planned', 'label' => 'Absence prévue'],
                ['value' => 'medical', 'label' => 'Maladie / raison médicale'],
                ['value' => 'emergency', 'label' => 'Urgence familiale'],
                ['value' => 'justification', 'label' => 'Justification après absence'],
            ], null, ['required' => true])
            . '</div>' . Form::textarea('reason', 'Motif circonstancié', '', ['rows' => 4, 'required' => true])
            . Form::dropzone('attachment', 'Ajouter un justificatif', ['accept' => '.pdf,image/jpeg,image/png,image/webp', 'hint' => 'Certificat, convocation ou autre preuve · PDF/image · 5 Mo maximum']);
    }

    private static function lateness(): string
    {
        return '<div class="employee-field-grid">'
            . Form::input('incident_date', 'Date du retard', '', ['type' => 'date', 'required' => true])
            . Form::input('arrival_time', 'Heure d’arrivée réelle', '', ['type' => 'time', 'required' => true])
            . '</div>' . Form::textarea('reason', 'Cause du retard', '', ['rows' => 4, 'required' => true])
            . Form::dropzone('attachment', 'Justificatif éventuel', ['accept' => '.pdf,image/jpeg,image/png,image/webp', 'hint' => 'Preuve de transport, certificat ou document utile']);
    }

    private static function attendanceCorrection(): string
    {
        return '<div class="employee-field-grid">'
            . Form::input('incident_date', 'Journée à corriger', '', ['type' => 'date', 'required' => true])
            . Form::select('correction_kind', 'Correction demandée', [
                ['value' => 'missing_entry', 'label' => 'Entrée manquante'],
                ['value' => 'missing_exit', 'label' => 'Sortie manquante'],
                ['value' => 'wrong_time', 'label' => 'Horaire incorrect'],
                ['value' => 'wrong_status', 'label' => 'Statut de présence incorrect'],
            ], null, ['required' => true])
            . Form::input('check_in_time', 'Heure d’entrée correcte', '', ['type' => 'time'])
            . Form::input('check_out_time', 'Heure de sortie correcte', '', ['type' => 'time'])
            . '</div>' . Form::textarea('reason', 'Explication de la correction', '', ['rows' => 4, 'required' => true])
            . Form::dropzone('attachment', 'Preuve éventuelle', ['accept' => '.pdf,image/jpeg,image/png,image/webp']);
    }

    private static function salaryAdvance(): string
    {
        return '<div class="employee-field-grid">'
            . Form::input('amount', 'Montant demandé (FCFA)', '', ['type' => 'number', 'min' => 1, 'step' => 1, 'required' => true])
            . Form::select('repayment_months', 'Remboursement proposé', [
                ['value' => '1', 'label' => '1 mois'],
                ['value' => '2', 'label' => '2 mois'],
                ['value' => '3', 'label' => '3 mois'],
                ['value' => '4', 'label' => '4 mois'],
                ['value' => '6', 'label' => '6 mois'],
            ], null, ['required' => true])
            . Form::input('desired_payment_date', 'Date de versement souhaitée', '', ['type' => 'date'])
            . '</div>' . Form::textarea('reason', 'Motif de la demande', '', ['rows' => 4, 'required' => true]);
    }

    private static function document(): string
    {
        return '<div class="employee-field-grid">'
            . Form::select('document_kind', 'Document souhaité', [
                ['value' => 'work_certificate', 'label' => 'Attestation de travail'],
                ['value' => 'salary_certificate', 'label' => 'Attestation de salaire'],
                ['value' => 'employment_certificate', 'label' => 'Certificat d’emploi'],
                ['value' => 'contract_copy', 'label' => 'Copie du contrat'],
                ['value' => 'other', 'label' => 'Autre document'],
            ], null, ['required' => true])
            . Form::select('delivery_format', 'Format de livraison', [
                ['value' => 'digital', 'label' => 'Document numérique'],
                ['value' => 'paper', 'label' => 'Original papier'],
                ['value' => 'both', 'label' => 'Numérique et papier'],
            ], null, ['required' => true])
            . '</div>' . Form::textarea('reason', 'Usage ou précisions', '', ['rows' => 4, 'required' => true]);
    }

    private static function other(): string
    {
        return Form::input('subject', 'Objet de la demande', '', ['required' => true])
            . Form::textarea('reason', 'Description complète', '', ['rows' => 6, 'required' => true])
            . Form::dropzone('attachment', 'Pièce complémentaire', ['accept' => '.pdf,image/jpeg,image/png,image/webp']);
    }
}
