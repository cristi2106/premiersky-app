<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These columns were required from day one for a hand-built contract,
     * but the Quotes module's "Generate Contract" action — see
     * QuoteOfferController::generateContract() — creates a draft from
     * best-effort matching (aircraft type against aircraft_speed_reference,
     * airports against ICAO code) and deliberately leaves a field null
     * rather than guessing wrong when that match isn't confident. The
     * Edit form it always lands on already renders that blank state (a
     * brand-new Contract via the Create page starts every one of these
     * fields null too), so no frontend change was needed to support it.
     *
     * flight_duration_minutes, distance_nm, and arrival_datetime become
     * nullable as a direct consequence, not a separate choice: all three
     * are computed by FlightCalculator, which needs both airports *and*
     * the aircraft's cruise speed — any of which might be exactly what
     * didn't confidently match.
     *
     * Same shape as tails:import's own nullable columns — see
     * make_category_and_aircraft_speed_reference_id_nullable_on_tails_table.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('aircraft_speed_reference_id')->nullable()->change();
        });

        Schema::table('contract_legs', function (Blueprint $table) {
            $table->foreignId('departure_airport_id')->nullable()->change();
            $table->foreignId('arrival_airport_id')->nullable()->change();
            $table->unsignedInteger('flight_duration_minutes')->nullable()->change();
            $table->decimal('distance_nm', 8, 1)->nullable()->change();
            $table->dateTime('arrival_datetime')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_legs', function (Blueprint $table) {
            $table->dateTime('arrival_datetime')->nullable(false)->change();
            $table->decimal('distance_nm', 8, 1)->nullable(false)->change();
            $table->unsignedInteger('flight_duration_minutes')->nullable(false)->change();
            $table->foreignId('arrival_airport_id')->nullable(false)->change();
            $table->foreignId('departure_airport_id')->nullable(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('aircraft_speed_reference_id')->nullable(false)->change();
        });
    }
};
