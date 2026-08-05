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
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(QuoteOffer::class);
    }
}
