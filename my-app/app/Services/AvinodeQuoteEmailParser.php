<?php

namespace App\Services;

/**
 * Turns a raw Avinode notification email body into structured quote offers.
 *
 * Pure/stateless on purpose — no IMAP, no Eloquent, no I/O. It takes a
 * string, returns an array. That's what makes it possible to unit-test
 * against saved fixtures (tests/fixtures/avinode-emails) without touching
 * the mailbox, and it's what QuoteEmailSearcher's results get run through
 * once retrieval is wired to parsing.
 *
 * Only ACCEPTED aircraft lines become offers. DECLINED and UNANSWERED lines
 * are real information (a full picture of who was asked), but they're not
 * bookable, so they're dropped here entirely — see parseAircraftLines().
 *
 * The subject line is never consulted. Real Avinode subjects are
 * inconsistent (a "Message for Trip X" subject can still carry a fully
 * accepted offer in the body — see sample-2 in the fixtures), so every
 * field is derived from the body's own "Aircraft" section and, when
 * present, that offer's detail block.
 */
class AvinodeQuoteEmailParser
{
    /**
     * @return array{offers: list<array<string, mixed>>}
     */
    public function parse(string $rawBody): array
    {
        $body = $this->normalize($rawBody);

        $acceptedLines = array_values(array_filter(
            $this->parseAircraftLines($this->extractSection($body, 'Aircraft', 'Seller')),
            fn (array $line) => $line['status'] === 'ACCEPTED'
        ));

        if ($acceptedLines === []) {
            return ['offers' => []];
        }

        $sellerFallbackOperator = $this->extractSellerOperator($body);
        $simpleItinerary = $this->extractSimpleItinerary($body);
        $detailBlocks = $this->extractDetailBlocks($body);

        $offers = [];

        foreach ($acceptedLines as $line) {
            $detail = $this->matchDetailBlock($line, $detailBlocks);

            $operator = $line['operated_by']
                ?? $detail['operated_by']
                ?? $sellerFallbackOperator;

            $offers[] = [
                'avinode_request_id' => $detail['request_id'] ?? null,
                'operator_name' => $operator,
                'aircraft_type' => $line['type'],
                'aircraft_registration' => $line['registration'],
                'offered_price' => $line['price'],
                'offered_currency' => $line['currency'],
                'year_of_make' => $detail['year_of_make'] ?? null,
                'max_pax' => $detail['max_pax'] ?? null,
                'distance_nm' => $detail['distance_nm'] ?? null,
                'flight_duration' => $detail['flight_duration'] ?? null,
                // Not persisted as quote_offers columns — re-derived from
                // raw_email_body whenever the offer is displayed instead.
                // The detail block (this specific operator's own quoted
                // schedule) wins over the top-level "Itinerary" line (the
                // client's original request) when both exist, since the two
                // can genuinely differ — see sample-4's fixture.
                'itinerary' => [
                    'departure_date' => $detail['departure_date'] ?? $simpleItinerary['date'] ?? null,
                    'departure_airport' => $simpleItinerary['departure_airport'] ?? null,
                    'arrival_airport' => $simpleItinerary['arrival_airport'] ?? null,
                    // Bare ICAO codes, separate from the display strings
                    // above — lets a consumer (the Quotation PDF) look the
                    // airport up against our own Airports table for its
                    // full name/IATA code, the same way Contracts do.
                    'departure_icao' => $simpleItinerary['departure_icao'] ?? null,
                    'arrival_icao' => $simpleItinerary['arrival_icao'] ?? null,
                    'departure_time' => $detail['departure_time'] ?? $simpleItinerary['time'] ?? null,
                    'arrival_time' => $detail['arrival_time'] ?? null,
                    'pax' => $simpleItinerary['pax'] ?? null,
                ],
            ];
        }

        return ['offers' => $offers];
    }

