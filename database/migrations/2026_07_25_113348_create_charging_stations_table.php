<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('serial_number')->unique();
            $table->string('model');
            $table->string('manufacturer');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('firmware_version')->nullable();
            $table->enum('ocpp_version', ['1.6', '2.0.1']);
            $table->string('ocpp_identifier')->nullable()->unique();
            $table->unsignedSmallInteger('declared_connector_count');
            $table->decimal('power_kw', 8, 2);
            $table->enum('operational_status', [
                'available', 'occupied', 'out_of_service',
                'maintenance', 'disconnected', 'fault',
            ])->default('disconnected');
            $table->enum('administrative_status', [
                'commissioning', 'active', 'disabled', 'decommissioned',
            ])->default('commissioning');
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_stations');
    }
};
