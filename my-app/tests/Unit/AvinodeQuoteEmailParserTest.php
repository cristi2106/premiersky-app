<?php

namespace Tests\Unit;

use App\Services\AvinodeQuoteEmailParser;
use PHPUnit\Framework\TestCase;

class AvinodeQuoteEmailParserTest extends TestCase
{
    private AvinodeQuoteEmailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new AvinodeQuoteEmailParser();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__.'/../fixtures/avinode-emails/'.$name);
    }

    public function test_sample_1_finds_the_single_accepted_offer_buried_among_declines(): void
    {
        $result = $this->parser->parse($this->fixture('sample-1-vistajet-floating-fleet.txt'));

        // 4 DECLINED lines sit alongside the 1 ACCEPTED line in this email —
        // only the accepted one should ever become an offer.
        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];

        $this->assertSame('Legacy 650', $offer['aircraft_type']);
        $this->assertNull($offer['aircraft_registration']); // floating fleet
        $this->assertSame(32070.0, $offer['offered_price']);
        $this->assertSame('EUR', $offer['offered_currency']);
        // Inline "(Operated by: VistaJet GmbH)" on the accepted line itself.
        $this->assertSame('VistaJet GmbH', $offer['operator_name']);

        // No detail block exists at all in this email — bonus fields must
        // degrade to null rather than erroring.
        $this->assertNull($offer['avinode_request_id']);
        $this->assertNull($offer['year_of_make']);
        $this->assertNull($offer['max_pax']);
        $this->assertNull($offer['distance_nm']);
        $this->assertNull($offer['flight_duration']);
    }

    public function test_sample_2_subject_says_message_but_body_has_a_full_accepted_offer(): void
    {
        // This fixture's subject is "Message for Trip 6JPEE9" — no
        // "Accepted" anywhere in it. The parser never looks at the
        // subject, so that shouldn't matter.
        $result = $this->parser->parse($this->fixture('sample-2-mhs-full-detail.txt'));

        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];

        $this->assertSame('166279215', $offer['avinode_request_id']);
        // No "Operated by:" anywhere in this email -> falls back to the
        // bare (no comma) Seller line.
        $this->assertSame('MHS Aviation GmbH', $offer['operator_name']);
        $this->assertSame('Challenger 604', $offer['aircraft_type']);
        $this->assertSame('D-ANGB', $offer['aircraft_registration']);
        $this->assertSame(33000.0, $offer['offered_price']);
        $this->assertSame('EUR', $offer['offered_currency']);
        $this->assertSame('2002', $offer['year_of_make']);
        $this->assertSame(8, $offer['max_pax']);
        $this->assertSame(569, $offer['distance_nm']);
        $this->assertSame('01:45', $offer['flight_duration']);

        // Local times only — the raw body also contains "(16:00 UTC)" and
        // "(17:45 UTC)" right next to these, which must never surface.
        $this->assertSame('18:00', $offer['itinerary']['departure_time']);
        $this->assertSame('19:45', $offer['itinerary']['arrival_time']);
        $this->assertStringNotContainsString('UTC', $offer['itinerary']['departure_time']);
        $this->assertStringNotContainsString('UTC', $offer['itinerary']['arrival_time']);
    }

    public function test_sample_3_registration_is_captured_for_tails_matching(): void
    {
        $result = $this->parser->parse($this->fixture('sample-3-egt-existing-tail.txt'));

        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];

        $this->assertSame('166279217', $offer['avinode_request_id']);
        $this->assertSame('Challenger 605', $offer['aircraft_type']);
        $this->assertSame('LZ-VPI', $offer['aircraft_registration']);
        $this->assertSame(37000.0, $offer['offered_price']);
        // Seller is "Simona Cankova, EGT JET LTD." — only the company half,
        // after the first comma, should be kept.
        $this->assertSame('EGT JET LTD.', $offer['operator_name']);
        $this->assertSame('2008', $offer['year_of_make']);
        $this->assertSame(9, $offer['max_pax']);
        $this->assertSame(258, $offer['distance_nm']);
        $this->assertSame('01:35', $offer['flight_duration']);
    }

    public function test_sample_4_skips_declined_and_unanswered_keeping_only_the_accepted_line(): void
    {
        $result = $this->parser->parse($this->fixture('sample-4-vistajet-unanswered-and-range-year.txt'));

        // 2 DECLINED + 1 UNANSWERED (which even carries its own price,
        // 24,500 USD — a real trap for "skip if no price" logic) + 1
        // ACCEPTED. Only the accepted one should survive.
        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];

        $this->assertSame('Legacy 650', $offer['aircraft_type']);
        $this->assertSame(32070.0, $offer['offered_price']);
        $this->assertSame('EUR', $offer['offered_currency']);
        $this->assertNotSame(24500.0, $offer['offered_price']);
        $this->assertNotSame('USD', $offer['offered_currency']);

        // A year *range*, stored verbatim as a string, not coerced to int.
        $this->assertIsString($offer['year_of_make']);
        $this->assertSame('2011–2020', $offer['year_of_make']);

        $this->assertSame('166278699', $offer['avinode_request_id']);
        $this->assertSame(13, $offer['max_pax']);

        // This offer's own detail block quotes 18:30/20:00 — a different,
        // more specific schedule than the top-level "Itinerary" line's
        // requested 18:00. The detail block should win for the offer.
        $this->assertSame('18:30', $offer['itinerary']['departure_time']);
        $this->assertSame('20:00', $offer['itinerary']['arrival_time']);
    }

    public function test_sample_5_decodes_html_entities_in_the_operator_name(): void
    {
        $result = $this->parser->parse($this->fixture('sample-5-platoon-entity-in-name.txt'));

        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];

        // Raw source has "Platoon Aviation GmbH &amp; Co. KG" (Seller
        // fallback — no "Operated by:" anywhere in this email either).
        $this->assertSame('Platoon Aviation GmbH & Co. KG', $offer['operator_name']);
        $this->assertStringNotContainsString('&amp;', $offer['operator_name']);

        $this->assertSame('PLATOON PC-24', $offer['aircraft_type']);
        $this->assertNull($offer['aircraft_registration']);
        $this->assertSame(19950.0, $offer['offered_price']);
        $this->assertSame('166278692', $offer['avinode_request_id']);
        $this->assertSame('2019–2026', $offer['year_of_make']);
        $this->assertSame(8, $offer['max_pax']);
        $this->assertSame(568, $offer['distance_nm']);
        $this->assertSame('01:40', $offer['flight_duration']);
    }

    public function test_no_accepted_lines_returns_no_offers(): void
    {
        $body = <<<'EMAIL'
Trip 6JPEE9
Some Operator

Buyer
Someone, Premier Sky

Message
No luck this time.

Trip
Nice, FR - Tirana, AL

Itinerary
04 Aug 2026 18:00 LFMN Nice, FR - LATI Tirana, AL 4 PAX

Aircraft
DECLINED Challenger 605 () (Operated by: Some Operator)
UNANSWERED 10,000 EUR Citation XLS () (Operated by: Some Operator)

Seller
Some Operator
EMAIL;

        $result = $this->parser->parse($body);

        $this->assertSame([], $result['offers']);
    }
}
