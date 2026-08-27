<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnReferenceCollision;
use App\Models\AircraftSpeedReference;
use App\Models\Airport;
use App\Models\Contract;
use App\Models\ContractLeg;
use App\Models\QuoteOffer;
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
     * Updates an offer's commission and/or its "selected for the client
     * PDF" flag. Each field uses `sometimes` so a request touching only
     * one (e.g. the checkbox toggling `selected`) doesn't also validate,
     * require, or overwrite the other — the commission inputs and the
     * selection checkbox save independently of each other.
     *
     * Commission's final_price is always recalculated here, server-side —
     * the client shows a live preview as you type, but the stored,
     * authoritative number is never trusted from the request.
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
            'commission_type' => ['sometimes', 'nullable', Rule::in(QuoteOffer::COMMISSION_TYPES)],
            'commission_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        $quoteOffer->fill($data);

        if ($request->hasAny(['commission_type', 'commission_value'])) {
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
        $quoteOffer->load('quoteRequest.client');
        $quoteRequest = $quoteOffer->quoteRequest;

        // Mirrors the "Generate PDF" button's own guard (see Quotes/Index.vue's
        // pdfHint) — the button itself is disabled without a client
        // selected, this is just the server not trusting that alone.
        if ($quoteRequest->client === null) {
            return Redirect::route('quotes.index', ['trip_id' => $quoteRequest->avinode_trip_id, 'view' => 1])
                ->with('contractError', 'Select a client before generating a contract.');
        }

        // The same itinerary every offer on this trip shares — see
        // TripScheduleResolver — not this offer's own raw_email_body,
        // so the leg this creates always agrees with the Schedule block
        // already shown above the offer list.
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
            return Redirect::route('quotes.index', ['trip_id' => $quoteRequest->avinode_trip_id, 'view' => 1])
                ->with('contractError', 'Could not create a contract — no flight schedule was found for this trip.');
        }

        $aircraftMatch = $aircraftMatcher->match($quoteOffer->aircraft_type);
        // 'contains' is only ever returned when exactly one candidate
        // matched (see AircraftTypeMatcher) — an 'ambiguous' or 'none'
        // result is what leaves this null.
        $aircraftSpeedReferenceId = in_array($aircraftMatch['status'], ['exact', 'contains'], true)
            ? $aircraftMatch['id']
            : null;

        $departureAirport = $this->findAirportByIcao($schedule['departure_icao'] ?? null);
        $arrivalAirport = $this->findAirportByIcao($schedule['arrival_icao'] ?? null);

        $contract = null;

        $this->retryOnReferenceCollision(function () use (
            &$contract, $quoteRequest, $quoteOffer, $aircraftSpeedReferenceId,
            $flightDate, $schedule, $departureAirport, $arrivalAirport, $calculator
        ) {
            DB::transaction(function () use (
                &$contract, $quoteRequest, $quoteOffer, $aircraftSpeedReferenceId,
                $flightDate, $schedule, $departureAirport, $arrivalAirport, $calculator
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

                $this->createLegFromSchedule(
                    $contract, $aircraftSpeedReferenceId, $departureAirport, $arrivalAirport,
                    $flightDate, $schedule['departure_time'], $schedule['pax'], $calculator
                );
            });
        });

        return Redirect::route('contracts.edit', $contract)
            ->with('status', 'Draft contract created from this quote — please review before finalizing.');
    }

    /**
     * The draft's single leg. Distance/flight time/arrival are computed
     * via FlightCalculator exactly like a hand-built contract's legs —
     * but only when both airports *and* the aircraft matched, since all
     * three are required inputs to that calculation. Left null otherwise
     * (see the migration that made these columns nullable): the Edit
     * page's own leg row already recalculates live once the missing
     * piece is filled in by hand, so nothing downstream needs these to
     * be pre-computed to work correctly.
     */
    private function createLegFromSchedule(
        Contract $contract,
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
            'leg_number' => 1,
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
}
