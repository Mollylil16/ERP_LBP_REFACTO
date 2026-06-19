<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

use App\Models\User;

final class UserShowPage
{
    /** @var array<string,mixed>|null */
    public readonly ?array $employee;
    /** @var array<string,string> */
    public readonly array $details;
    /** @var array<int,array<string,string>> */
    public readonly array $grantedPermissions;
    public readonly bool $canChangeAccess;
    public readonly bool $active;

    /**
     * @param array<string,mixed>|null $employee
     * @param array<int,array<string,mixed>> $permissions
     */
    public function __construct(
        public readonly User $user,
        ?array $employee,
        array $permissions,
        int $currentUserId,
    ) {
        $this->employee = $employee;
        $this->details = [
            'Identifiant' => '#' . (int) $user->id,
            'Profil RH' => $employee
                ? (string) (($employee['employee_number'] ?? '') ?: ($employee['full_name'] ?? ''))
                : 'Compte système',
            ...($employee ? [
                'Service' => (string) ($employee['service_name'] ?? ''),
                'Fonction' => (string) ($employee['function_name'] ?? ''),
            ] : []),
            'Profil' => $user->isAdmin ? 'Administrateur' : 'Utilisateur',
            'Statut' => ucfirst($user->status),
            'Créé le' => self::date($user->createdAt, 'Non renseigné'),
            'Dernière mise à jour' => self::date($user->updatedAt, 'Aucune'),
        ];
        $this->grantedPermissions = array_values(array_filter(array_map(
            static function (array $permission): ?array {
                $labels = [];
                foreach (['view' => 'Lecture', 'create' => 'Création', 'update' => 'Modification', 'delete' => 'Suppression'] as $action => $label) {
                    if (!empty($permission['can_' . $action])) {
                        $labels[] = $label;
                    }
                }
                if ($labels === []) {
                    return null;
                }
                return [
                    'name' => (string) ($permission['module'] ?? '') . ' · ' . (string) ($permission['name'] ?? ''),
                    'rights' => implode(', ', $labels),
                ];
            },
            $permissions
        )));
        $this->canChangeAccess = $currentUserId !== (int) $user->id;
        $this->active = $user->status === 'active';
    }

    public function accessAction(): string
    {
        return 'admin/users/' . (int) $this->user->id . ($this->active ? '/desactiver' : '/activer');
    }

    private static function date(?string $value, string $fallback): string
    {
        $timestamp = $value ? strtotime($value) : false;
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $fallback;
    }
}
