<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnReferenceCollision;
use App\Models\AircraftSpeedReference;
use App\Models\Airport;
use App\Models\Contract;
use App\Models\ContractLeg;
use App\Models\QuoteOffer;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestLeg;
use App\Models\Tail;
use App\Services\AircraftTypeMatcher;
use App\Services\FlightCalculator;
use App\Services\QuoteOfferPresenter;
use App\Services\SequentialReferenceGenerator;
use App\Services\TripScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Throwable;

class QuoteOfferController extends Controller
{
    use RetriesOnReferenceCollision;

    /**
     * Adds an offer by hand — for an operator that responded by phone or
     * another channel instead of email, so the Quotes module isn't limited
     * to whatever AvinodeQuoteEmailParser could pull out of a message.
     *
     * The Tail is the only real input: everything else that's "about the
     * aircraft" (operator, type, year, seats) is copied from its own
     * record rather than re-typed, so it can never disagree with what the
     * Tails module already has on file. avinode_request_id,
     * aircraft_registration mismatches, distance/flight-time, and
     * raw_email_body all stay null/empty — there's no parsed itinerary or
     * source email for this offer; it uses the trip's shared schedule
     * block like every other offer already does (see
     * TripScheduleResolver, which simply skips an offer with no
     * itinerary).
     *
     * A full Inertia redirect back to quotes.index, not a JSON response —
     * unlike update()'s single-field patch, a brand new offer changes
     * several things this page shows at once (the offers list itself, the
     * request's status once it flips to "offers_received", the search
     * history row's offer count) and QuoteController::index() already
     * knows how to (re)compute all of them consistently; duplicating that
     * here would just be a second place for them to drift apart.
     */
    public function store(Request $request, QuoteRequest $quoteRequest): RedirectResponse
    {
        $data = $request->validate([
            'tail_id' => ['required', 'integer', 'exists:tails,id'],
            'offered_price' => ['required', 'numeric', 'min:0'],
            'offered_currency' => ['required', Rule::in(['EUR', 'RON', 'USD'])],
            'commission_type' => ['nullable', Rule::in(QuoteOffer::COMMISSION_TYPES)],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tail = Tail::with('aircraftSpeedReference:id,type_name')->findOrFail($data['tail_id']);

        // The aircraft fields all come from the Tail via the shared
        // factory (see QuoteOffer::forTail()) — the same one
        // QuoteRequestController::store() uses for a manual quote's
        // auto-created reference offer, so the two can't diverge on what
        // gets copied. This path additionally has a price in hand.
        $offer = QuoteOffer::forTail($tail)->fill([
            'offered_price' => $data['offered_price'],
            'offered_currency' => $data['offered_currency'],
            'commission_type' => $data['commission_type'] ?? null,
            'commission_value' => $data['commission_value'] ?? null,
        ]);

        $offer->quoteRequest()->associate($quoteRequest);
        $offer->final_price = $offer->calculateFinalPrice();
        $offer->save();

        // Same "at least one offer means offers received" flip
        // QuoteController::index() applies after an email pull — a
        // manually-added offer is just as much a real offer as a parsed
        // one, so this trip is no longer merely "pending" either.
        if ($quoteRequest->status !== 'offers_received') {
            $quoteRequest->update(['status' => 'offers_received']);
        }

        return Redirect::route('quotes.index', $this->quoteReturnRouteParams($quoteRequest))
            ->with('success', 'Offer added.');
    }

    /**
     * Updates an offer's price/currency, its commission, and/or its
     * "selected for the client PDF" flag. Every field uses `sometimes` so
     * a request touching only one (e.g. the checkbox toggling `selected`)
     * doesn't also validate, require, or overwrite the others — the price
     * inputs, the commission inputs and the selection checkbox each save
     * independently.
     *
     * The price fields are here because a manually-created quote's
     * auto-created reference offer (QuoteRequestController::store()) lands
     * with no price at all — this inline editor is where the user enters
     * it, the same card any other offer's commission is entered on.
     *
     * final_price is always recalculated here, server-side, whenever the
     * price or the commission moves — the client shows a live preview as
     * you type, but the stored, authoritative number is never trusted
     * from the request.
     *
     * Plain JSON, not an Inertia response: this is called from the Quotes
     * page's offer cards and only needs to patch one offer in the page's
     * already-loaded list, not re-render/replace the whole page (which
     * would also mean re-deciding what an Inertia partial reload should
     * and shouldn't touch on a route that isn't quotes.index).
     */
    public function update(Request $request, QuoteOffer $quoteOffer, QuoteOfferPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'offered_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'offered_currency' => ['sometimes', 'nullable', Rule::in(['EUR', 'RON', 'USD'])],
            'commission_type' => ['sometimes', 'nullable', Rule::in(QuoteOffer::COMMISSION_TYPES)],
            'commission_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        $quoteOffer->fill($data);

        if ($request->hasAny(['offered_price', 'commission_type', 'commission_value'])) {
            $quoteOffer->final_price = $quoteOffer->calculateFinalPrice();
        }

        $quoteOffer->save();

        return response()->json([
            'offer' => $presenter->present($quoteOffer->fresh('tail')),
        ]);
    }

    /**
     * Creates a draft Contract pre-filled from this one offer, so a
     * client accepting a quote doesn't mean re-typing everything by hand
     * into the Contracts module. Deliberately a *starting point*, not a
     * finished document: aircraft type and airports are both matched
     * best-effort (see AircraftTypeMatcher and the ICAO lookup below) and
     * left null rather than guessed wrong when that match isn't
     * confident — the redirect below always lands on the Contract's edit
     * form specifically so a null (or a wrong-looking) match gets caught
     * before anything is finalized or a PDF goes out, never on the PDF
     * itself.
     *
     * Available on every offer, not just ones checked for the client PDF
     * — which offer becomes the client-facing quotation and which offer
     * becomes the contract are independent decisions once a client has
     * accepted verbally/by email, so this doesn't read `selected` at all.
     */
    public function generateContract(
        QuoteOffer $quoteOffer,
        QuoteOfferPresenter $presenter,
        AircraftTypeMatcher $aircraftMatcher,
        TripScheduleResolver $scheduleResolver,
        FlightCalculator $calculator
    ): RedirectResponse {
        $quoteOffer->load([
            'quoteRequest.client',
            'quoteRequest.legs.departureAirport',
            'quoteRequest.legs.arrivalAirport',
        ]);
        $quoteRequest = $quoteOffer->quoteRequest;

        // Mirrors the "Generate PDF" button's own guard (see Quotes/Index.vue's
        // pdfHint) — the button itself is disabled without a client
        // selected, this is just the server not trusting that alone.
        if ($quoteRequest->client === null) {
            return Redirect::route('quotes.index', $this->quoteReturnRouteParams($quoteRequest))
                ->with('contractError', 'Select a client before generating a contract.');
        }

        // A manually-created quote's own quote_request_legs — however many
        // there are — if it has any (see QuoteRequestController::saveLegs());
        // otherwise the single itinerary every offer on this email-pulled
        // trip shares (see TripScheduleResolver), not this offer's own
        // raw_email_body, so the leg(s) this creates always agree with the
        // Schedule block already shown above the offer list.
        $legSpecs = $quoteRequest->legs->isNotEmpty()
            ? $this->legSpecsFromQuoteRequestLegs($quoteRequest)
            : $this->legSpecsFromParsedSchedule($quoteRequest, $presenter, $scheduleResolver);

        if ($legSpecs === null) {
            return Redirect::route('quotes.index', $this->quoteReturnRouteParams($quoteRequest))
                ->with('contractError', 'Could not create a contract — no flight schedule was found for this trip.');
        }

        $aircraftMatch = $aircraftMatcher->match($quoteOffer->aircraft_type);
        // 'contains' is only ever returned when exactly one candidate
        // matched (see AircraftTypeMatcher) — an 'ambiguous' or 'none'
        // result is what leaves this null.
        $aircraftSpeedReferenceId = in_array($aircraftMatch['status'], ['exact', 'contains'], true)
            ? $aircraftMatch['id']
            : null;

        $contract = null;

        $this->retryOnReferenceCollision(function () use (
            &$contract, $quoteRequest, $quoteOffer, $aircraftSpeedReferenceId, $legSpecs, $calculator
        ) {
            DB::transaction(function () use (
                &$contract, $quoteRequest, $quoteOffer, $aircraftSpeedReferenceId, $legSpecs, $calculator
            ) {
                $contract = Contract::create([
                    'client_id' => $quoteRequest->client_id,
                    'aircraft_speed_reference_id' => $aircraftSpeedReferenceId,
                    'reference_number' => SequentialReferenceGenerator::next(Contract::class, 'reference_number'),
                    // offered_price + commission, already combined — see
                    // QuoteOffer::calculateFinalPrice() — falling back to
                    // the bare offered price only if no commission has
                    // been entered yet (final_price is then still null),
                    // same fallback the client PDF itself uses.
                    'price' => $quoteOffer->final_price ?? $quoteOffer->offered_price,
                    'currency' => $quoteOffer->offered_currency,
                    // Commission is a separate concern the offer already
                    // tracked above — VAT is a contract-only figure with
                    // no equivalent on a quote offer, so there's nothing
                    // to pre-fill it from; it keeps its schema default.
                    'vat_percentage' => 0,
                    'status' => 'draft',
                ]);

                foreach ($legSpecs as $index => $spec) {
                    $this->createLegFromSchedule(
                        $contract, $index + 1, $aircraftSpeedReferenceId,
                        $spec['departure_airport'], $spec['arrival_airport'],
                        $spec['flight_date'], $spec['departure_time'], $spec['pax'], $calculator
                    );
                }
            });
        });

        return Redirect::route('contracts.edit', $contract)
            ->with('status', 'Draft contract created from this quote — please review before finalizing.');
    }

    /**
     * One leg spec per quote_request_leg, in order — the draft Contract
     * this offer generates gets every leg the manual quote has, not just
     * the first, since a client accepting one operator's price for a
     * multi-leg trip is accepting it for the whole trip.
     *
     * @return list<array{departure_airport: ?Airport, arrival_airport: ?Airport, flight_date: string, departure_time: string, pax: int}>
     */
    private function legSpecsFromQuoteRequestLegs(QuoteRequest $quoteRequest): array
    {
        return $quoteRequest->legs->map(fn (QuoteRequestLeg $leg) => [
            'departure_airport' => $leg->departureAirport,
            'arrival_airport' => $leg->arrivalAirport,
            'flight_date' => $leg->flight_date->format('Y-m-d'),
            'departure_time' => substr($leg->departure_time, 0, 5),
            'pax' => $leg->pax,
        ])->values()->all();
    }

    /**
     * A single leg spec parsed from whichever offer TripScheduleResolver
     * picks — the email-pulled path's existing behavior, unchanged, just
     * returning the same shape legSpecsFromQuoteRequestLegs() does so
     * generateContract() can treat both sources identically. Null when
     * there's nothing confident enough to build even one leg from — see
     * the inline check below for why.
     *
     * @return list<array{departure_airport: ?Airport, arrival_airport: ?Airport, flight_date: string, departure_time: string, pax: int}>|null
     */
    private function legSpecsFromParsedSchedule(
        QuoteRequest $quoteRequest,
        QuoteOfferPresenter $presenter,
        TripScheduleResolver $scheduleResolver
    ): ?array {
        $presentedOffers = $quoteRequest->offers()
            ->orderBy('offered_price')
            ->get()
            ->map(fn (QuoteOffer $offer) => $presenter->present($offer))
            ->all();

        $schedule = $scheduleResolver->resolve($presentedOffers);
        $flightDate = $this->parseScheduleDate($schedule['departure_date'] ?? null);

        // date, time and pax all come from the same single regex match
        // (see AvinodeQuoteEmailParser::extractSimpleItinerary()) — they're
        // either all present together or all absent together, never a
        // partial mix. Unlike aircraft type/airports, there's no
        // "leave it blank" option for these: flight_date, departure_time
        // and pax are all NOT NULL columns, so with nothing to put there
        // this can't create a leg at all rather than one with guessed
        // values.
        if ($schedule === null || $flightDate === null || $schedule['departure_time'] === null || $schedule['pax'] === null) {
            return null;
        }

        return [[
            'departure_airport' => $this->findAirportByIcao($schedule['departure_icao'] ?? null),
            'arrival_airport' => $this->findAirportByIcao($schedule['arrival_icao'] ?? null),
            'flight_date' => $flightDate,
            'departure_time' => $schedule['departure_time'],
            'pax' => $schedule['pax'],
        ]];
    }

    /**
     * One draft leg. Distance/flight time/arrival are computed via
     * FlightCalculator exactly like a hand-built contract's legs — but
     * only when both airports *and* the aircraft matched, since all three
     * are required inputs to that calculation. Left null otherwise (see
     * the migration that made these columns nullable): the Edit page's
     * own leg row already recalculates live once the missing piece is
     * filled in by hand, so nothing downstream needs these to be
     * pre-computed to work correctly.
     */
    private function createLegFromSchedule(
        Contract $contract,
        int $legNumber,
        ?int $aircraftSpeedReferenceId,
        ?Airport $departureAirport,
        ?Airport $arrivalAirport,
        string $flightDate,
        string $departureTime,
        int $pax,
        FlightCalculator $calculator
    ): void {
        $durationMinutes = null;
        $distanceNm = null;
        $arrivalDatetime = null;

        if ($departureAirport !== null && $arrivalAirport !== null && $aircraftSpeedReferenceId !== null) {
            $aircraft = AircraftSpeedReference::find($aircraftSpeedReferenceId);

            $departureLocal = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                "{$flightDate} {$departureTime}",
                $departureAirport->timezone,
            );

            $result = $calculator->calculate($departureAirport, $arrivalAirport, $aircraft->cruise_speed_knots, $departureLocal);

            $durationMinutes = $result->durationMinutes;
            $distanceNm = round($result->distanceNauticalMiles, 1);
            $arrivalDatetime = $result->arrivalLocal->format('Y-m-d H:i:s');
        }

        ContractLeg::create([
            'contract_id' => $contract->id,
            'leg_number' => $legNumber,
            'departure_airport_id' => $departureAirport?->id,
            'arrival_airport_id' => $arrivalAirport?->id,
            'flight_date' => $flightDate,
            'departure_time' => $departureTime,
            'pax' => $pax,
            'flight_duration_minutes' => $durationMinutes,
            'distance_nm' => $distanceNm,
            'arrival_datetime' => $arrivalDatetime,
        ]);
    }

    /**
     * "04 Aug 2026" (AvinodeQuoteEmailParser's own date format, see
     * extractSimpleItinerary()) to "2026-08-04". Null in, null out; also
     * null on anything that fails to parse rather than throwing, so a
     * surprise format is just one more reason to leave the leg uncreated
     * (see the schedule-completeness check above) instead of a 500.
     */
    private function parseScheduleDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('d M Y', $value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Exact ICAO match against our own Airports table — same lookup (and
     * the same "no fuzzy fallback" stance) as
     * QuoteRequestController::resolveAirportLabel() uses for the client
     * PDF's airport line, just returning the model instead of a display
     * string.
     */
    private function findAirportByIcao(?string $icao): ?Airport
    {
        return $icao !== null ? Airport::where('icao_code', $icao)->first() : null;
    }

    /**
     * Route params to land back on this quote's own offer page — trip_id
     * (plus the "don't re-pull" view flag) for an email-pulled quote, or
     * quote_request_id for one with no avinode_trip_id at all (a
     * manually-created quote — see QuoteRequestController::store() — has
     * nothing to search the mailbox by, the same reason
     * QuoteController::index() only ever opens one by id).
     */
    private function quoteReturnRouteParams(QuoteRequest $quoteRequest): array
    {
        return $quoteRequest->avinode_trip_id !== null
            ? ['trip_id' => $quoteRequest->avinode_trip_id, 'view' => 1]
            : ['quote_request_id' => $quoteRequest->id];
    }
}
