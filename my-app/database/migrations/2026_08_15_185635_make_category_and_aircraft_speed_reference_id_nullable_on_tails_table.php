<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both columns were required from day one (every tail was hand-entered
     * with a category and a matched aircraft type picked from a dropdown),
     * but the bulk CSV import doesn't always have a clean match for either
     * — a category spelled differently than our enum, or a Type with no
     * corresponding aircraft_speed_reference row. Rather than reject those
     * rows outright, tails:import leaves them null and reports the
     * mismatch, to be filled in by hand later. The Create/Edit forms keep
     * requiring both fields for anyone adding or editing a tail manually
     * (see TailController::validated()) — this only relaxes the DB
     * constraint so an incomplete imported row can exist in the meantime.
     */
    public function up(): void
    {
        Schema::table('tails', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
            $table->foreignId('aircraft_speed_reference_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tails', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->foreignId('aircraft_speed_reference_id')->nullable(false)->change();
        });
    }
};
