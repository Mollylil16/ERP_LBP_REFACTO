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
        $id = (int) ($employee['id'] ?? 0);
        $name = trim((string) ($employee['full_name'] ?? 'Collaborateur'));
        $photoPath = trim((string) ($employee['photo_path'] ?? ''));
        $photoUrl = self::photoUrl($photoPath);
        $isActive = (int) ($employee['is_active'] ?? 0) === 1;
        $email = trim((string) ($employee['email'] ?? ''));
        $phone = trim((string) ($employee['phone'] ?? ''));
        $contact = $email ?: $phone ?: 'Contact non renseigné';

        $avatar = $photoUrl !== null
            ? '<img src="' . View::e($photoUrl) . '" alt="Photo de ' . View::e($name) . '" loading="lazy">'
            : '<span aria-hidden="true">' . View::e(self::initials($name)) . '</span>';

        $genderLabel = 'Non renseigné';
        if (($employee['gender'] ?? '') === 'male') {
            $genderLabel = 'Masculin';
        } elseif (($employee['gender'] ?? '') === 'female') {
            $genderLabel = 'Féminin';
        } elseif (($employee['gender'] ?? '') === 'other') {
            $genderLabel = 'Autre';
        }

        $activeContracts = (int) ($employee['active_contracts_count'] ?? 0);

        return '<article class="rh-person-card">'
            . '<div class="rh-person-card-header"></div>'
            . '<div class="rh-person-card-body">'
            . '<div class="rh-person-avatar">' . $avatar . '</div>'
            . '<span class="finea-status-badge ' . ($isActive ? 'finea-status-badge--ok' : 'finea-status-badge--warning') . '" style="position: absolute; top: 16px; right: 16px;">'
            . ($isActive ? 'En poste' : 'Sorti') . '</span>'
            . '<div class="rh-person-identity" style="margin-top: 8px;">'
            . '<h2>' . View::e($name) . '</h2>'
            . '<span class="rh-person-number">' . View::e((string) (($employee['employee_number'] ?? '') ?: 'Sans matricule')) . '</span>'
            . '</div>'
            . '<div class="rh-person-details" style="display: flex; flex-direction: column; gap: 8px; margin: 12px 0 6px;">'
            . '<div class="rh-person-detail-row">'
            . '<span class="rh-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span>'
            . '<div><small>FONCTION</small><strong>' . View::e((string) ($employee['function_name'] ?? 'Fonction non renseignee')) . '</strong></div>'
            . '</div>'
            . '<div class="rh-person-detail-row">'
            . '<span class="rh-detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="16"></line><line x1="15" y1="22" x2="15" y2="16"></line><line x1="9" y1="16" x2="15" y2="16"></line><path d="M9 8h.01M9 12h.01M15 8h.01M15 12h.01"></path></svg></span>'
            . '<div><small>SERVICE</small><strong>' . View::e((string) ($employee['service_name'] ?? 'LOGISTIQUE')) . '</strong></div>'
            . '</div>'
            . '</div>'
            . '<div class="rh-card-badges">'
            . '<span class="rh-badge rh-badge--gray">' . View::e((string) ($employee['status_name'] ?? 'CDD')) . '</span>'
            . '<span class="rh-badge rh-badge--blue">' . View::e((string) (($employee['site'] ?? '') ?: 'Site 0')) . '</span>'
            . '<span class="rh-badge rh-badge--green">' . View::e($genderLabel) . '</span>'
            . ($activeContracts === 0 ? '<span class="rh-badge rh-badge--yellow">Contrat a parametrer</span>' : '')
            . '</div>'
            . '<div class="rh-person-actions-grid">'
            . '<a class="rh-card-btn rh-card-btn--dark rh-grid-span-1" href="' . View::url('rh/personnel/' . $id) . '" title="Infos rapides"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Infos</a>'
            . '<a class="rh-card-btn rh-card-btn--green rh-grid-span-1" href="' . View::url('rh/personnel/' . $id) . '" title="Dossier complet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg> Dossier</a>'
            . '<a class="rh-card-btn rh-card-btn--white rh-grid-span-1" href="' . View::url('rh/personnel/' . $id . '/modifier') . '" title="Modifier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> Modifier</a>'
            . '<a class="rh-card-btn rh-card-btn--purple rh-grid-span-1" href="' . View::url('rh/personnel/' . $id . '/mutation') . '" title="Mutation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m16 3 4 4-4 4"></path><path d="M20 7H9a4 4 0 0 0-4 4v3"></path><path d="m8 21-4-4 4-4"></path><path d="M4 17h11a4 4 0 0 0 4-4v-3"></path></svg> Mutation</a>'
            . '<a class="rh-card-btn rh-card-btn--light-blue rh-grid-span-1" href="' . View::url('rh/pointage?employee_id=' . $id) . '" title="Pointage"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Pointage</a>'
            . '<a class="rh-card-btn rh-card-btn--light-red rh-grid-span-1" href="' . View::url('rh/paie?employee_id=' . $id) . '" title="Paie"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg> Paie</a>'
            . '<a class="rh-card-btn rh-card-btn--light-yellow rh-grid-span-2" href="' . View::url('rh/cycle-vie?section=contracts&employee_id=' . $id) . '" title="Contrat paie"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg> Contrat paie</a>'
            . '<a class="rh-card-btn rh-card-btn--light-orange rh-grid-span-2" href="' . View::url('rh/personnel/' . $id . '/sortie') . '" title="Déclarer sortie"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Sortie</a>'
            . '<a class="rh-card-btn rh-card-btn--light-brown rh-grid-span-2" href="' . View::url('rh/cycle-vie?section=contracts&employee_id=' . $id) . '" title="Contrat brouillon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg> Brouillon</a>'
            . ($email !== '' ? '<a class="rh-card-btn rh-card-btn--full-width" href="mailto:' . View::e($email) . '" title="E-mail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> E-mail</a>' : '')
            . '</div>'
            . '</div>'
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