    /**
     * Re-derive a single offer's itinerary from its own stored
     * raw_email_body — used for display once an offer is already
     * persisted, rather than caching itinerary fields as quote_offers
     * columns. Matches on the same identifying fields the importer
     * dedupes on (type, registration, price); falls back to the first
     * accepted offer found if that fails, since raw_email_body is that
     * offer's own source and should always contain a match.
     *
     * @return array{departure_date: ?string, departure_airport: ?string, arrival_airport: ?string, departure_time: ?string, arrival_time: ?string, pax: ?int}|null
     */
    public function findOfferItinerary(
        string $rawBody,
        string $aircraftType,
        ?string $aircraftRegistration,
        float $offeredPrice
    ): ?array {
        $offers = $this->parse($rawBody)['offers'];

        foreach ($offers as $offer) {
            if ($offer['aircraft_type'] === $aircraftType
                && $offer['aircraft_registration'] === $aircraftRegistration
                && (float) $offer['offered_price'] === $offeredPrice) {
                return $offer['itinerary'];
            }
        }

        return $offers[0]['itinerary'] ?? null;
    }

    /**
     * Decode HTML entities (numeric — &#10;, &#13;, &#9; — and named —
     * &amp;, &gt; — all handled by html_entity_decode itself) and collapse
     * line endings, once, up front. Everything downstream — including
     * operator names — is parsed from this normalized text, which is what
     * makes entity decoding apply everywhere without special-casing it per
     * field.
     */
    private function normalize(string $body): string
    {
        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_replace(["\r\n", "\r"], "\n", $decoded);
    }

