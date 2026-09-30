<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tail extends Model
{
    public const CATEGORIES = [
        'Piston',
        'Turbo Prop',
        'Helicopter',
        'Entry Level Jet',
        'Light Jet',
        'Super Light Jet',
        'Midsize Jet',
        'Super Midsize Jet',
        'Heavy Jet',
        'Ultra Long Range',
        'VIP Airliner',
        'Airliner',
    ];

    protected $fillable = [
        'tail',
        'operator',
        'category',
        'aircraft_speed_reference_id',
        'year_of_make',
        'year_of_refurbishment',
        'max_pax',
        'lavatory',
        'wifi',
        'bed',
        'entertainment_system',
        'pets_allowed',
        'smoking_allowed',
        'photo_1',
        'photo_2',
    ];

    // Named "photo1_url" rather than "photo_1_url" deliberately: Eloquent
    // derives this key from the accessor method two different ways
    // depending on the code path (Str::camel(key) for property access vs.
    // Str::snake(method) when serializing via toArray()/JSON), and those
    // two derivations disagree once a digit sits next to an underscore
    // boundary — "photo_1_url" round-trips to "photo1_url", not itself.
    // Keeping the key digit-adjacent avoids the mismatch entirely.
    protected $appends = [
        'photo1_url',
        'photo2_url',
    ];

    protected $casts = [
        'year_of_make' => 'integer',
        'year_of_refurbishment' => 'integer',
        'max_pax' => 'integer',
        'lavatory' => 'boolean',
        'wifi' => 'boolean',
        'bed' => 'boolean',
        'entertainment_system' => 'boolean',
        'pets_allowed' => 'boolean',
        'smoking_allowed' => 'boolean',
    ];

    public function aircraftSpeedReference(): BelongsTo
    {
        return $this->belongsTo(AircraftSpeedReference::class);
    }

    // Deliberately not Storage::disk('public')->url() — that resolves
    // against the 'public' disk's configured 'url', which is hardcoded to
    // APP_URL (config/filesystems.php). APP_URL rarely matches the host
    // actually serving the app in dev (a forwarded port, a container
    // hostname, etc.), so an absolute URL built from it 404s in the
    // browser. A root-relative path always resolves against whatever
    // origin actually loaded the page — same reasoning as the
    // window.location.origin override for Ziggy in resources/js/app.js.
    protected function photo1Url(): Attribute
    {
        return Attribute::get(
            fn () => $this->photo_1 ? '/storage/'.$this->photo_1 : null
        );
    }

    protected function photo2Url(): Attribute
    {
        return Attribute::get(
            fn () => $this->photo_2 ? '/storage/'.$this->photo_2 : null
        );
    }
}
