<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

/**
 * Ce que l'écran des rôles a besoin de savoir : le catalogue et les anomalies.
 */
final class RolesPage
{
    /** @var array<int, array<string, mixed>> */
    public readonly array $roles;

    /** @var array<int, array<string, mixed>> */
    public readonly array $sansRole;

    /** @var array<int, array<string, mixed>> */
    public readonly array $dormants;

    /** @var array<int, array<string, mixed>> */
    public readonly array $administrateurs;

    /**
     * @param array<int, array<string, mixed>> $roles
     * @param array<int, array<string, mixed>> $sansRole
     * @param array<int, array<string, mixed>> $dormants
     * @param array<int, array<string, mixed>> $administrateurs
     */
    public function __construct(array $roles, array $sansRole, array $dormants, array $administrateurs)
    {
        $this->roles = $roles;
        $this->sansRole = $sansRole;
        $this->dormants = $dormants;
        $this->administrateurs = $administrateurs;
    }
}
