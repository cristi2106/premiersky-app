<?php

namespace App\Services;

use App\Models\QuoteOffer;

/**
 * Shapes a QuoteOffer (plus its re-derived itinerary and Tail match) into
 * the array the frontend renders — shared by QuoteController (the offer
 * list after a pull) and QuoteOfferController (a single offer after a
 * commission edit), so both stay in exact agreement about the shape.
 */
class QuoteOfferPresenter
{
    public function __construct(private readonly AvinodeQuoteEmailParser $parser)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(QuoteOffer $offer): array
    {
        $itinerary = $this->parser->findOfferItinerary(
            $offer->raw_email_body,
            $offer->aircraft_type,
            $offer->aircraft_registration,
            (float) $offer->offered_price
        );

        // Null (not 0.0) when a manually-created quote's reference offer
        // hasn't been priced yet — see QuoteRequestController::store() —
        // so the card can show "not set" and the sort/preview code can
        // tell "free" apart from "zero".
        $offeredPrice = $offer->offered_price !== null ? (float) $offer->offered_price : null;

        return [
            'id' => $offer->id,
            'avinode_request_id' => $offer->avinode_request_id,
            'operator_name' => $offer->operator_name,
            'aircraft_type' => $offer->aircraft_type,
            'aircraft_registration' => $offer->aircraft_registration,
            'offered_price' => $offeredPrice,
            'offered_currency' => $offer->offered_currency,
            'year_of_make' => $offer->year_of_make,
            'max_pax' => $offer->max_pax,
            'distance_nm' => $offer->distance_nm,
            'flight_duration' => $offer->flight_duration,
            'commission_type' => $offer->commission_type,
            'commission_value' => $offer->commission_value !== null ? (float) $offer->commission_value : null,
            'final_price' => $offer->final_price !== null ? (float) $offer->final_price : null,
            'selected' => $offer->selected,
            'source' => $offer->source,
            // Local time only — see AvinodeQuoteEmailParser::extractDetailBlocks(),
            // which never captures the UTC figures the raw body also has.
            'itinerary' => $itinerary,
            'tail' => $offer->tail ? [
                'id' => $offer->tail->id,
                'tail' => $offer->tail->tail,
                'category' => $offer->tail->category,
            ] : null,
        ];
    }
}
