<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AircraftSpeedReference extends Model
{
    protected $table = 'aircraft_speed_reference';

    protected $fillable = [
        'type_name',
        'cruise_speed_knots',
        'cabin_width_m',
        'cabin_height_m',
        'cabin_length_m',
        'cabin_volume_m3',
        'baggage_capacity_m3',
        'seating_capacity',
    ];

    protected $casts = [
        'cruise_speed_knots' => 'integer',
        'cabin_width_m' => 'decimal:2',
        'cabin_height_m' => 'decimal:2',
        'cabin_length_m' => 'decimal:2',
        'cabin_volume_m3' => 'decimal:2',
        'baggage_capacity_m3' => 'decimal:2',
    ];
}
