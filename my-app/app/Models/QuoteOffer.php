<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteOffer extends Model
{
    public const COMMISSION_TYPES = ['percentage', 'fixed'];

    /**
     * 'email' — parsed out of an Avinode email by QuoteOfferImporter.
     * 'manual' — entered by hand via QuoteOfferController::store(), for an
     * operator that responded by phone or another channel. See the
     * migration that added this column for the backfill/default split.
     */
    public const SOURCES = ['email', 'manual'];

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
        'source',
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
     * A new (unsaved) manually-sourced offer with every "about the
     * aircraft" field copied from a Tail's own record — operator, type,
     * registration, year, seats — so a hand-built offer can never
     * disagree with what the Tails module has on file. Price, currency
     * and commission are deliberately left for the caller: the "Add
     * Offer" form (QuoteOfferController::store()) fills a price in up
     * front, while a manual quote's auto-created reference offer
     * (QuoteRequestController::store()) leaves them null for the user to
     * enter on the offer page. raw_email_body is '' and source 'manual'
     * either way — there's no source email for either path.
     *
     * Reads $tail->aircraftSpeedReference; eager-load it
     * (with('aircraftSpeedReference:id,type_name')) at the call site.
     */
    public static function forTail(Tail $tail): self
    {
        return new self([
            'operator_name' => $tail->operator,
            'aircraft_type' => $tail->aircraftSpeedReference?->type_name ?? $tail->category ?? 'Unknown type',
            'aircraft_registration' => $tail->tail,
            'year_of_make' => $tail->year_of_make !== null ? (string) $tail->year_of_make : null,
            'max_pax' => $tail->max_pax,
            'raw_email_body' => '',
            'tail_id' => $tail->id,
            'source' => 'manual',
        ]);
    }

    /**
     * offered_price plus commission, per the chosen commission type.
     * Returns null until both a type and a value are set — or if there's
     * no price yet at all (a manual quote's auto-created reference offer
     * starts with none; see forTail()). The PDF step (later) shows this
     * as a single combined total — never labelled "commission" — so this
     * is the one place the formula should live.
     */
    public function calculateFinalPrice(): ?string
    {
        if ($this->offered_price === null || $this->commission_type === null || $this->commission_value === null) {
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
