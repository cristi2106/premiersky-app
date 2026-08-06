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
        Schema::table('quote_requests', function (Blueprint $table) {
            // Our own sequence ("08-2026/Q01"), assigned once — the first
            // time a quotation PDF is generated for this request — and
            // reused on every re-download after that. Deliberately not the
            // Avinode trip ID: see QuoteRequestController::pdf().
            $table->string('quotation_reference')->nullable()->unique()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('quotation_reference');
        });
    }
};
