<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('identifier_hash', 64)->unique();
            $table->string('identifier_hint')->nullable();
            $table->string('label')->nullable();
            $table->string('administrative_status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('administrative_status');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE rfid_badges ADD CONSTRAINT rfid_badges_administrative_status_check "
                . "CHECK (administrative_status IN ('pending', 'active', 'blocked'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_badges');
    }
};
