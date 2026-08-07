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
        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('charging_station_id')
                ->constrained('charging_stations')
                ->restrictOnDelete();

            $table->foreignId('connector_id')
                ->constrained('connectors')
                ->restrictOnDelete();

            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')->default('pending');

            $table->string('reason_code')->nullable();
            $table->text('reason_detail')->nullable();

            $table->unsignedBigInteger('meter_start_wh')->nullable();
            $table->unsignedBigInteger('latest_meter_wh')->nullable();
            $table->unsignedBigInteger('meter_stop_wh')->nullable();

            $table->decimal('total_price', 10, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            $table->string('ocpp_transaction_id')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('total_paused_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('charging_station_id');
            $table->index('connector_id');
        });

        // Decision #2 (frozen roadmap v1.1): only one OPEN session per connector,
        // where "open" means status is pending, active, or paused. This is a
        // partial unique index — it only applies to rows matching the WHERE
        // clause, so a connector can have unlimited completed/cancelled
        // sessions in its history, just never more than one open at a time.
        // This is the database-level backstop; ChargingSessionService also
        // checks this explicitly and returns a French 409 before ever
        // reaching the database, so this index should normally never fire —
        // it exists as a last-resort safety net against race conditions.
        DB::statement(
            "CREATE UNIQUE INDEX charging_sessions_one_open_per_connector
             ON charging_sessions (connector_id)
             WHERE status IN ('pending', 'active', 'paused')"
        );

        // Decision #13: ocpp_transaction_id must be unique per station, but
        // the same transaction id string is allowed to repeat across
        // different stations, and multiple NULLs are always fine (a session
        // has no ocpp_transaction_id until a real OCPP server is wired in,
        // per the post-Module-5 integration step).
        DB::statement(
            "CREATE UNIQUE INDEX charging_sessions_ocpp_txn_unique_per_station
             ON charging_sessions (charging_station_id, ocpp_transaction_id)
             WHERE ocpp_transaction_id IS NOT NULL"
        );

        // Decision #11: reason_code is a constrained set of terminal reason
        // codes. SQLite (used by the test suite) does not support adding a
        // CHECK constraint via ALTER TABLE, so this is PostgreSQL-only —
        // exactly the same driver-aware pattern already documented for the
        // ILIKE gotcha in Module 3. Form Request `in:` validation covers the
        // same rule on every driver, including SQLite during tests.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE charging_sessions
                 ADD CONSTRAINT charging_sessions_reason_code_check
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
        Schema::dropIfExists('charging_sessions');
    }
};
