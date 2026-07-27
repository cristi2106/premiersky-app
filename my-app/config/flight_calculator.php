<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Arrival Buffer
    |--------------------------------------------------------------------------
    |
    | Extra minutes added on top of the raw distance/speed flight time to
    | account for taxi, climb and descent — applied to every calculation in
    | App\Services\FlightCalculator.
    |
    */

    'arrival_buffer_minutes' => env('FLIGHT_CALCULATOR_ARRIVAL_BUFFER_MINUTES', 15),

];
