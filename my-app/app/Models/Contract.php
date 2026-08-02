<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'client_id',
        'aircraft_speed_reference_id',
        'reference_number',
        'price',
        'currency',
        'vat_percentage',
        'special_information',
        'cancellation_policy',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(AircraftSpeedReference::class, 'aircraft_speed_reference_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(ContractLeg::class)->orderBy('leg_number');
    }
}
