<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manually-created quote now drops its reference Tail straight in as
     * the quote's first offer (see QuoteRequestController::store()), with
     * no price yet — the user sets price/currency/commission afterwards on
     * the offer page, the same inline editor every other offer uses. Both
     * columns were NOT NULL because every prior way to create an offer (an
     * Avinode email pull, or the "Add Offer" form) always had a price in
     * hand; this new path doesn't, so they become nullable.
     */
    public function up(): void
    {
        Schema::table('quote_offers', function (Blueprint $table) {
            $table->decimal('offered_price', 12, 2)->nullable()->change();
            $table->string('offered_currency', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quote_offers', function (Blueprint $table) {
            $table->decimal('offered_price', 12, 2)->nullable(false)->change();
            $table->string('offered_currency', 3)->nullable(false)->change();
        });
    }
};