    /**
     * Lines between an exact `$start` line and an exact `$end` line
     * (both exclusive), trimmed, blanks dropped. Used for the "Aircraft"
     * section, which is always bounded this way.
     *
     * @return list<string>
     */
    private function extractSection(string $body, string $start, string $end): array
    {
        $pattern = '/^'.preg_quote($start, '/').'[ \t]*$\n(.*?)^'.preg_quote($end, '/').'[ \t]*$/ms';

        if (! preg_match($pattern, $body, $m)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $m[1])), fn ($line) => $line !== ''));
    }

    /**
     * Parse every line of the "Aircraft" section into its status, price,
     * type, registration and (when present) inline operator. Non-ACCEPTED
     * lines are still parsed (not just skipped) so callers can tell the
     * difference between "this line didn't match the format" and "this
     * line was a decline" if that's ever useful — parse() itself only
     * keeps the ACCEPTED ones.
     *
     * Two shapes appear in real mail:
     *   "ACCEPTED 32,070 EUR Legacy 650 () (Operated by: VistaJet GmbH)"  — floating fleet
     *   "ACCEPTED 33,000 EUR Challenger 604, D-ANGB"                     — tail assigned
     * DECLINED lines often have no price at all.
     *
     * @param  list<string>  $lines
     * @return list<array{status: string, price: ?float, currency: ?string, type: string, registration: ?string, operated_by: ?string}>
     */
    private function parseAircraftLines(array $lines): array
    {
        // Two mutually exclusive shapes for what follows the type name, so
        // they're two branches rather than one pattern with an optional
        // leading space — a floating-fleet line has a space before "()"
        // ("Legacy 650 ()"), but a tail-assigned line has none before its
        // comma ("Challenger 604, D-ANGB").
        $pattern = '/^(?<status>ACCEPTED|DECLINED|UNANSWERED)\s+'
            .'(?:(?<price>[\d,]+)\s+(?<currency>[A-Z]{3})\s+)?'
            .'(?:'
            .'(?<type_a>.+?)\s+\(\)(?:\s*\(Operated by:\s*(?<operated_by>[^)]+)\))?'
            .'|'
            .'(?<type_b>.+?),\s*(?<registration>\S+)'
            .')'
            .'\s*$/';

        $parsed = [];

        foreach ($lines as $line) {
            if (! preg_match($pattern, $line, $m, PREG_UNMATCHED_AS_NULL)) {
                continue;
            }

            $parsed[] = [
                'status' => $m['status'],
                'price' => $m['price'] !== null ? $this->parseAmount($m['price']) : null,
                'currency' => $m['currency'],
                'type' => trim($m['type_a'] ?? $m['type_b']),
                'registration' => $m['registration'] !== null ? trim($m['registration']) : null,
                'operated_by' => $m['operated_by'] !== null ? trim($m['operated_by']) : null,
            ];
        }

        return $parsed;
    }

    /**
     * "32,070" -> 32070.0
     */
    private function parseAmount(string $amount): float
    {
        return (float) str_replace(',', '', $amount);
    }

    /**
     * Fallback operator when neither the aircraft line nor its detail block
     * names one. The "Seller" line is either a bare company name
     * ("VistaJet") or "Person Name, Company Name" ("Vincent Heiliger,
     * Platoon Aviation GmbH & Co. KG") — only the company half is useful
     * here, so the first comma splits person from company when present.
     */
    private function extractSellerOperator(string $body): ?string
    {
        if (! preg_match('/^Seller[ \t]*$\n[ \t]*\n?[ \t]*(.+)$/m', $body, $m)) {
            return null;
        }

        $line = trim($m[1]);

        if ($line === '') {
            return null;
        }

        if (str_contains($line, ',')) {
            [, $company] = explode(',', $line, 2);

            return trim($company);
        }

        return $line;
    }

    /**
     * The client's originally-requested itinerary, always present as a
     * single line directly under an "Itinerary" heading:
     *   "04 Aug 2026 18:00 LFMN Nice, FR - LATI Tirana, AL 4 PAX"
     *
     * @return array{date: ?string, time: ?string, departure_airport: ?string, arrival_airport: ?string, departure_icao: ?string, arrival_icao: ?string, pax: ?int}
     */
    private function extractSimpleItinerary(string $body): array
    {
        $empty = [
            'date' => null,
            'time' => null,
            'departure_airport' => null,
            'arrival_airport' => null,
            'departure_icao' => null,
            'arrival_icao' => null,
            'pax' => null,
        ];

        $lines = $this->extractSection($body, 'Itinerary', 'Aircraft');

        if ($lines === []) {
            return $empty;
        }

        $pattern = '/^(?<date>\d{2} \w{3} \d{4})\s+(?<time>\d{2}:\d{2})\s+(?<dep_icao>\S+)\s+(?<dep_city>.+?)\s+-\s+(?<arr_icao>\S+)\s+(?<arr_city>.+?)\s+(?<pax>\d+)\s*PAX\s*$/';

        if (! preg_match($pattern, $lines[0], $m)) {
            return $empty;
        }

        return [
            'date' => $m['date'],
            'time' => $m['time'],
            'departure_airport' => trim($m['dep_icao'].' '.$m['dep_city']),
            'arrival_airport' => trim($m['arr_icao'].' '.$m['arr_city']),
            'departure_icao' => $m['dep_icao'],
            'arrival_icao' => $m['arr_icao'],
            'pax' => (int) $m['pax'],
        ];
    }

    /**
     * Each accepted (or, in principle, any) offer can have its own detail
     * block further down the email: a repeated "<type>[, <reg>] <price>
     * <currency>" header line, followed (in any order, with blank lines
     * between) by "Request ID:", "Year of make:", "Max PAX:", an optional
     * standalone "Operated by:" line, and — when the seller quoted a
     * specific schedule — a table row with both local and UTC times plus
     * distance. Not every accepted offer has one of these (a floating-fleet
     * decline-heavy email may have nothing past the Aircraft/Seller
     * sections at all — see sample-1's fixture).
     *
     * @return list<array{type: string, registration: ?string, price: ?float, currency: ?string, request_id: ?string, year_of_make: ?string, max_pax: ?int, operated_by: ?string, departure_date: ?string, departure_time: ?string, arrival_time: ?string, distance_nm: ?int, flight_duration: ?string}>
     */
    private function extractDetailBlocks(string $body): array
    {
        $lines = explode("\n", $body);
        // Same two-shape distinction as parseAircraftLines()'s pattern: a
        // floating-fleet header repeats the "()" marker ("Legacy 650 ()
        // 32,070 EUR"), a tail-assigned one has a bare comma+registration
        // ("Challenger 604, D-ANGB 33,000 EUR"). Without explicitly
        // matching "()" here it gets swallowed into the lazy type group
        // instead of being dropped, producing "Legacy 650 ()" instead of
        // "Legacy 650" and silently breaking the match against the
        // aircraft-line's type.
        $headerPattern = '/^(?<type>.+?)(?:\s*\(\)|,\s*(?<registration>\S+))?\s+(?<price>[\d,]+)\s+(?<currency>[A-Z]{3})\s*$/';
        $itineraryRowPattern = '/^(?<date>\d{2} \w{3} \d{4})\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\d+\s+'
            .'(?<dep_local>\d{2}:\d{2})\s*\(\d{2}:\d{2}\s*UTC\)\s+'
            .'(?<arr_local>\d{2}:\d{2})\s*\(\d{2}:\d{2}\s*UTC\)\s+'
            .'(?<duration>\d{2}:\d{2})\s+(?<nm>\d+)\s*$/';

        $requestIdIndexes = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^Request ID:\s*(\d+)\s*$/', trim($line))) {
                $requestIdIndexes[] = $i;
            }
        }

        $blocks = [];

        foreach ($requestIdIndexes as $n => $index) {
            preg_match('/^Request ID:\s*(?<id>\d+)\s*$/', trim($lines[$index]), $idMatch);

            $block = [
                'type' => null,
                'registration' => null,
                'price' => null,
                'currency' => null,
                'request_id' => $idMatch['id'],
                'year_of_make' => null,
                'max_pax' => null,
                'operated_by' => null,
                'departure_date' => null,
                'departure_time' => null,
                'arrival_time' => null,
                'distance_nm' => null,
                'flight_duration' => null,
            ];

            // Scan upward for this block's repeated header line.
            for ($j = $index - 1; $j >= max(0, $index - 15); $j--) {
                $candidate = trim($lines[$j]);

                if ($candidate === '') {
                    continue;
                }

                if (preg_match($headerPattern, $candidate, $hm)) {
                    $block['type'] = trim($hm['type']);
                    $block['registration'] = ($hm['registration'] ?? '') !== '' ? trim($hm['registration']) : null;
                    $block['price'] = $this->parseAmount($hm['price']);
                    $block['currency'] = $hm['currency'];

                    break;
                }
            }

            // Scan downward, stopping at "Created" or the next block's
            // Request ID, collecting whichever detail fields are present.
            $stop = $requestIdIndexes[$n + 1] ?? count($lines);

            for ($j = $index + 1; $j < $stop; $j++) {
                $candidate = trim($lines[$j]);

                if ($candidate === 'Created') {
                    break;
                }

                if (preg_match('/^Year of make:\s*(.+)$/', $candidate, $ym)) {
                    $block['year_of_make'] = trim($ym[1]);
                } elseif (preg_match('/^Max PAX:\s*(\d+)/', $candidate, $pm)) {
                    $block['max_pax'] = (int) $pm[1];
                } elseif (preg_match('/^Operated by:\s*(.+)$/', $candidate, $om)) {
                    $block['operated_by'] = trim($om[1]);
                } elseif (preg_match($itineraryRowPattern, $candidate, $rm)) {
                    $block['departure_date'] = $rm['date'];
                    $block['departure_time'] = $rm['dep_local'];
                    $block['arrival_time'] = $rm['arr_local'];
                    $block['flight_duration'] = $rm['duration'];
                    $block['distance_nm'] = (int) $rm['nm'];
                }
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Match an ACCEPTED aircraft-line entry to its detail block, when it
     * has one. Registration is the strongest signal when present; floating
     * fleet offers (no registration on either side) fall back to matching
     * on type + price, which is enough to disambiguate multiple floating
     * offers of different types or prices in the same email.
     *
     * @param  array{type: string, registration: ?string, price: ?float, currency: ?string}  $line
     * @param  list<array<string, mixed>>  $blocks
     */
    private function matchDetailBlock(array $line, array $blocks): ?array
    {
        foreach ($blocks as $block) {
            if ($block['type'] !== $line['type']) {
                continue;
            }

            if ($line['registration'] !== null || $block['registration'] !== null) {
                if ($block['registration'] === $line['registration']) {
                    return $block;
                }

                continue;
            }

            if ($block['price'] === $line['price']) {
                return $block;
            }
        }

        return null;
    }
}
