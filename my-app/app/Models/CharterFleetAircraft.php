<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharterFleetAircraft extends Model
{
    protected $table = 'charter_fleet';

    protected $fillable = [
        'aviapages_id',
        'registration_number',
        'operator_name',
        'aircraft_type_name',
        'aircraft_type_icao',
        'aircraft_class',
        'year_of_production',
        'passengers_max',
        'lavatory',
        'beds',
        'wireless_internet',
        'entertainment_system',
        'pets_allowed',
        'smoking',
        'cabin_height',
        'cabin_length',
        'cabin_width',
        'luggage_volume',
        'sleeping_places',
        'divan_seats',
        'hot_meal',
        'medical_ramp',
        'refurbishment',
        'description',
        'exterior_image_url',
    ];

    protected $casts = [
        'lavatory' => 'boolean',
        'wireless_internet' => 'boolean',
        'entertainment_system' => 'boolean',
        'pets_allowed' => 'boolean',
        'smoking' => 'boolean',
        'hot_meal' => 'boolean',
        'medical_ramp' => 'boolean',
        'cabin_height' => 'float',
        'cabin_length' => 'float',
        'cabin_width' => 'float',
        'luggage_volume' => 'float',
    ];
}
