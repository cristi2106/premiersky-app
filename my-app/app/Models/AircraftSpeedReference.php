<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AircraftSpeedReference extends Model
{
    protected $table = 'aircraft_speed_reference';

    protected $fillable = [
        'type_name',
        'cruise_speed_knots',
    ];

    protected $casts = [
        'cruise_speed_knots' => 'integer',
    ];
}
