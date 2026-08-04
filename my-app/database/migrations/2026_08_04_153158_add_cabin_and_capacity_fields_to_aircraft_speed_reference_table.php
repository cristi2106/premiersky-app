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
        Schema::table('aircraft_speed_reference', function (Blueprint $table) {
            $table->decimal('cabin_width_m', 8, 2)->nullable()->after('cruise_speed_knots');
            $table->decimal('cabin_height_m', 8, 2)->nullable()->after('cabin_width_m');
            $table->decimal('cabin_length_m', 8, 2)->nullable()->after('cabin_height_m');
            $table->decimal('cabin_volume_m3', 8, 2)->nullable()->after('cabin_length_m');
            $table->decimal('baggage_capacity_m3', 8, 2)->nullable()->after('cabin_volume_m3');
            $table->unsignedInteger('seating_capacity')->nullable()->after('baggage_capacity_m3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aircraft_speed_reference', function (Blueprint $table) {
            $table->dropColumn([
                'cabin_width_m',
                'cabin_height_m',
                'cabin_length_m',
                'cabin_volume_m3',
                'baggage_capacity_m3',
                'seating_capacity',
            ]);
        });
    }
};
