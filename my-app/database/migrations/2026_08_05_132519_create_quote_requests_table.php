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
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('avinode_trip_id')->unique();
            // Nullable until the parsing step (a later piece of this module)
            // can resolve an email thread back to a known client.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            // Plain string rather than a native DB enum, matching how
            // contracts.status is modelled — see QuoteRequest::STATUSES.
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
