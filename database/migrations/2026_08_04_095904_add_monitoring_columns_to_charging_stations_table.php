<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_stations', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->index()->after('operational_status');
            $table->timestamp('last_seen_at')->nullable()->index()->after('last_heartbeat_at');
            $table->timestamp('disconnected_at')->nullable()->index()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('charging_stations', function (Blueprint $table) {
            $table->dropColumn(['last_heartbeat_at', 'last_seen_at', 'disconnected_at']);
        });
    }
};
