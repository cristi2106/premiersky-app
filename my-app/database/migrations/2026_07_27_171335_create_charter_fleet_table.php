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
        Schema::create('charter_fleet', function (Blueprint $table) {
            $table->id();
            // The source record's id from Aviapages — used to upsert on re-sync.
            $table->unsignedInteger('aviapages_id')->unique();
            $table->string('registration_number')->nullable()->index();
            $table->string('operator_name')->nullable()->index();
            $table->string('aircraft_type_name')->nullable();
            $table->string('aircraft_type_icao', 10)->nullable();
            $table->string('aircraft_class')->nullable();
            $table->unsignedSmallInteger('year_of_production')->nullable();
            $table->unsignedSmallInteger('passengers_max')->nullable();

            // Cabin/amenity fields from aircraft_extension — mostly null per record.
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

            // Linked directly to Aviapages' CDN — never downloaded/re-hosted.
            $table->string('exterior_image_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charter_fleet');
    }
};
