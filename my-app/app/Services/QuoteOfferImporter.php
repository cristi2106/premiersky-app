<?php

namespace App\Services;

use App\Models\QuoteOffer;
use App\Models\QuoteRequest;
use App\Models\Tail;

/**
 * Runs a batch of raw emails (as returned by QuoteEmailSearcher) through
 * AvinodeQuoteEmailParser and persists whatever ACCEPTED offers it finds
 * against a QuoteRequest, matching each offer's registration (if any)
 * against the Tails table along the way.
 */
class QuoteOfferImporter
{
    public function __construct(private readonly AvinodeQuoteEmailParser $parser)
    {
    }

    /**
     * @param  list<array{from: string, subject: string, date: ?string, body: string}>  $emails
     * @return int number of offers imported (created or updated)
     */
    public function importFromEmails(QuoteRequest $quoteRequest, array $emails): int
    {
        $count = 0;

        foreach ($emails as $email) {
            $result = $this->parser->parse($email['body']);

            foreach ($result['offers'] as $offer) {
                $this->upsertOffer($quoteRequest, $offer, $email['body']);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function upsertOffer(QuoteRequest $quoteRequest, array $offer, string $rawBody): QuoteOffer
    {
        $tailId = $offer['aircraft_registration'] !== null
            ? $this->matchTail($offer['aircraft_registration'])?->id
            : null;

        // Avinode's Request ID is the strongest dedup key across re-pulls,
        // but not every accepted line has one (no detail block — see
        // AvinodeQuoteEmailParser's fixture notes). Without one, fall back
        // to the offer's own identifying fields so re-pulling the same
        // trip doesn't pile up duplicate rows.
        $matchOn = $offer['avinode_request_id'] !== null
            ? [
                'quote_request_id' => $quoteRequest->id,
                'avinode_request_id' => $offer['avinode_request_id'],
            ]
            : [
                'quote_request_id' => $quoteRequest->id,
                'avinode_request_id' => null,
                'operator_name' => $offer['operator_name'],
                'aircraft_type' => $offer['aircraft_type'],
                'aircraft_registration' => $offer['aircraft_registration'],
                'offered_price' => $offer['offered_price'],
            ];

        return QuoteOffer::updateOrCreate($matchOn, [
            'operator_name' => $offer['operator_name'],
            'aircraft_type' => $offer['aircraft_type'],
            'aircraft_registration' => $offer['aircraft_registration'],
            'offered_price' => $offer['offered_price'],
            'offered_currency' => $offer['offered_currency'],
            'year_of_make' => $offer['year_of_make'],
            'max_pax' => $offer['max_pax'],
            'distance_nm' => $offer['distance_nm'],
            'flight_duration' => $offer['flight_duration'],
            'raw_email_body' => $rawBody,
            'tail_id' => $tailId,
        ]);
    }

    private function matchTail(string $registration): ?Tail
    {
        return Tail::whereRaw('UPPER(tail) = ?', [strtoupper(trim($registration))])->first();
    }
}
