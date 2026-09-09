<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\QuoteRequestLeg;
use Illuminate\Support\Collection;

/**
 * Picks the one schedule shown for an entire trip — above the Quotes
 * offer list (see QuoteController::index()) and reused by
 * QuoteOfferController::generateContract() to fill a draft Contract's
 * leg — from whichever already-presented offer (see
 * QuoteOfferPresenter::present()) has the most complete itinerary.
 *
 * Picked cheapest-first (offers are expected pre-sorted by price, their
 * display order everywhere else), preferring the first offer that quoted
 * an arrival time — the top-level Itinerary line every offer in an email
 * shares never carries one, only each offer's own detail block does —
 * and falling back to the first offer's (arrival-time-less) itinerary if
 * none of them quoted one, or null if none of the offers have one at
 * all.
 *
 * A manually-created quote (see QuoteRequestController::store()) never
 * goes through resolve() at all — it has no parsed itinerary anywhere to
 * scan for (a manual offer's raw_email_body is always empty — see
 * QuoteOfferController::store()) — every caller checks
 * QuoteRequest::legs first and calls summarizeLegs() instead. Both
 * return the exact same shape so the "Schedule" block, and
 * generateContract()'s no-schedule bail-out check, don't need to know or
 * care which kind of quote they're looking at. generateContract() itself
 * goes further for a manual quote's *contract*-building step specifically
 * — every leg, not just this summary — see its own doc comment.
 */
class TripScheduleResolver
{
    /**
     * @param  list<array<string, mixed>>  $offers  price-ordered, each shaped like QuoteOfferPresenter::present()'s 'itinerary' entry
     * @return array{departure_date: ?string, departure_airport: ?string, arrival_airport: ?string, departure_icao: ?string, arrival_icao: ?string, departure_time: ?string, arrival_time: ?string, pax: ?int}|null
     */
    public function resolve(array $offers): ?array
    {
        $fallback = null;

        foreach ($offers as $offer) {
            $itinerary = $offer['itinerary'];

            if ($itinerary === null) {
                continue;
            }

            if ($itinerary['arrival_time'] !== null) {
                return $itinerary;
            }

            $fallback ??= $itinerary;
        }

        return $fallback;
    }

    /**
     * A compact single-schedule summary of a manually-created quote's full
     * itinerary — first leg's departure, last leg's arrival — for the
     * places that only ever showed one schedule to begin with (the
     * "Schedule" card above the offer list, the search-history list's
     * Schedule column). The full per-leg detail lives in quote_request_legs
     * itself; see Quotes/Schedule.vue for that, and the Quote PDF's
     * Itinerary table (built straight from the legs, not this summary —
     * see QuoteRequestController::buildItineraryLegs()) for the
     * client-facing multi-leg version.
     *
     * arrival_time is populated whenever the last leg's own is — unlike
     * the single-field schedule this replaced, which always left it null.
     * Every quote_request_leg is calculated against the quote's own
     * reference Tail (see QuoteRequest::tail_id and
     * QuoteRequestController::saveLegs()) as soon as one is picked, so
     * this can just read the result back rather than being unable to
     * compute it at all.
     *
     * @param  Collection<int, QuoteRequestLeg>  $legs  leg_number order (see QuoteRequest::legs()); departureAirport/arrivalAirport must already be loaded
     * @return array{departure_date: string, departure_airport: ?string, arrival_airport: ?string, departure_icao: ?string, arrival_icao: ?string, departure_time: string, arrival_time: ?string, pax: int}|null
     */
    public function summarizeLegs(Collection $legs): ?array
    {
        if ($legs->isEmpty()) {
            return null;
        }

        $first = $legs->first();
        $last = $legs->last();

        return [
            'departure_date' => $first->flight_date->format('d M Y'),
            'departure_airport' => $this->airportLabel($first->departureAirport),
            'arrival_airport' => $this->airportLabel($last->arrivalAirport),
            'departure_icao' => $first->departureAirport?->icao_code,
            'arrival_icao' => $last->arrivalAirport?->icao_code,
            'departure_time' => substr((string) $first->departure_time, 0, 5),
            'arrival_time' => $last->arrival_datetime?->format('H:i'),
            'pax' => $first->pax,
        ];
    }

    /**
     * "Sibiu International Airport (LRSB/SBZ)" — same shape as
     * ContractLegRow.vue's own airportLabel(), just server-side: a manual
     * quote's airports come from our own Airports table (picked via
     * SearchableSelect — see QuoteRequestController::store()), never from
     * free-text the way an email-parsed offer's schedule is, so there's
     * no "raw text" fallback to preserve the way
     * QuoteRequestController::resolveAirportLabel() has one for the PDF.
     */
    private function airportLabel(?Airport $airport): ?string
    {
        if ($airport === null) {
            return null;
        }

        return $airport->name.' ('.$airport->icao_code.($airport->iata_code ? '/'.$airport->iata_code : '').')';
    }
}
