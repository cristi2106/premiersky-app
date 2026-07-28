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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_speed_reference_id')->constrained('aircraft_speed_reference')->restrictOnDelete();
            // Auto-generated on creation, e.g. "07-2026/03" — see
            // App\Http\Controllers\ContractController::nextReferenceNumber().
            $table->string('reference_number')->unique();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->text('special_information')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
