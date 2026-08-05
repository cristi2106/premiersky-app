<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteOffer extends Model
{
    public const COMMISSION_TYPES = ['percentage', 'fixed'];

    protected $fillable = [
        'quote_request_id',
        'avinode_request_id',
        'operator_name',
        'aircraft_type',
        'aircraft_registration',
        'offered_price',
        'offered_currency',
        'year_of_make',
        'max_pax',
        'distance_nm',
        'flight_duration',
        'raw_email_body',
        'tail_id',
        'commission_type',
        'commission_value',
        'final_price',
        'selected',
    ];

    protected $casts = [
        'offered_price' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'final_price' => 'decimal:2',
        'selected' => 'boolean',
    ];

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function tail(): BelongsTo
    {
        return $this->belongsTo(Tail::class);
    }

    /**
     * offered_price plus commission, per the chosen commission type.
     * Returns null until both a type and a value are set. The PDF step
     * (later) shows this as a single combined total — never labelled
     * "commission" — so this is the one place the formula should live.
     */
    public function calculateFinalPrice(): ?string
    {
        if ($this->commission_type === null || $this->commission_value === null) {
            return null;
        }

        $price = (float) $this->offered_price;
        $value = (float) $this->commission_value;

        $final = $this->commission_type === 'percentage'
            ? $price * (1 + $value / 100)
            : $price + $value;

        return number_format($final, 2, '.', '');
    }
}
