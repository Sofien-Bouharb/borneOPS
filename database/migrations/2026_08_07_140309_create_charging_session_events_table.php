<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('charging_session_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('charging_session_id')
                ->constrained('charging_sessions')
                ->cascadeOnDelete();

            $table->string('event_type');

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->unsignedBigInteger('meter_value_wh')->nullable();

            $table->string('reason_code')->nullable();
            $table->text('reason_detail')->nullable();

            $table->string('source');

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index('charging_session_id');
            $table->index('event_type');
        });

        // Same driver-aware pattern as the charging_sessions migration:
        // SQLite (test suite) cannot ALTER TABLE ADD CONSTRAINT, so these
        // CHECK constraints are PostgreSQL-only. Form Request `in:` rules
        // enforce the same restriction on every driver.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE charging_session_events
                 ADD CONSTRAINT charging_session_events_event_type_check
                 CHECK (event_type IN (
                     'created',
                     'started',
                     'paused',
                     'resumed',
                     'completed',
                     'cancelled'
                 ))"
            );

            DB::statement(
                "ALTER TABLE charging_session_events
                 ADD CONSTRAINT charging_session_events_source_check
                 CHECK (source IN ('user', 'system', 'ocpp'))"
            );

            DB::statement(
                "ALTER TABLE charging_session_events
                 ADD CONSTRAINT charging_session_events_reason_code_check
                 CHECK (reason_code IS NULL OR reason_code IN (
                     'user_requested',
                     'operator_requested',
                     'remote_stop',
                     'vehicle_disconnected',
                     'equipment_fault',
                     'equipment_unavailable',
                     'power_loss',
                     'communication_loss',
                     'other'
                 ))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charging_session_events');
    }
};
