<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->updateOrCreate(['slug' => 'superadmin'], ['name' => 'Superadmin']);
        Role::query()->updateOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::query()->updateOrCreate(['slug' => 'merchant'], ['name' => 'Merchant']);
    }
}
