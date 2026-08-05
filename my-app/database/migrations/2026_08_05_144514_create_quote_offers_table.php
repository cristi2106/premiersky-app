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
        Schema::create('quote_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();

            // Avinode's own reference for this specific offer. Nullable —
            // not every accepted line has a detail block to pull it from
            // (see AvinodeQuoteEmailParser's fixture notes) — so it's
            // indexed for the upsert lookup but not DB-unique; re-pull
            // dedup for the ones without an ID falls back to matching on
            // the offer's other identifying fields (see QuoteController).
            $table->string('avinode_request_id')->nullable()->index();

            $table->string('operator_name');
            $table->string('aircraft_type');
            $table->string('aircraft_registration')->nullable();
            $table->decimal('offered_price', 12, 2);
            $table->string('offered_currency', 3);

            // String, not year/integer — sometimes a single year ("2002"),
            // sometimes a range ("2011–2020").
            $table->string('year_of_make')->nullable();
            $table->unsignedInteger('max_pax')->nullable();
            $table->unsignedInteger('distance_nm')->nullable();
            $table->string('flight_duration')->nullable();

            // Full original body, kept for reference/audit and so the
            // itinerary (departure/arrival, local time only) can be
            // re-derived for display without a dedicated set of columns.
            $table->text('raw_email_body');

            $table->foreignId('tail_id')->nullable()->constrained()->nullOnDelete();

            // Plain string rather than a native DB enum, matching
            // quote_requests.status and contracts.status — see
            // QuoteOffer::COMMISSION_TYPES.
            $table->string('commission_type')->nullable();
            $table->decimal('commission_value', 12, 2)->nullable();
            $table->decimal('final_price', 12, 2)->nullable();

            $table->boolean('selected')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_offers');
    }
};
