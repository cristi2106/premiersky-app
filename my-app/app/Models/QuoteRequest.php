<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    /**
     * pending — trip ID logged, no accepted offers parsed in yet.
     * offers_received — at least one ACCEPTED aircraft line has been
     * parsed into a quote_offers row.
     */
    public const STATUSES = ['pending', 'offers_received'];

    protected $fillable = [
        'avinode_trip_id',
        'client_id',
        'status',
        'quotation_reference',
        'reference_label',
        'tail_id',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(QuoteOffer::class);
    }

    /**
     * A manually-created quote's schedule — see
     * QuoteRequestController::saveLegs(). Always empty for an email-pulled
     * quote, which keeps deriving its schedule from parsed offer data
     * instead — see TripScheduleResolver. Ordered by leg_number so every
     * consumer (the offer page's Schedule card, the schedule editor, the
     * Quote PDF's Itinerary table) can just iterate the relation directly
     * without re-sorting.
     */
    public function legs(): HasMany
    {
        return $this->hasMany(QuoteRequestLeg::class)->orderBy('leg_number');
    }

    /**
     * The reference aircraft picked for a manually-created quote — gives
     * quote_request_legs' flight-time calculation a cruise speed (see
     * QuoteRequestController::saveLegs()). This relation isn't a link to
     * any QuoteOffer; store() does seed the quote's first offer from the
     * same Tail at creation time, but that offer carries its own tail_id
     * and is edited independently thereafter. Null for an email-pulled
     * quote.
     */
    public function tail(): BelongsTo
    {
        return $this->belongsTo(Tail::class);
    }
}
