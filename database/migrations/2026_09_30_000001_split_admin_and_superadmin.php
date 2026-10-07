<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $adminId = DB::table('roles')->where('slug', 'admin')->value('id');
        if (! $adminId) {
            $operatorId = DB::table('roles')->where('slug', 'operator')->value('id');
            if ($operatorId) {
                DB::table('roles')->where('id', $operatorId)->update([
                    'slug' => 'admin',
                    'name' => 'Admin',
                    'updated_at' => $now,
                ]);
                $adminId = $operatorId;
            } else {
                $adminId = DB::table('roles')->insertGetId([
                    'name' => 'Admin',
                    'slug' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $superadminId = DB::table('roles')->where('slug', 'superadmin')->value('id');
        if (! $superadminId) {
            $superadminId = DB::table('roles')->insertGetId([
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->where('username', 'superadmin')->update([
            'role_id' => $superadminId,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $adminId = DB::table('roles')->where('slug', 'admin')->value('id');
        $superadminId = DB::table('roles')->where('slug', 'superadmin')->value('id');
        if ($adminId && $superadminId) {
            DB::table('users')->where('role_id', $superadminId)->update(['role_id' => $adminId]);
        }
        DB::table('roles')->where('slug', 'superadmin')->delete();
        if ($adminId) {
            DB::table('roles')->where('id', $adminId)->update([
                'slug' => 'operator',
                'name' => 'Operator',
            ]);
        }
    }
};
