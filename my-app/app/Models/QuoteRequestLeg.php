<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leg of a manually-created quote's schedule — see
 * QuoteRequestController::saveLegs(). Column-for-column identical to
 * ContractLeg; kept as a separate model (rather than reusing ContractLeg
 * against a different table) since a quote_request_leg and a contract_leg
 * belong to entirely different parents and have no other relationship to
 * each other.
 */
class QuoteRequestLeg extends Model
{
    protected $fillable = [
        'quote_request_id',
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

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
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
