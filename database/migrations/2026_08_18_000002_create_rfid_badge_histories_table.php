<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_badge_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfid_badge_id')
                ->constrained('rfid_badges')
                ->cascadeOnDelete();
            $table->string('event_type');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('source')->default('user');
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('rfid_badge_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE rfid_badge_histories ADD CONSTRAINT rfid_badge_histories_event_type_check "
                . "CHECK (event_type IN ('created', 'reassigned', 'activated', 'blocked', 'expiration_changed', 'metadata_updated'))"
            );
            DB::statement(
                "ALTER TABLE rfid_badge_histories ADD CONSTRAINT rfid_badge_histories_source_check "
                . "CHECK (source IN ('user', 'system', 'ocpp'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_badge_histories');
    }
};
