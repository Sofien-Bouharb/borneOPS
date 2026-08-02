<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charging_station_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedSmallInteger('connector_number');
            $table->enum('standard', ['ccs', 'type2', 'chademo']);
            $table->enum('current_type', ['ac', 'dc']);
            $table->decimal('max_power_kw', 8, 2);
            $table->enum('operational_status', ['available', 'occupied', 'out_of_service', 'maintenance', 'disconnected', 'fault'])
                ->default('disconnected');
            $table->enum('administrative_status', ['enabled', 'disabled'])
                ->default('enabled');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['charging_station_id', 'connector_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
