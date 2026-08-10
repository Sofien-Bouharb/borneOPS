<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->unsignedInteger('ocpp_evse_id')->nullable()->after('connector_number');
            $table->unsignedInteger('ocpp_connector_id')->nullable()->after('ocpp_evse_id');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropColumn(['ocpp_evse_id', 'ocpp_connector_id']);
        });
    }
};
