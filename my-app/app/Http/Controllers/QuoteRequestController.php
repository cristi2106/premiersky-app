<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnReferenceCollision;
use App\Models\Airport;
use App\Models\AircraftSpeedReference;
use App\Models\QuoteOffer;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestLeg;
use App\Models\Tail;
use App\Services\AvinodeQuoteEmailParser;
use App\Services\FlightCalculator;
use App\Services\SequentialReferenceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuoteRequestController extends Controller
{
    use RetriesOnReferenceCollision;

    public function __construct(private readonly FlightCalculator $calculator) {}

    /**
     * Creates a Quote entirely by hand — for a trip that never came
     * through Avinode/email at all. avinode_trip_id is deliberately left
     * null (see the migration that made it nullable); everything an
     * email-pulled quote would otherwise derive from a parsed itinerary
     * is instead collected on this form as one or more legs and saved via
     * saveLegs() below — see TripScheduleResolver::summarizeLegs(), which
     * is what the offer page's "Schedule" block ends up reading these back
     * through.
     *
     * The `legs` payload is the exact same shape updateSchedule() accepts
     * (the post-creation schedule editor) — CreateManualQuoteModal now
     * builds its legs with the same ContractLegRow the editor uses — so
     * the two go through identical validation and the same saveLegs()
     * path, and can never disagree leg-for-leg.
     *
     * tail_id is required here (not just on the schedule editor) so every
     * leg is calculated immediately — see saveLegs()' own doc comment for
     * why a cruise speed is never optional there. That same Tail is also
     * dropped straight in as the quote's first offer (see
     * createReferenceOffer()), price/commission blank, so the user lands
     * on the offer page with it already there to fill in.
     *
     * Redirects straight to the new quote's own offer page (by id, not
     * trip_id — see QuoteController::index()'s own doc comment), where
     * "Add Offer" (QuoteOfferController::store()) adds any competing
     * options on top of that first one.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'tail_id' => ['required', 'integer', 'exists:tails,id'],
            'reference_label' => ['nullable', 'string', 'max:255'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.departure_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'legs.*.arrival_airport_id' => ['required', 'integer', 'different:legs.*.departure_airport_id', 'exists:airports,id'],
            'legs.*.flight_date' => ['required', 'date_format:Y-m-d'],
            'legs.*.departure_time' => ['required', 'date_format:H:i'],
            'legs.*.pax' => ['required', 'integer', 'min:1'],
        ]);

        $quoteRequest = null;

        DB::transaction(function () use (&$quoteRequest, $data) {
            $quoteRequest = QuoteRequest::create([
                'avinode_trip_id' => null,
                'client_id' => $data['client_id'],
                'tail_id' => $data['tail_id'],
                'reference_label' => $data['reference_label'] !== null && $data['reference_label'] !== ''
                    ? $data['reference_label']
                    : $this->generateReferenceLabel(),
            ]);

            $this->saveLegs($quoteRequest, $data['legs']);
            $this->createReferenceOffer($quoteRequest, $data['tail_id']);
        });

        return Redirect::route('quotes.index', ['quote_request_id' => $quoteRequest->id])
            ->with('success', 'Quote created.');
    }

    /**
     * Drops the quote's reference Tail in as its first offer, right away —
     * offered_price, offered_currency and commission all left null for the
     * user to fill in on the offer page (QuoteOfferCard's inline editor),
     * the same as they would for any other offer. Every "about the
     * aircraft" field is copied from the Tail via QuoteOffer::forTail() —
     * the exact same factory QuoteOfferController::store() ("Add Offer")
     * builds from, so the two paths can't disagree on what gets copied.
     *
     * status flips to 'offers_received' for the same reason "Add Offer"
     * flips it — an offer now exists — and QuoteController::presentQuote()
     * would flip it on the very next page load regardless.
     */
    private function createReferenceOffer(QuoteRequest $quoteRequest, int $tailId): void
    {
        $tail = Tail::with('aircraftSpeedReference:id,type_name')->findOrFail($tailId);

        $offer = QuoteOffer::forTail($tail);
        $offer->quoteRequest()->associate($quoteRequest);
        $offer->save();

        $quoteRequest->update(['status' => 'offers_received']);
    }

    /**
     * The schedule editor for a manually-created quote — unavailable for
     * an email-pulled one, which keeps deriving its schedule from parsed
     * offer data instead (see TripScheduleResolver) and has nothing here
     * to edit in the first place.
     */
    public function editSchedule(QuoteRequest $quoteRequest): Response
    {
        abort_if($quoteRequest->avinode_trip_id !== null, 404);

        $quoteRequest->load(['legs.departureAirport', 'legs.arrivalAirport', 'tail']);

        return Inertia::render('Quotes/Schedule', [
            'quoteRequest' => [
                'id' => $quoteRequest->id,
                'reference_label' => $quoteRequest->reference_label,
                'tail_id' => $quoteRequest->tail_id,
                'tail' => $quoteRequest->tail,
                'legs' => $quoteRequest->legs->map(fn (QuoteRequestLeg $leg) => [
                    'departure_airport_id' => $leg->departure_airport_id,
                    'arrival_airport_id' => $leg->arrival_airport_id,
                    'flight_date' => $leg->flight_date->format('Y-m-d'),
                    'departure_time' => substr($leg->departure_time, 0, 5),
                    'pax' => $leg->pax,
                    'departure_airport' => $leg->departureAirport,
                    'arrival_airport' => $leg->arrivalAirport,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Saves the schedule editor's tail + legs — same "delete every leg,
     * recreate them all" approach as ContractController::update() takes
     * with a contract's legs, for the same reason: legs have no identity
     * of their own worth preserving row-by-row (no other table points at
     * one), so reconciling an edited list in place would be
     * meaningfully more code for no real benefit over just replacing it.
     */
    public function updateSchedule(Request $request, QuoteRequest $quoteRequest): RedirectResponse
    {
        abort_if($quoteRequest->avinode_trip_id !== null, 404);

        $data = $request->validate([
            'tail_id' => ['required', 'integer', 'exists:tails,id'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.departure_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'legs.*.arrival_airport_id' => ['required', 'integer', 'different:legs.*.departure_airport_id', 'exists:airports,id'],
            'legs.*.flight_date' => ['required', 'date_format:Y-m-d'],
            'legs.*.departure_time' => ['required', 'date_format:H:i'],
            'legs.*.pax' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($quoteRequest, $data) {
            $quoteRequest->update(['tail_id' => $data['tail_id']]);
            $quoteRequest->legs()->delete();
            $this->saveLegs($quoteRequest, $data['legs']);
        });

        return Redirect::route('quotes.index', ['quote_request_id' => $quoteRequest->id])
            ->with('success', 'Schedule updated.');
    }

    /**
     * Calculates and persists each leg for a manually-created quote, in
     * order — reusing FlightCalculator exactly like
     * ContractController::saveLegs() does, just against the quote's own
     * reference Tail (quote_request_id.tail_id) instead of a contract's
     * committed aircraft_speed_reference_id. Called from both store()
     * (the legs entered at creation time) and updateSchedule() (the full
     * edited list), so the two can never calculate a leg differently from
     * one another.
     *
     * $quoteRequest->tail_id is assumed already set (by the caller, in
     * the same transaction) by the time this runs. The Tail itself can
     * still have no aircraft_speed_reference_id of its own on file — see
     * the nullable-columns migration on tails — in which case there's no
     * cruise speed to calculate with and every leg's calculated trio is
     * left null, same "leave it null rather than guess" stance
     * ContractController::saveLegs() takes when a Contract's own aircraft
     * doesn't confidently match.
     */
    private function saveLegs(QuoteRequest $quoteRequest, array $legs): void
    {
        $tail = Tail::with('aircraftSpeedReference')->find($quoteRequest->tail_id);
        $cruiseSpeedKnots = $tail?->aircraftSpeedReference?->cruise_speed_knots;

        foreach ($legs as $index => $legData) {
            $departureAirport = Airport::findOrFail($legData['departure_airport_id']);
            $arrivalAirport = Airport::findOrFail($legData['arrival_airport_id']);

            $result = null;

            if ($cruiseSpeedKnots !== null) {
                $departureLocal = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    "{$legData['flight_date']} {$legData['departure_time']}",
                    $departureAirport->timezone,
                );

                $result = $this->calculator->calculate(
                    $departureAirport,
                    $arrivalAirport,
                    $cruiseSpeedKnots,
                    $departureLocal,
                );
            }

            QuoteRequestLeg::create([
                'quote_request_id' => $quoteRequest->id,
                'leg_number' => $index + 1,
                'departure_airport_id' => $departureAirport->id,
                'arrival_airport_id' => $arrivalAirport->id,
                'flight_date' => $legData['flight_date'],
                'departure_time' => $legData['departure_time'],
                'pax' => $legData['pax'],
                'flight_duration_minutes' => $result?->durationMinutes,
                'distance_nm' => $result !== null ? round($result->distanceNauticalMiles, 1) : null,
                'arrival_datetime' => $result?->arrivalLocal->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * "MANUAL-2026-08-29-01" — restarts from 01 each day, taking the
     * highest existing suffix for today rather than counting rows, same
     * reasoning as SequentialReferenceGenerator (a deleted quote
     * shouldn't free up its number for reuse). Not reused as-is because
     * the format itself differs (daily, not monthly, and no DB-unique
     * constraint backs it — this is a display label the user can always
     * override by typing their own, not a document reference number
     * anything downstream keys off of, so the rare double-submit race
     * producing a duplicate-looking suffix is a cosmetic non-issue, not
     * worth a locked transaction + collision retry over).
     */
    private function generateReferenceLabel(): string
    {
        $prefix = 'MANUAL-'.now()->format('Y-m-d');

        $maxSuffix = QuoteRequest::where('reference_label', 'like', "{$prefix}-%")
            ->pluck('reference_label')
            ->map(function (string $label) {
                preg_match('/(\d+)$/', $label, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return sprintf('%s-%02d', $prefix, $maxSuffix + 1);
    }

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
     * Removes one trip (or one manually-created quote) from the search
     * history entirely — the QuoteRequest row(s) and every quote_offers
     * row hanging off them.
     *
     * For an email-pulled trip, "row(s)" is plural: a trip ID can still
     * have more than one QuoteRequest on file (legacy case-variant
     * duplicates — see QuoteController::resolveQuoteRequest()). The
     * history list already collapses those into one entry, so deleting
     * must take the whole case-insensitive group with it, otherwise the
     * trip would reappear from a leftover row on the next render. A
     * manually-created quote (avinode_trip_id null) has no such group to
     * begin with — see QuoteController::searchHistory()'s own groupBy —
     * so it's always just the one row; grouping it by trip ID the same
     * way would silently match nothing (every column comparison against
     * NULL is false, never true, even UPPER(NULL) = '') and leave it
     * undeleted despite the redirect looking like success, which is
     * exactly what happened here before this null check existed.
     *
     * quote_offers and quote_request_legs both go with it via their own
     * ON DELETE CASCADE on quote_request_id (see the create_quote_offers
     * and create_quote_request_legs migrations); the explicit deletes
     * here first make that independent of whether SQLite FK enforcement
     * happens to be on for this connection. Nothing else references
     * either table — in particular no column in `contracts`/`contract_legs`
     * points back at a quote (a Contract created by
     * QuoteOfferController::generateContract() copies the values it needs
     * and keeps none of the linkage), so a contract generated from this
     * quote is completely untouched by any of this.
     */
    public function destroy(QuoteRequest $quoteRequest): RedirectResponse
    {
        $label = $quoteRequest->avinode_trip_id !== null
            ? strtoupper($quoteRequest->avinode_trip_id)
            : ($quoteRequest->reference_label ?? 'this quote');

        $requestIds = $quoteRequest->avinode_trip_id !== null
            ? QuoteRequest::whereRaw('UPPER(avinode_trip_id) = ?', [$label])->pluck('id')
            : collect([$quoteRequest->id]);

        DB::transaction(function () use ($requestIds) {
            QuoteOffer::whereIn('quote_request_id', $requestIds)->delete();
            QuoteRequestLeg::whereIn('quote_request_id', $requestIds)->delete();
            QuoteRequest::whereKey($requestIds)->delete();
        });

        return Redirect::route('quotes.index')->with('success', "Removed {$label} from search history.");
    }

    /**
     * Wipes the whole search history — every QuoteRequest and every
     * quote_offers/quote_request_legs row. Same cascade/contract-safety
     * notes as destroy() above; contracts are a separate table with no
     * reference to any of this, so they all survive.
     */
    public function clearHistory(): RedirectResponse
    {
        DB::transaction(function () {
            QuoteOffer::query()->delete();
            QuoteRequestLeg::query()->delete();
            QuoteRequest::query()->delete();
        });

        return Redirect::route('quotes.index')->with('success', 'Search history cleared.');
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
        $quoteRequest->load(['client', 'legs.departureAirport', 'legs.arrivalAirport']);

        abort_if($quoteRequest->client === null, 400, 'Select a client before generating a quotation PDF.');

        $selectedOffers = $quoteRequest->offers()
            ->where('selected', true)
            ->with('tail.aircraftSpeedReference')
            ->orderBy('offered_price')
            ->get();

        abort_if($selectedOffers->isEmpty(), 400, 'Select at least one offer before generating a quotation PDF.');

        // A manual quote's auto-created reference offer (see
        // QuoteOffer::forTail()) starts with offered_price/offered_currency
        // both null — meant to be filled in on the offer page afterward —
        // but nothing stops it from being checked "selected" before that
        // happens. Catch that here, before quotation_reference is even
        // assigned or the PDF is attempted: buildOfferForPdf()'s total_price
        // casts a null offered_price to 0.0 (never null), but offered_currency
        // passes straight through, and quotes.pdf's $formatAmount closure is
        // typed (float $amount, string $currency) — a null currency there is
        // a TypeError, not a validation failure. price-block.blade.php has
        // its own null guard as a last-resort backstop, but the real fix is
        // to never let PDF generation start at all.
        $incompleteOffer = $selectedOffers->first(
            fn (QuoteOffer $offer) => $offer->offered_price === null || $offer->offered_currency === null
        );

        abort_if(
            $incompleteOffer !== null,
            400,
            "Offer for {$incompleteOffer?->aircraft_type} is missing a price — please add one before generating the PDF."
        );

        if ($quoteRequest->quotation_reference === null) {
            $this->retryOnReferenceCollision(function () use ($quoteRequest) {
                DB::transaction(function () use ($quoteRequest) {
                    $quoteRequest->update(['quotation_reference' => $this->nextQuotationReference()]);
                });
            });
        }

        $itineraryLegs = $this->buildItineraryLegs($quoteRequest, $selectedOffers->first(), $parser);
        $offers = $selectedOffers->map(fn ($offer) => $this->buildOfferForPdf($offer))->all();

        $pdf = Pdf::loadView('quotes.pdf', [
            'quoteRequest' => $quoteRequest,
            'client' => $quoteRequest->client,
            'itineraryLegs' => $itineraryLegs,
            // Whether flight_duration_minutes on each of the above is
            // meaningful — only ever true for a manually-created quote
            // (quote_request_legs, each calculated against the quote's own
            // reference Tail — see saveLegs()); an email-pulled quote's
            // parsed itinerary never carried a duration at all, so the
            // PDF's Flight Time column would just be blank for every row
            // and isn't shown, matching how it never showed one before
            // this multi-leg support existed.
            'showFlightTime' => $quoteRequest->legs->isNotEmpty(),
            'offers' => $offers,
        ]);

        $filenameSafeReference = str_replace('/', '-', $quoteRequest->quotation_reference);

        return $pdf->stream("quotation-{$filenameSafeReference}.pdf");
    }

    /**
     * The Itinerary table's rows — every quote_request_leg, in order, for
     * a manually-created quote (see QuoteRequestController::saveLegs()),
     * or the single itinerary parsed from whichever selected offer sorts
     * first (cheapest) for an email-pulled one, exactly as this method
     * (then named buildItinerary()) always worked — see the class doc on
     * QuoteController for why only one offer's itinerary is shown at all,
     * repeated per option, rather than once per shared schedule.
     *
     * Both branches return the same shape, so quotes.pdf's Itinerary table
     * doesn't need to know which kind of quote it's rendering — only
     * whether to show the Leg column at all (more than one row) and the
     * Flight Time column (only ever populated on the manual-quote branch —
     * see pdf()'s own 'showFlightTime' prop).
     *
     * @return list<array{date: ?string, departure: ?string, arrival: ?string, departure_time: ?string, arrival_time: ?string, pax: ?int, flight_duration_minutes: ?int}>
     */
    private function buildItineraryLegs(QuoteRequest $quoteRequest, QuoteOffer $cheapestOffer, AvinodeQuoteEmailParser $parser): array
    {
        if ($quoteRequest->legs->isNotEmpty()) {
            return $quoteRequest->legs->map(fn (QuoteRequestLeg $leg) => [
                'date' => $leg->flight_date->format('d M Y'),
                'departure' => $this->airportLabel($leg->departureAirport),
                'arrival' => $this->airportLabel($leg->arrivalAirport),
                'departure_time' => substr($leg->departure_time, 0, 5),
                'arrival_time' => $leg->arrival_datetime?->format('H:i'),
                'pax' => $leg->pax,
                'flight_duration_minutes' => $leg->flight_duration_minutes,
            ])->values()->all();
        }

        $parsed = $parser->findOfferItinerary(
            $cheapestOffer->raw_email_body,
            $cheapestOffer->aircraft_type,
            $cheapestOffer->aircraft_registration,
            (float) $cheapestOffer->offered_price
        ) ?? [];

        return [[
            'date' => $parsed['departure_date'] ?? null,
            'departure' => $this->resolveAirportLabel($parsed['departure_icao'] ?? null, $parsed['departure_airport'] ?? null),
            'arrival' => $this->resolveAirportLabel($parsed['arrival_icao'] ?? null, $parsed['arrival_airport'] ?? null),
            'departure_time' => $parsed['departure_time'] ?? null,
            'arrival_time' => $parsed['arrival_time'] ?? null,
            'pax' => $parsed['pax'] ?? null,
            'flight_duration_minutes' => null,
        ]];
    }

    /**
     * "Sibiu International Airport (LRSB/SBZ)" — identical shape to
     * resolveAirportLabel() below, just starting from an already-resolved
     * Airport model (a quote_request_leg's departure/arrival are always
     * real rows on file — picked via SearchableSelect, not free-text —
     * see QuoteRequestController::store()) instead of an ICAO string that
     * still needs looking up.
     */
    private function airportLabel(Airport $airport): string
    {
        return $airport->name.' ('.($airport->iata_code ?: $airport->icao_code).')';
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
     *
     * See SequentialReferenceGenerator for why this is a max-suffix
     * lookup rather than a row count, and retryOnReferenceCollision() at
     * this method's call site for how a same-instant collision with
     * another request is handled.
     */
    private function nextQuotationReference(): string
    {
        return SequentialReferenceGenerator::next(QuoteRequest::class, 'quotation_reference', 'Q');
    }
}
