<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('role');
            $table->string('cms_id');
            $table->unsignedBigInteger('local_device_id')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['cms_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_codes');
    }
};
