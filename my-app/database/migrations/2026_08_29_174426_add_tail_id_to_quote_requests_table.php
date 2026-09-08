<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tail_id is a manually-created quote's reference aircraft — picked
     * once (in the "Create Manual Quote" form, or later via the schedule
     * editor) purely to give quote_request_legs' flight-time calculation
     * a cruise speed to work from (see QuoteRequestController::saveLegs()).
     * It's independent of whatever Tail each competing offer eventually
     * turns out to use — see QuoteOfferController::store() — so this is
     * never read from or written to a QuoteOffer, only quote_requests
     * itself. Nullable, and null for every email-pulled quote, same as
     * every other manual-quote-only column this table has picked up.
     *
     * The backfill below moves the single schedule these columns used to
     * hold (departure/arrival_airport_id, flight_date, departure_time,
     * pax) into one leg_number=1 row in quote_request_legs — see that
     * table's own migration — before dropping them, so no existing
     * manually-created quote loses its schedule. Calculated fields are
     * left null on the backfilled row: this migration is what adds
     * tail_id in the first place, so at backfill time every one of these
     * quotes still has none set, and therefore no cruise speed to
     * calculate flight time from yet — opening the schedule editor and
     * saving (with a tail picked) fills them in.
     */
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->foreignId('tail_id')->nullable()->after('client_id')->constrained('tails')->nullOnDelete();
        });

        DB::table('quote_requests')
            ->whereNotNull('flight_date')
            ->orderBy('id')
            ->get(['id', 'departure_airport_id', 'arrival_airport_id', 'flight_date', 'departure_time', 'pax'])
            ->each(function ($quoteRequest) {
                DB::table('quote_request_legs')->insert([
                    'quote_request_id' => $quoteRequest->id,
                    'leg_number' => 1,
                    'departure_airport_id' => $quoteRequest->departure_airport_id,
                    'arrival_airport_id' => $quoteRequest->arrival_airport_id,
                    'flight_date' => $quoteRequest->flight_date,
                    'departure_time' => $quoteRequest->departure_time,
                    'pax' => $quoteRequest->pax,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('departure_airport_id');
            $table->dropConstrainedForeignId('arrival_airport_id');
            $table->dropColumn(['flight_date', 'departure_time', 'pax']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort: restores the old single-schedule columns from each
     * quote's leg_number=1 row only — a quote with more than one leg by
     * the time this rolls back loses every leg past the first, since the
     * schema being reverted to has nowhere to put them.
     */
    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->foreignId('departure_airport_id')->nullable()->after('client_id')
                ->constrained('airports')->nullOnDelete();
            $table->foreignId('arrival_airport_id')->nullable()->after('departure_airport_id')
                ->constrained('airports')->nullOnDelete();
            $table->date('flight_date')->nullable()->after('arrival_airport_id');
            $table->time('departure_time')->nullable()->after('flight_date');
            $table->unsignedInteger('pax')->nullable()->after('departure_time');
        });

        DB::table('quote_request_legs')
            ->where('leg_number', 1)
            ->get(['quote_request_id', 'departure_airport_id', 'arrival_airport_id', 'flight_date', 'departure_time', 'pax'])
            ->each(function ($leg) {
                DB::table('quote_requests')->where('id', $leg->quote_request_id)->update([
                    'departure_airport_id' => $leg->departure_airport_id,
                    'arrival_airport_id' => $leg->arrival_airport_id,
                    'flight_date' => $leg->flight_date,
                    'departure_time' => $leg->departure_time,
                    'pax' => $leg->pax,
                ]);
            });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tail_id');
        });
    }
};
