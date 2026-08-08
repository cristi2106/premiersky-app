<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\QuoteEmailSearcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Covers the Quotes page's search-history list added on top of
 * QuoteController::index — the mailbox itself is never touched here
 * (QuoteEmailSearcher is mocked), since these tests are only about how
 * history is built and when a pull is (or isn't) triggered.
 */
class QuoteHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    /**
     * search() should never be called at all when no trip_id is given —
     * this is just the plain history-list page load.
     */
    private function mockSearcherNeverCalled(): void
    {
        $mock = Mockery::mock(QuoteEmailSearcher::class);
        $mock->shouldNotReceive('search');
        $this->app->instance(QuoteEmailSearcher::class, $mock);
    }

    private function mockSearcherReturning(string $tripId, array $emails): void
    {
        $mock = Mockery::mock(QuoteEmailSearcher::class);
        $mock->shouldReceive('search')
            ->once()
            ->with($tripId)
            ->andReturn([
                'emails' => $emails,
                'total_matches' => count($emails),
                'truncated' => false,
                'search_scope' => 'subject',
            ]);
        $this->app->instance(QuoteEmailSearcher::class, $mock);
    }

    public function test_history_lists_every_quote_request_most_recent_first(): void
    {
        $this->mockSearcherNeverCalled();

        $older = QuoteRequest::create(['avinode_trip_id' => 'OLDONE', 'status' => 'pending']);
        $older->created_at = now()->subDay();
        $older->save();

        $newer = QuoteRequest::create(['avinode_trip_id' => 'NEWONE', 'status' => 'offers_received']);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Quotes/Index')
            ->has('history', 2)
            ->where('history.0.avinode_trip_id', 'NEWONE')
            ->where('history.1.avinode_trip_id', 'OLDONE')
        );
    }

    /**
     * The history row's schedule column shows date, departure, and
     * arrival (time + ICAO on both ends) — the arrival side specifically
     * requires an offer with a detail block, which is where an arrival
     * time actually lives (see resolveSchedule()'s doc comment).
     */
    public function test_history_shows_full_schedule_when_an_offer_has_a_detail_block(): void
    {
        $this->mockSearcherNeverCalled();

        $quoteRequest = QuoteRequest::create(['avinode_trip_id' => 'SCHED1', 'status' => 'offers_received']);
        $quoteRequest->offers()->create([
            'operator_name' => 'EGT JET LTD.',
            'aircraft_type' => 'Challenger 605',
            'aircraft_registration' => 'LZ-VPI',
            'offered_price' => 37000,
            'offered_currency' => 'EUR',
            'raw_email_body' => file_get_contents(
                __DIR__.'/../fixtures/avinode-emails/sample-3-egt-existing-tail.txt'
            ),
        ]);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('history.0.schedule.date', '04 Aug 2026')
            ->where('history.0.schedule.departure_time', '18:00')
            ->where('history.0.schedule.departure_icao', 'LFMN')
            ->where('history.0.schedule.arrival_time', '19:35')
            ->where('history.0.schedule.arrival_icao', 'LATI')
        );
    }

    /**
     * A trip with offers but none of them quoting an arrival time still
     * shows the departure side rather than nothing at all.
     */
    public function test_history_falls_back_to_departure_only_when_no_offer_has_an_arrival_time(): void
    {
        $this->mockSearcherNeverCalled();

        $quoteRequest = QuoteRequest::create(['avinode_trip_id' => 'SCHED2', 'status' => 'offers_received']);
        $quoteRequest->offers()->create([
            'operator_name' => 'VistaJet GmbH',
            'aircraft_type' => 'Legacy 650',
            'offered_price' => 32070,
            'offered_currency' => 'EUR',
            'raw_email_body' => file_get_contents(
                __DIR__.'/../fixtures/avinode-emails/sample-1-vistajet-floating-fleet.txt'
            ),
        ]);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('history.0.schedule.departure_icao', 'LFMN')
            ->where('history.0.schedule.arrival_time', null)
        );
    }

    /**
     * A trip with no offers yet (still pending) has no schedule to show.
     */
    public function test_history_schedule_is_null_when_the_trip_has_no_offers(): void
    {
        $this->mockSearcherNeverCalled();

        QuoteRequest::create(['avinode_trip_id' => 'SCHED3', 'status' => 'pending']);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertInertia(fn ($page) => $page->where('history.0.schedule', null));
    }

    public function test_history_shows_offers_count_and_status(): void
    {
        $this->mockSearcherNeverCalled();

        $quoteRequest = QuoteRequest::create(['avinode_trip_id' => 'ABC123', 'status' => 'offers_received']);
        $quoteRequest->offers()->create([
            'operator_name' => 'Test Air',
            'aircraft_type' => 'Challenger 604',
            'offered_price' => 10000,
            'offered_currency' => 'EUR',
            'raw_email_body' => '',
        ]);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('history.0.offers_count', 1)
            ->where('history.0.status', 'offers_received')
        );
    }

    /**
     * Clicking a trip ID from history (view=1) loads whatever's already
     * stored without hitting the mailbox at all.
     */
    public function test_viewing_an_existing_trip_from_history_does_not_pull_the_mailbox(): void
    {
        $this->mockSearcherNeverCalled();

        $quoteRequest = QuoteRequest::create(['avinode_trip_id' => 'NOPULL1', 'status' => 'offers_received']);
        $quoteRequest->offers()->create([
            'operator_name' => 'Test Air',
            'aircraft_type' => 'Challenger 604',
            'offered_price' => 10000,
            'offered_currency' => 'EUR',
            'raw_email_body' => '',
        ]);

        $response = $this->actingAs($this->actingUser())
            ->get(route('quotes.index', ['trip_id' => 'NOPULL1', 'view' => 1]));

        $response->assertInertia(fn ($page) => $page
            ->where('pulled', false)
            ->has('offers', 1)
        );
    }

    /**
     * The history list's "Refresh" button (trip_id with no view flag) runs
     * a normal live pull, same as the main search form.
     */
    public function test_refreshing_an_existing_trip_pulls_the_mailbox_again(): void
    {
        QuoteRequest::create(['avinode_trip_id' => 'PULLME1', 'status' => 'pending']);

        $this->mockSearcherReturning('PULLME1', [
            [
                'from' => 'Avinode <system@avinode.com>',
                'subject' => 'Accepted: PULLME1',
                'date' => null,
                'body' => "Aircraft\nACCEPTED 10,000 EUR Challenger 604, D-TEST\n\nSeller\nTest Air\n",
            ],
        ]);

        $response = $this->actingAs($this->actingUser())
            ->get(route('quotes.index', ['trip_id' => 'PULLME1']));

        $response->assertInertia(fn ($page) => $page
            ->where('pulled', true)
            ->has('offers', 1)
        );
    }

    /**
     * A trip ID with no history row yet always pulls, even if `view=1` is
     * somehow passed — there's nothing stored to fall back to showing.
     */
    public function test_a_brand_new_trip_id_pulls_even_with_the_view_flag(): void
    {
        $this->mockSearcherReturning('BRANDNEW', []);

        $response = $this->actingAs($this->actingUser())
            ->get(route('quotes.index', ['trip_id' => 'BRANDNEW', 'view' => 1]));

        $response->assertInertia(fn ($page) => $page->where('pulled', true));

        $this->assertDatabaseHas('quote_requests', ['avinode_trip_id' => 'BRANDNEW']);
    }

    /**
     * A lower/mixed-case trip ID typed into the search form is normalized
     * to uppercase before it's ever used — both what's searched/stored and
     * what's echoed back as the current trip ID.
     */
    public function test_a_new_trip_id_is_normalized_to_uppercase_on_first_search(): void
    {
        $this->mockSearcherReturning('MIXEDUP', []);

        $response = $this->actingAs($this->actingUser())
            ->get(route('quotes.index', ['trip_id' => 'MixedUp']));

        $response->assertInertia(fn ($page) => $page->where('tripId', 'MIXEDUP'));

        $this->assertDatabaseHas('quote_requests', ['avinode_trip_id' => 'MIXEDUP']);
        $this->assertDatabaseMissing('quote_requests', ['avinode_trip_id' => 'MixedUp']);
    }

    /**
     * Searching a trip ID already on file under different casing reuses
     * that row instead of creating a case-variant duplicate — the actual
     * bug this whole normalization pass fixes.
     */
    public function test_searching_an_existing_trip_under_different_casing_reuses_the_same_row(): void
    {
        $existing = QuoteRequest::create(['avinode_trip_id' => 'REUSE1', 'status' => 'pending']);

        $this->mockSearcherReturning('REUSE1', []);

        $response = $this->actingAs($this->actingUser())
            ->get(route('quotes.index', ['trip_id' => 'reuse1']));

        $response->assertInertia(fn ($page) => $page->where('quoteRequest.id', $existing->id));

        $this->assertSame(1, QuoteRequest::count());
    }

    /**
     * Legacy case-variant duplicates (rows created before this
     * normalization existed) collapse to a single history entry — the
     * one with more offers imported wins, and "first searched" reflects
     * whichever of the two rows is actually older.
     */
    public function test_history_collapses_legacy_case_variant_duplicates_into_one_row(): void
    {
        $this->mockSearcherNeverCalled();

        $older = QuoteRequest::create(['avinode_trip_id' => 'DUPE1', 'status' => 'pending']);
        $older->created_at = now()->subDay();
        $older->save();

        $newer = QuoteRequest::create(['avinode_trip_id' => 'dupe1', 'status' => 'offers_received']);
        $newer->offers()->create([
            'operator_name' => 'Test Air',
            'aircraft_type' => 'Challenger 604',
            'offered_price' => 10000,
            'offered_currency' => 'EUR',
            'raw_email_body' => '',
        ]);

        $response = $this->actingAs($this->actingUser())->get(route('quotes.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('history', 1)
            ->where('history.0.avinode_trip_id', 'DUPE1')
            ->where('history.0.id', $newer->id) // more offers wins
            ->where('history.0.offers_count', 1)
            ->where('history.0.status', 'offers_received')
            ->where('history.0.first_searched_at', $older->created_at->toIso8601String())
        );
    }
}
