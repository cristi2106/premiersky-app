<?php

namespace App\Services;

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
}
