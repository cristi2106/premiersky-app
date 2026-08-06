<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\AircraftSpeedReference;
use App\Models\QuoteOffer;
use App\Models\QuoteRequest;
use App\Services\AvinodeQuoteEmailParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuoteRequestController extends Controller
{
    /**
     * Mirrors resources/js/tailAmenities.js's key/label pairs — kept in
     * sync by hand since one lives in JS (the Tails module's own UI) and
     * this one renders server-side into the PDF.
     */
    private const AMENITIES = [
        ['lavatory', 'Lavatory'],
        ['wifi', 'WiFi'],
        ['bed', 'Bed / flat sleeping space'],
        ['entertainment_system', 'TV / entertainment system'],
        ['pets_allowed', 'Pets allowed'],
        ['smoking_allowed', 'Smoking allowed'],
    ];

    /**
     * Every Tail photo is displayed at exactly this size (CSS px, matching
     * the PDF's other px-based measurements — see table.photo-row in
     * quotes/pdf.blade.php, which this needs to stay sized for) — dompdf
     * has no `object-fit` support (checked: not referenced anywhere in
     * vendor/dompdf/dompdf), so getting uniform, uncropped-looking photos
     * out of wildly different upload dimensions means actually cropping
     * them ourselves before they're embedded — see croppedPhotoDataUri().
     * Sized to sit two-up, side by side, in the photos column rather than
     * stacked — same 200:113 aspect ratio, scaled to the pair's combined
     * width against td.offer-photos-col's share of the card (56% → 48%
     * went to the text column to make room for these growing).
     */
    private const PHOTO_WIDTH = 177;

    private const PHOTO_HEIGHT = 100;

    /**
     * Lazy cache for allAircraftSpeedReferences() — see its own doc
     * comment for why the whole table is loaded once per request rather
     * than queried per offer.
     */
    private ?Collection $aircraftSpeedReferences = null;

    /**
     * Attaches (or clears) the client this quote request is for. Plain
     * JSON, matching QuoteOfferController's update — called from the
     * Quotes page's client picker, patches just this one piece of state.
     */
    public function update(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $quoteRequest->update($data);
        $quoteRequest->load('client');

        return response()->json([
            'quoteRequest' => [
                'id' => $quoteRequest->id,
                'client' => $quoteRequest->client ? [
                    'id' => $quoteRequest->client->id,
                    'company_name' => $quoteRequest->client->company_name,
                ] : null,
            ],
        ]);
    }

    /**
     * Streams the client-facing quotation PDF for whichever offers are
     * currently marked `selected`.
     *
     * This is the one place in the app that's actively responsible for
     * *not* leaking operator identity: the query below never selects
     * operator_name, aircraft_registration, or avinode_request_id onto
     * anything handed to the view, and the view itself never receives the
     * QuoteOffer models directly — only the pre-shaped arrays built here.
     */
    public function pdf(QuoteRequest $quoteRequest, AvinodeQuoteEmailParser $parser): SymfonyResponse
    {
        $quoteRequest->load('client');

        abort_if($quoteRequest->client === null, 400, 'Select a client before generating a quotation PDF.');

        $selectedOffers = $quoteRequest->offers()
            ->where('selected', true)
            ->with('tail.aircraftSpeedReference')
            ->orderBy('offered_price')
            ->get();

        abort_if($selectedOffers->isEmpty(), 400, 'Select at least one offer before generating a quotation PDF.');

        if ($quoteRequest->quotation_reference === null) {
            $quoteRequest->update(['quotation_reference' => $this->nextQuotationReference()]);
        }

        $itinerary = $this->buildItinerary($selectedOffers->first(), $parser);
        $offers = $selectedOffers->map(fn ($offer) => $this->buildOfferForPdf($offer))->all();

        $pdf = Pdf::loadView('quotes.pdf', [
            'quoteRequest' => $quoteRequest,
            'client' => $quoteRequest->client,
            'itinerary' => $itinerary,
            'offers' => $offers,
        ]);

        $filenameSafeReference = str_replace('/', '-', $quoteRequest->quotation_reference);

        return $pdf->stream("quotation-{$filenameSafeReference}.pdf");
    }

    /**
     * The single shared schedule shown once at the top of the PDF, taken
     * from whichever selected offer sorts first (cheapest) — see the
     * class doc on QuoteController for why this is deliberately not
     * repeated per offer option.
     *
     * @return array<string, mixed>
     */
    private function buildItinerary(QuoteOffer $offer, AvinodeQuoteEmailParser $parser): array
    {
        $parsed = $parser->findOfferItinerary(
            $offer->raw_email_body,
            $offer->aircraft_type,
            $offer->aircraft_registration,
            (float) $offer->offered_price
        ) ?? [];

        return [
            'date' => $parsed['departure_date'] ?? null,
            'departure_time' => $parsed['departure_time'] ?? null,
            'arrival_time' => $parsed['arrival_time'] ?? null,
            'pax' => $parsed['pax'] ?? null,
            'departure' => $this->resolveAirportLabel($parsed['departure_icao'] ?? null, $parsed['departure_airport'] ?? null),
            'arrival' => $this->resolveAirportLabel($parsed['arrival_icao'] ?? null, $parsed['arrival_airport'] ?? null),
        ];
    }

    /**
     * "<Full airport name> (<IATA or ICAO>)" — identical shape to how
     * Contracts label airports — when the ICAO code matches a row in our
     * own Airports table; otherwise falls back to whatever descriptive
     * text the parser already extracted from the email, so a route we
     * don't have on file yet still shows something sensible.
     */
    private function resolveAirportLabel(?string $icao, ?string $fallback): ?string
    {
        $airport = $icao !== null ? Airport::where('icao_code', $icao)->first() : null;

        if ($airport !== null) {
            return $airport->name.' ('.($airport->iata_code ?: $airport->icao_code).')';
        }

        return $fallback;
    }

    /**
     * Operators occasionally list their floating fleet in Avinode under
     * their own brand instead of the manufacturer — Platoon Aviation's
     * quotes read "PLATOON PC-24" for what is actually a Pilatus PC-24
     * (see tests/fixtures/avinode-emails/sample-5-platoon-entity-in-name.txt
     * — that's the literal text in the source email, so the parser
     * extracting it verbatim isn't a bug to fix there). Corrected once,
     * here, rather than at parse time, so the raw offer keeps recording
     * exactly what the operator sent; only the client-facing PDF (and the
     * aircraft_speed_reference lookup, which needs the real manufacturer
     * name to match at all) ever see the corrected form. Keyed on the
     * type string's first word, lowercased.
     */
    private const OPERATOR_BRAND_ALIASES = [
        'platoon' => 'Pilatus',
    ];

    /**
     * Shapes one selected offer into exactly what the PDF is allowed to
     * show: aircraft type and a single combined total price always;
     * photos/amenities always require a matched Tail (that's the only
     * place those live) — but cabin size/seats have a second source: when
     * there's no Tail match, we fall back to looking the offer's aircraft
     * type up in aircraft_speed_reference directly (the same reference
     * data the Flight Calculator and Contracts use), so an offer whose
     * registration didn't match anything can still show cabin details as
     * long as its type is on file. See matchAircraftSpeedReference().
     *
     * @return array<string, mixed>
     */
    private function buildOfferForPdf(QuoteOffer $offer): array
    {
        $tail = $offer->tail;
        $aircraftType = $this->correctOperatorBrandedAircraftType($offer->aircraft_type);

        $cabin = null;

        if ($tail === null) {
            $reference = $this->matchAircraftSpeedReference($aircraftType);

            if ($reference !== null) {
                $cabin = [
                    'seats' => $reference->seating_capacity,
                    'cabin_summary' => $this->buildCabinSummary($reference),
                ];
            }
        }

        return [
            // offered_price + commission, combined into one number — see
            // QuoteOffer::calculateFinalPrice(). Falls back to the bare
            // offered price if no commission has been set yet, so the
            // client document is never left with a blank total.
            'total_price' => (float) ($offer->final_price ?? $offer->offered_price),
            'currency' => $offer->offered_currency,
            'aircraft_type' => $aircraftType,
            'tail' => $tail ? [
                'category' => $tail->category,
                'photos' => array_values(array_filter([
                    $this->croppedPhotoDataUri($tail->photo_1),
                    $this->croppedPhotoDataUri($tail->photo_2),
                ])),
                'amenities' => collect(self::AMENITIES)
                    ->filter(fn ($pair) => (bool) $tail->{$pair[0]})
                    ->map(fn ($pair) => $pair[1])
                    ->values()
                    ->all(),
                'seats' => $tail->aircraftSpeedReference?->seating_capacity,
                'cabin_summary' => $this->buildCabinSummary($tail->aircraftSpeedReference),
            ] : null,
            'cabin' => $cabin,
        ];
    }

    /**
     * Swaps a known operator-brand first word for the real manufacturer —
     * "PLATOON PC-24" becomes "Pilatus PC-24" — leaving everything else
     * about the string untouched. A no-op for every type that doesn't
     * start with one of OPERATOR_BRAND_ALIASES' keys, which is the vast
     * majority. See OPERATOR_BRAND_ALIASES' own doc comment for why this
     * exists.
     */
    private function correctOperatorBrandedAircraftType(string $aircraftType): string
    {
        $firstSpace = strpos($aircraftType, ' ');
        $firstWord = $firstSpace === false ? $aircraftType : substr($aircraftType, 0, $firstSpace);
        $manufacturer = self::OPERATOR_BRAND_ALIASES[mb_strtolower($firstWord)] ?? null;

        if ($manufacturer === null) {
            return $aircraftType;
        }

        return $manufacturer.substr($aircraftType, strlen($firstWord));
    }

    /**
     * Looks up aircraft_speed_reference by aircraft type name for offers
     * with no Tail match.
     *
     * An exact (even case-insensitive) match almost never fires in
     * practice: offer aircraft_type comes free-text from a parsed Avinode
     * email and is consistently just the model — "Challenger 604",
     * "Legacy 650", "Phenom 300E" — while aircraft_speed_reference.type_name
     * (imported from the CSV) always carries the manufacturer prefix —
     * "Bombardier Challenger 604", "Embraer Legacy 650", "Embraer Phenom
     * 300E" — sometimes with extra spacing/hyphen differences on top of
     * that ("Gulfstream G-200" vs "Gulfstream G200", "Falcon 900 LX" vs
     * "Falcon 900LX"). So instead: strip whitespace/hyphens from both
     * sides and check whether the reference name *ends with* the offer's
     * (normalized) type — the manufacturer prefix, if any, just falls
     * before the match. Matching on a full trailing token this way
     * (rather than a loose substring search) avoids "Legacy 650" wrongly
     * matching "Legacy 650E" and similar near-miss model names.
     *
     * If that still turns up more than one row (or none), that's treated
     * as no match — showing no cabin line beats guessing wrong — which is
     * also what correctly happens for a type that isn't in the table at
     * all under any manufacturer, e.g. a typo'd "PLATOON PC-24".
     */
    private function matchAircraftSpeedReference(string $aircraftType): ?AircraftSpeedReference
    {
        $needle = $this->normalizeAircraftTypeName($aircraftType);

        if ($needle === '') {
            return null;
        }

        $matches = $this->allAircraftSpeedReferences()->filter(
            fn (AircraftSpeedReference $reference) => str_ends_with($this->normalizeAircraftTypeName($reference->type_name), $needle)
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Lowercased with all whitespace and hyphens removed, so "Falcon 900
     * LX", "Falcon 900-LX", and "Falcon900LX" all compare equal — see
     * matchAircraftSpeedReference().
     */
    private function normalizeAircraftTypeName(string $value): string
    {
        return mb_strtolower(preg_replace('/[\s\-]+/', '', trim($value)));
    }

    /**
     * The full aircraft_speed_reference table, fetched once per PDF
     * request and reused across every offer's lookup — there's no
     * indexed way to run matchAircraftSpeedReference()'s normalized
     * suffix comparison in SQL (SQLite has no built-in regex function),
     * and at ~150 rows loading it once is cheaper than N queries anyway.
     */
    private function allAircraftSpeedReferences(): Collection
    {
        return $this->aircraftSpeedReferences ??= AircraftSpeedReference::all();
    }

    /**
     * "2.41m W × 1.85m H × 8.31m L · Baggage: 3.85m³" — one line combining
     * whichever of width/height/length/baggage are actually set
     * (cabin_volume_m3 is deliberately left out: it's derivable from the
     * three dimensions and the line is already dense enough). No leading
     * "Cabin:" — the PDF puts that as its own label above this line (see
     * .detail-label in quotes/pdf.blade.php) rather than inline here.
     * Seats stays a separate field entirely — see buildOfferForPdf().
     */
    private function buildCabinSummary(?AircraftSpeedReference $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $dimension = fn (?string $value, string $axis) => $value !== null
            ? number_format((float) $value, 2).'m '.$axis
            : null;

        $dimensions = array_filter([
            $dimension($reference->cabin_width_m, 'W'),
            $dimension($reference->cabin_height_m, 'H'),
            $dimension($reference->cabin_length_m, 'L'),
        ]);

        $parts = [];

        if ($dimensions !== []) {
            $parts[] = implode(' × ', $dimensions);
        }

        if ($reference->baggage_capacity_m3 !== null) {
            $parts[] = 'Baggage: '.number_format((float) $reference->baggage_capacity_m3, 2).'m³';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Embeds a Tail photo as a base64 data URI, center-cropped and
     * resampled to a fixed PHOTO_WIDTH x PHOTO_HEIGHT so every photo across
     * every option — regardless of the original upload's dimensions or
     * aspect ratio — displays at the exact same size with no stretching.
     * This is what stands in for `object-fit: cover`, which dompdf doesn't
     * support (see the PHOTO_WIDTH/HEIGHT doc comment); the crop itself
     * still runs at 2x the display size so it stays sharp once placed in
     * the PDF.
     *
     * Rendering everything down to a fixed-size JPEG here also shrinks the
     * PDF considerably compared to embedding original uploads untouched
     * (a two-photo, one-option PDF dropped from ~2.3MB to a few hundred KB
     * in testing).
     */
    private function croppedPhotoDataUri(?string $storagePath): ?string
    {
        if ($storagePath === null) {
            return null;
        }

        $absolutePath = storage_path('app/public/'.$storagePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return null;
        }

        [$sourceWidth, $sourceHeight, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if (! $source) {
            return null;
        }

        $targetRatio = self::PHOTO_WIDTH / self::PHOTO_HEIGHT;
        $sourceRatio = $sourceWidth / $sourceHeight;

        // Center-crop the source down to the target aspect ratio first...
        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        // ...then resample that crop down to the final size (2x for
        // sharpness at the PDF's actual print/render resolution).
        $renderWidth = self::PHOTO_WIDTH * 2;
        $renderHeight = self::PHOTO_HEIGHT * 2;

        $destination = imagecreatetruecolor($renderWidth, $renderHeight);
        imagecopyresampled(
            $destination, $source,
            0, 0, $cropX, $cropY,
            $renderWidth, $renderHeight, $cropWidth, $cropHeight
        );

        ob_start();
        imagejpeg($destination, null, 82);
        $bytes = ob_get_clean();

        imagedestroy($source);
        imagedestroy($destination);

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    /**
     * Auto-generates the next quotation reference as "MM-YYYY/QNN", where
     * NN restarts from 01 each calendar month — same shape as
     * ContractController::nextReferenceNumber(), with a "Q" marker so the
     * two sequences (and the documents they identify) are never
     * ambiguous with one another. Deliberately independent of the Avinode
     * trip ID, which never appears on this document at all.
     */
    private function nextQuotationReference(): string
    {
        $prefix = now()->format('m-Y');

        $count = QuoteRequest::query()
            ->where('quotation_reference', 'like', "{$prefix}/Q%")
            ->lockForUpdate()
            ->count();

        return sprintf('%s/Q%02d', $prefix, $count + 1);
    }
}
