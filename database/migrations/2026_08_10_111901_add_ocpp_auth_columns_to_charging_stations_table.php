<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_stations', function (Blueprint $table) {
            $table->string('ocpp_auth_password_hash')->nullable()->after('ocpp_identifier');
            $table->timestamp('ocpp_auth_updated_at')->nullable()->after('ocpp_auth_password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('charging_stations', function (Blueprint $table) {
            $table->dropColumn(['ocpp_auth_password_hash', 'ocpp_auth_updated_at']);
        });
    }
};
