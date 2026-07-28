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
        Schema::create('contract_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('leg_number');
            $table->foreignId('departure_airport_id')->constrained('airports')->restrictOnDelete();
            $table->foreignId('arrival_airport_id')->constrained('airports')->restrictOnDelete();
            $table->date('flight_date');
            // Local time at the departure airport, as entered.
            $table->time('departure_time');
            $table->unsignedInteger('pax');
            // Calculated via App\Services\FlightCalculator at save time.
            $table->unsignedInteger('flight_duration_minutes');
            $table->decimal('distance_nm', 8, 1);
            // Local time at the arrival airport (not UTC) — the wall-clock
            // moment a passenger there would read off a clock.
            $table->dateTime('arrival_datetime');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_legs');
    }
};
