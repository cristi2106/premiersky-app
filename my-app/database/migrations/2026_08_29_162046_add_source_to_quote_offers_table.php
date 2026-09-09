<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Distinguishes an offer parsed from an Avinode email (the only kind
     * that existed before the "Add Offer" form) from one entered by hand
     * for an operator that replied by phone or another channel — see
     * QuoteOffer::SOURCES. Defaulting the column itself to 'email' means
     * every row that already exists at migration time — all of them, so
     * far — is correctly backfilled with no separate UPDATE needed; the
     * new manual-entry path (QuoteOfferController::store()) sets 'manual'
     * explicitly, same as QuoteOfferImporter sets 'email' explicitly
     * despite it also being the column default.
     */
    public function up(): void
    {
        Schema::table('quote_offers', function (Blueprint $table) {
            $table->string('source')->default('email')->after('selected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_offers', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
