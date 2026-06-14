<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class EmployeeCard
{
    /**
     * @param array<string,mixed> $employee
     * @param array<int,array{label:string,href:string,variant?:string}> $actions
     */
    public static function render(array $employee, array $actions = []): string
    {
        $name = trim((string) ($employee['full_name'] ?? 'Collaborateur'));
        $photoPath = trim((string) ($employee['photo_path'] ?? ''));
        $photoUrl = self::photoUrl($photoPath);
        $isActive = (int) ($employee['is_active'] ?? 0) === 1;
        $contact = trim((string) ($employee['email'] ?? ''))
            ?: trim((string) ($employee['phone'] ?? ''))
            ?: 'Contact non renseigné';

        $avatar = $photoUrl !== null
            ? '<img src="' . View::e($photoUrl) . '" alt="Photo de ' . View::e($name) . '" loading="lazy">'
            : '<span aria-hidden="true">' . View::e(self::initials($name)) . '</span>';

        $actionsHtml = '';
        foreach ($actions as $action) {
            $actionsHtml .= Ui::button(
                $action['label'],
                [
                    'href' => $action['href'],
                    'variant' => (string) ($action['variant'] ?? 'secondary'),
                ]
            );
        }

        return '<article class="rh-person-card">'
            . '<div class="rh-person-card-top">'
            . '<div class="rh-person-avatar">' . $avatar . '</div>'
            . '<div class="rh-person-identity">'
            . '<span class="rh-person-number">' . View::e((string) (($employee['employee_number'] ?? '') ?: 'Sans matricule')) . '</span>'
            . '<h2>' . View::e($name) . '</h2>'
            . '<p>' . View::e($contact) . '</p>'
            . '</div>'
            . '<span class="finea-status-badge ' . ($isActive ? 'finea-status-badge--ok' : 'finea-status-badge--warning') . '">'
            . ($isActive ? 'En poste' : 'Sorti') . '</span>'
            . '</div>'
            . '<dl class="rh-person-details">'
            . self::detail('Service', (string) ($employee['service_name'] ?? 'Non renseigné'))
            . self::detail('Fonction', (string) ($employee['function_name'] ?? 'Non renseignée'))
            . self::detail('Statut', (string) ($employee['status_name'] ?? 'Non renseigné'))
            . self::detail('Site', (string) (($employee['site'] ?? '') ?: 'Non renseigné'))
            . '</dl>'
            . ($actionsHtml !== '' ? '<div class="rh-person-actions">' . $actionsHtml . '</div>' : '')
            . '</article>';
    }

    private static function detail(string $label, string $value): string
    {
        return '<div><dt>' . View::e($label) . '</dt><dd>' . View::e($value) . '</dd></div>';
    }

    private static function photoUrl(string $path): ?string
    {
        if ($path === '' || !preg_match('/\.(?:jpe?g|png|webp)$/i', $path)) {
            return null;
        }

        return View::url('public/' . ltrim($path, '/'));
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'RH';
    }
}
