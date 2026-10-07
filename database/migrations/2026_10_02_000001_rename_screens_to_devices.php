<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('screens', 'devices');
        Schema::table('claim_codes', function (Blueprint $table) {
            $table->renameColumn('screen_id', 'device_id');
        });
    }

    public function down(): void
    {
        Schema::table('claim_codes', function (Blueprint $table) {
            $table->renameColumn('device_id', 'screen_id');
        });
        Schema::rename('devices', 'screens');
    }
};
