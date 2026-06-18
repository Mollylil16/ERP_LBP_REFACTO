<?php

declare(strict_types=1);

namespace App\Services\Employee;

final class EmployeeRequestCatalog
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            'leave' => [
                'label' => 'Demande de congé', 'icon' => 'CG', 'tone' => 'blue',
                'description' => 'Planifier une période de congé et organiser la continuité.',
                'fields' => ['start_date', 'end_date', 'leave_kind', 'handover', 'reason'],
            ],
            'absence' => [
                'label' => 'Absence / justification', 'icon' => 'AB', 'tone' => 'orange',
                'description' => 'Déclarer une absence prévue ou justifier une absence passée.',
                'fields' => ['start_date', 'end_date', 'absence_kind', 'reason', 'attachment'],
            ],
            'lateness' => [
                'label' => 'Retard', 'icon' => 'RT', 'tone' => 'amber',
                'description' => 'Signaler ou justifier un retard avec l’heure réelle.',
                'fields' => ['incident_date', 'arrival_time', 'reason', 'attachment'],
            ],
            'attendance_correction' => [
                'label' => 'Correction de pointage', 'icon' => 'PT', 'tone' => 'violet',
                'description' => 'Corriger une entrée, une sortie ou une journée manquante.',
                'fields' => ['incident_date', 'correction_kind', 'check_in_time', 'check_out_time', 'reason', 'attachment'],
            ],
            'salary_advance' => [
                'label' => 'Avance sur salaire', 'icon' => 'AV', 'tone' => 'green',
                'description' => 'Demander un montant avec une proposition de remboursement.',
                'fields' => ['amount', 'repayment_months', 'desired_payment_date', 'reason'],
            ],
            'document' => [
                'label' => 'Document RH', 'icon' => 'DO', 'tone' => 'cyan',
                'description' => 'Demander une attestation, un certificat ou une copie.',
                'fields' => ['document_kind', 'delivery_format', 'reason'],
            ],
            'other' => [
                'label' => 'Autre demande', 'icon' => 'AU', 'tone' => 'slate',
                'description' => 'Soumettre une demande RH qui ne correspond pas aux catégories précédentes.',
                'fields' => ['subject', 'reason', 'attachment'],
            ],
        ];
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }
}
