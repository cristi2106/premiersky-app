<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Charter Fleet Directory is retired — Tails is now the primary internal
// aircraft database. See 2026_07_27_171335_create_charter_fleet_table.php
// for the schema this reverses.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('charter_fleet');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('charter_fleet', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aviapages_id')->unique();
            $table->string('registration_number')->nullable()->index();
            $table->string('operator_name')->nullable()->index();
            $table->string('aircraft_type_name')->nullable();
            $table->string('aircraft_type_icao', 10)->nullable();
            $table->string('aircraft_class')->nullable();
            $table->unsignedSmallInteger('year_of_production')->nullable();
            $table->unsignedSmallInteger('passengers_max')->nullable();

            $table->boolean('lavatory')->nullable();
            $table->unsignedSmallInteger('beds')->nullable();
            $table->boolean('wireless_internet')->nullable();
            $table->boolean('entertainment_system')->nullable();
            $table->boolean('pets_allowed')->nullable();
            $table->boolean('smoking')->nullable();
            $table->decimal('cabin_height', 5, 2)->nullable();
            $table->decimal('cabin_length', 5, 2)->nullable();
            $table->decimal('cabin_width', 5, 2)->nullable();
            $table->decimal('luggage_volume', 6, 2)->nullable();
            $table->unsignedSmallInteger('sleeping_places')->nullable();
            $table->unsignedSmallInteger('divan_seats')->nullable();
            $table->boolean('hot_meal')->nullable();
            $table->boolean('medical_ramp')->nullable();
            $table->unsignedSmallInteger('refurbishment')->nullable();
            $table->text('description')->nullable();

            $table->string('exterior_image_url')->nullable();

            $table->timestamps();
        });
    }
};
