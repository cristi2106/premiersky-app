<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLeg extends Model
{
    protected $fillable = [
        'contract_id',
        'leg_number',
        'departure_airport_id',
        'arrival_airport_id',
        'flight_date',
        'departure_time',
        'pax',
        'flight_duration_minutes',
        'distance_nm',
        'arrival_datetime',
    ];

    protected $casts = [
        'flight_date' => 'date',
        'distance_nm' => 'float',
        'pax' => 'integer',
        'flight_duration_minutes' => 'integer',
        // Wall-clock local time at the arrival airport, not UTC — see the
        // migration. Cast purely for convenient ->format() calls.
        'arrival_datetime' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function departureAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'departure_airport_id');
    }

    public function arrivalAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'arrival_airport_id');
    }
}
