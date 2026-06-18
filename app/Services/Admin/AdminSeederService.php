<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Repositories\Admin\UserRepository;

class AdminSeederService
{
    public function __construct(private UserRepository $users) {}

    public function seed(): void
    {
        $admin = $this->users->findByIdentifier('admin');

        if ($admin) {
            if (!$admin->isAdmin || $admin->status !== 'active') {
                $this->users->promoteToAdmin((int) $admin->id);
            }
            return;
        }

        $this->users->create(new User(
            id: null,
            fullName: 'Admin',
            email: 'admin@erp-lbp.local',
            phone: null,
            passwordHash: password_hash('admin', PASSWORD_DEFAULT),
            status: 'active',
            isAdmin: true,
        ));
    }
}
