<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Supports creating a Quote entirely by hand — see
     * QuoteRequestController::store() — for a trip that never came
     * through Avinode/email at all.
     *
     * avinode_trip_id becoming nullable is the load-bearing change: every
     * other piece of this feature follows from a QuoteRequest being
     * allowed to exist without one. The column's existing unique index is
     * left as-is rather than dropped — SQLite (like every other major
     * database) treats NULL as distinct from itself in a unique index, so
     * any number of manual quotes can coexist with NULL there without a
     * collision; only two rows both claiming the same real trip ID would
     * still violate it, which is exactly the case that should stay
     * disallowed.
     *
     * reference_label is a manual quote's stand-in for the trip ID
     * everywhere the UI would otherwise show one (search history, in
     * particular) — user-typed, or auto-generated if left blank, see
     * QuoteRequestController::generateReferenceLabel().
     *
     * departure/arrival_airport_id, flight_date, departure_time and pax
     * mirror contract_legs' own columns (same types, same "local time at
     * the departure airport" meaning for departure_time) — a manual quote
     * has nothing else to derive a schedule from: unlike an email-pulled
     * quote, there's no raw message for AvinodeQuoteEmailParser to pull an
     * itinerary out of, and a manual offer's own raw_email_body is always
     * empty (see QuoteOfferController::store()). See
     * QuoteController::manualSchedule().
     */
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('avinode_trip_id')->nullable()->change();
            $table->string('reference_label')->nullable()->after('avinode_trip_id');
            $table->foreignId('departure_airport_id')->nullable()->after('client_id')
                ->constrained('airports')->nullOnDelete();
            $table->foreignId('arrival_airport_id')->nullable()->after('departure_airport_id')
                ->constrained('airports')->nullOnDelete();
            $table->date('flight_date')->nullable()->after('arrival_airport_id');
            // Local time at the departure airport, as entered — same
            // convention as contract_legs.departure_time.
            $table->time('departure_time')->nullable()->after('flight_date');
            $table->unsignedInteger('pax')->nullable()->after('departure_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('departure_airport_id');
            $table->dropConstrainedForeignId('arrival_airport_id');
            $table->dropColumn(['reference_label', 'flight_date', 'departure_time', 'pax']);
            $table->string('avinode_trip_id')->nullable(false)->change();
        });
    }
};
