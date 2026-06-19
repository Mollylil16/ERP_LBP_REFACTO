<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

use App\Helpers\View;
use App\Models\User;

final class UserIndexPage
{
    /** @var array<string,string> */
    public readonly array $filters;
    /** @var array<int,array<string,mixed>> */
    public readonly array $users;
    /** @var array<int,array{number:int,href:string,active:bool}> */
    public readonly array $pagination;
    public readonly int $total;

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
        $items = is_array($pagination['items'] ?? null) ? $pagination['items'] : [];
        $this->total = (int) ($pagination['total'] ?? 0);

        $this->users = array_values(array_map(
            static fn(User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->fullName,
                'profile_reference' => $user->rhEmployeeId
                    ? 'Profil RH #' . (int) $user->rhEmployeeId
                    : 'Compte système',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'profile' => $user->isAdmin ? 'Administrateur' : 'Utilisateur',
                'is_admin' => $user->isAdmin,
                'status' => ucfirst($user->status),
                'status_tone' => $user->status === 'active' ? 'ok' : 'warning',
                'created_at' => self::date($user->createdAt),
                'actions' => [
                    ['label' => 'Profil', 'href' => 'admin/users/' . (int) $user->id],
                    ['label' => 'Modifier', 'href' => 'admin/users/' . (int) $user->id . '/modifier'],
                    ['label' => 'Droits', 'href' => 'admin/users/' . (int) $user->id . '/permissions'],
                ],
            ],
            array_filter($items, static fn(mixed $item): bool => $item instanceof User)
        ));

        $current = max(1, (int) ($pagination['page'] ?? 1));
        $pages = max(1, (int) ($pagination['totalPages'] ?? 1));
        $links = [];
        for ($number = 1; $number <= $pages; $number++) {
            $query = http_build_query(array_filter(
                $this->filters + ['page' => $number],
                static fn(mixed $value): bool => $value !== ''
            ));
            $links[] = [
                'number' => $number,
                'href' => View::url('admin/users?' . $query),
                'active' => $number === $current,
            ];
        }
        $this->pagination = $links;
    }

    private static function date(?string $value): string
    {
        $timestamp = $value ? strtotime($value) : false;
        return $timestamp ? date('d/m/Y', $timestamp) : '—';
    }
}
