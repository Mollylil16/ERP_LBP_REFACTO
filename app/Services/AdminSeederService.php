<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

class AdminSeederService
{
    public function __construct(private UserRepository $users) {}

    public function seed(): void
    {
        $admin = $this->users->findByIdentifier('admin');

        if ($admin) {
            return;
        }

        $this->users->create(new User(
            id: null,
            fullName: 'Admin',
            email: 'admin@erp-lbp.local',
            phone: null,
            passwordHash: password_hash('admin', PASSWORD_DEFAULT),
            status: 'active',
        ));
    }
}
