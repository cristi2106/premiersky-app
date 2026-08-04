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
            $table->string('seating_capacity', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aircraft_speed_reference', function (Blueprint $table) {
            $table->unsignedInteger('seating_capacity')->nullable()->change();
        });
    }
};
