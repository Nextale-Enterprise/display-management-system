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

        $operatorRoleId = Role::query()->where('slug', 'operator')->value('id');

        User::query()->updateOrCreate(
            ['email' => 'operator@signage.test'],
            [
                'name' => 'Operator',
                'username' => 'operator',
                'password' => 'password',
                'role_id' => $operatorRoleId,
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Superadmin',
                'email' => 'superadmin@signage.test',
                'password' => 'asd123',
                'role_id' => $operatorRoleId,
            ],
        );
    }
}
