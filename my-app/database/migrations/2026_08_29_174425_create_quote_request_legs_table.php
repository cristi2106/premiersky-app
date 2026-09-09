<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors contract_legs column-for-column (same types, same "local
     * time at the departure airport" meaning for departure_time, same
     * nullable calculated trio) — a manually-created quote's schedule (see
     * QuoteRequestController::store()) now lives here instead of directly
     * on quote_requests, exactly how a Contract's schedule lives in
     * contract_legs rather than on contracts itself. Only ever populated
     * for a manually-created quote (avinode_trip_id null on the parent);
     * an email-pulled quote's schedule keeps coming from the parsed offer
     * data, as it already did — see TripScheduleResolver.
     *
     * flight_duration_minutes/distance_nm/arrival_datetime are nullable
     * (not the original day-one contract_legs shape, but its current one
     * — see make_matched_fields_nullable_on_contracts_and_contract_legs_tables)
     * because computing them needs a cruise speed, which comes from
     * quote_requests.tail_id — nullable itself, and null on every
     * pre-existing manually-created quote this table's sibling migration
     * backfills into leg_number=1 rows here, since none of them had a
     * tail picked yet.
     */
    public function up(): void
    {
        Schema::create('quote_request_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('leg_number');
            $table->foreignId('departure_airport_id')->constrained('airports')->restrictOnDelete();
            $table->foreignId('arrival_airport_id')->constrained('airports')->restrictOnDelete();
            $table->date('flight_date');
            // Local time at the departure airport, as entered — same
            // convention as contract_legs.departure_time.
            $table->time('departure_time');
            $table->unsignedInteger('pax');
            $table->unsignedInteger('flight_duration_minutes')->nullable();
            $table->decimal('distance_nm', 8, 1)->nullable();
            $table->dateTime('arrival_datetime')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_request_legs');
    }
};
