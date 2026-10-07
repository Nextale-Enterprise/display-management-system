<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        $superadminRoleId = Role::query()->where('slug', 'superadmin')->value('id');

        User::query()->updateOrCreate(
            ['email' => 'admin@signage.test'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => 'asd123',
                'role_id' => $adminRoleId,
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Superadmin',
                'email' => 'superadmin@signage.test',
                'password' => 'asd123',
                'role_id' => $superadminRoleId,
            ],
        );
    }
}
