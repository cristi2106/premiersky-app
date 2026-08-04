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
        Schema::create('tails', function (Blueprint $table) {
            $table->id();
            $table->string('tail');
            $table->index('tail');
            $table->string('operator');
            $table->index('operator');
            $table->string('category');
            $table->foreignId('aircraft_speed_reference_id')->constrained('aircraft_speed_reference')->restrictOnDelete();
            $table->unsignedSmallInteger('year_of_make');
            $table->unsignedSmallInteger('year_of_refurbishment')->nullable();
            $table->unsignedInteger('max_pax');

            // Amenity flags — plain booleans (unlike Charter Fleet's synced,
            // nullable "unknown" tri-state) since these are always manually set.
            $table->boolean('lavatory')->default(false);
            $table->boolean('wifi')->default(false);
            $table->boolean('bed')->default(false);
            $table->boolean('entertainment_system')->default(false);
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('smoking_allowed')->default(false);

            // Storage path (public disk) for each of the two photo slots.
            $table->string('photo_1')->nullable();
            $table->string('photo_2')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tails');
    }
};
