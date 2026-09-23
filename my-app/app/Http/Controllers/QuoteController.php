<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Services\AvinodeQuoteEmailParser;
use App\Services\QuoteEmailSearcher;
use App\Services\QuoteOfferImporter;
use App\Services\QuoteOfferPresenter;
use App\Services\TripScheduleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QuoteController extends Controller
{
    /**
     * The Quotes module: paste an Avinode trip ID, pull every matching
     * email from the mailbox, parse whichever ones have an ACCEPTED
     * aircraft line into quote_offers, and show both — the structured
     * offers (the main event) and the raw matched emails underneath (kept
     * for reference/audit, and because not every match necessarily has an
     * accepted offer — a declines-only email still matched the search but
     * legitimately produces zero offers).
     *
     * A quote can also be opened directly by its own id (`quote_request_id`)
     * rather than by trip_id — the only way to open a manually-created
     * quote (see QuoteRequestController::store()), which has no
     * avinode_trip_id to resolve or search the mailbox by at all, and also
     * how the search history list now opens every row, manual or not (see
     * Quotes/Index.vue's viewHistoryItem()) — it never needs the
     * case-variant-duplicate resolution resolveQuoteRequest() exists for,
     * since a history row's own id already *is* whichever row that
     * resolution would have picked (see searchHistory()'s own "canonical"
     * doc comment). "Refresh" still goes through trip_id below, since only
     * a real trip id can be searched against the mailbox at all.
     */
    public function index(
        Request $request,
        QuoteEmailSearcher $searcher,
        QuoteOfferImporter $importer,
        QuoteOfferPresenter $presenter,
        AvinodeQuoteEmailParser $parser,
        TripScheduleResolver $scheduleResolver
    ): Response {
        // Uppercased once, up front, so every use below — searching,
        // resolving/creating the QuoteRequest, and the value sent back to
        // the page — agrees on one casing. Avinode trip IDs are otherwise
        // case-insensitive (the mailbox search already treats them that
        // way — see QuoteEmailSearcher), so without this, searching the
        // same trip under different casing used to create a separate
        // duplicate QuoteRequest each time. See resolveQuoteRequest().
        $tripId = strtoupper(trim((string) $request->query('trip_id', '')));
        $quoteRequestId = $request->query('quote_request_id');
        // Set by the search-history list's trip ID link: load whatever's
        // already stored for that trip without hitting the mailbox again.
        // The history list's own "Refresh" button, and the main search
        // form above, both omit this flag to run a normal live pull.
        $viewOnly = $request->boolean('view');
        $emails = [];
        $totalMatches = 0;
        $truncated = false;
        $searchScope = null;
        $searchError = null;
        $pulled = false;
        $offers = [];
        $quoteRequestData = null;

        if ($quoteRequestId !== null) {
            $quoteRequest = QuoteRequest::find($quoteRequestId);

            if ($quoteRequest !== null) {
                $tripId = strtoupper((string) $quoteRequest->avinode_trip_id);
                [$offers, $quoteRequestData] = $this->presentQuote($quoteRequest, $presenter, $scheduleResolver);
            }
        } elseif ($tripId !== '') {
            try {
                $quoteRequest = $this->resolveQuoteRequest($tripId);

                // A trip ID with no history yet always pulls regardless of
                // the view flag — there's nothing stored to show otherwise.
                $pulled = ! $viewOnly || $quoteRequest->wasRecentlyCreated;

                if ($pulled) {
                    $result = $searcher->search($tripId);
                    $emails = $result['emails'];
                    $totalMatches = $result['total_matches'];
                    $truncated = $result['truncated'];
                    $searchScope = $result['search_scope'];

                    $importer->importFromEmails($quoteRequest, $emails);
                }

                [$offers, $quoteRequestData] = $this->presentQuote($quoteRequest, $presenter, $scheduleResolver);
            } catch (Throwable $e) {
                Log::error('quotes: email pull failed', [
                    'trip_id' => $tripId,
                    'message' => $e->getMessage(),
                ]);

                $searchError = 'Could not reach the mailbox. Check the IMAP settings and try again.';
            }
        }

        return Inertia::render('Quotes/Index', [
            'tripId' => $tripId,
            'emails' => $emails,
            'totalMatches' => $totalMatches,
            'truncated' => $truncated,
            'searchScope' => $searchScope,
            'searchError' => $searchError,
            'pulled' => $pulled,
            'offers' => $offers,
            'quoteRequest' => $quoteRequestData,
            'history' => $this->searchHistory($parser, $scheduleResolver),
            // Set only when QuoteOfferController::generateContract() bails
            // out before creating anything (no confident schedule to build
            // a leg from) and redirects back here — see that method's own
            // doc comment for why that specific case can't just land on
            // the Contract edit page like everything else.
            'contractError' => session('contractError'),
        ]);
    }

    /**
     * Loads a QuoteRequest's offers and shapes the page's `quoteRequest`
     * prop — shared by both ways index() can end up with one: a trip_id
     * pull/view and a direct quote_request_id load. Also applies the
     * "at least one offer means offers received" status flip, which
     * applies identically either way (an offer is an offer, whichever
     * page action added it).
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private function presentQuote(
        QuoteRequest $quoteRequest,
        QuoteOfferPresenter $presenter,
        TripScheduleResolver $scheduleResolver
    ): array {
        $offerModels = $quoteRequest->offers()->with('tail')->orderBy('offered_price')->get();

        if ($offerModels->isNotEmpty() && $quoteRequest->status !== 'offers_received') {
            $quoteRequest->update(['status' => 'offers_received']);
        }

        $offers = $offerModels->map(fn ($offer) => $presenter->present($offer))->all();

        $quoteRequest->load(['client', 'legs.departureAirport', 'legs.arrivalAirport']);

        $quoteRequestData = [
            'id' => $quoteRequest->id,
            'avinode_trip_id' => $quoteRequest->avinode_trip_id,
            'reference_label' => $quoteRequest->reference_label,
            'client' => $quoteRequest->client ? [
                'id' => $quoteRequest->client->id,
                'company_name' => $quoteRequest->client->company_name,
            ] : null,
            'schedule' => $quoteRequest->legs->isNotEmpty()
                ? $scheduleResolver->summarizeLegs($quoteRequest->legs)
                : $scheduleResolver->resolve($offers),
        ];

        return [$offers, $quoteRequestData];
    }

    /**
     * Finds the QuoteRequest for this (already-uppercased) trip ID,
     * matched case-insensitively against whatever's stored — so "6jpee9"
     * and "6JPEE9" always resolve to the same record — creating one only
     * if none exists yet. New rows are created with $tripId as given
     * (already normalized by the caller), so once every legacy duplicate
     * below is resolved, this reduces to a plain unique lookup.
     *
     * A handful of QuoteRequest rows from before this normalization
     * existed are still on file as genuine case-variant duplicates of
     * each other (e.g. "6JPEE9" and "6jpee9") — some with real, diverged
     * work on both copies (different commissions chosen, even a
     * quotation PDF already issued from each), so they can't be silently
     * merged here. Until they're resolved, more than one row can still
     * match; the one with the most imported offers wins (freshest data
     * as tie-break) — searchHistory() below picks the same way, so a
     * history row and the record clicking it resolves to always describe
     * the same data.
     *
     * Never called for a manually-created quote (avinode_trip_id null) —
     * those are only ever opened by their own id, never by trip_id (see
     * index()'s own doc comment), so this can stay entirely unaware
     * nullable trip IDs exist at all.
     */
    private function resolveQuoteRequest(string $tripId): QuoteRequest
    {
        $existing = QuoteRequest::whereRaw('UPPER(avinode_trip_id) = ?', [$tripId])
            ->withCount('offers')
            ->orderByDesc('offers_count')
            ->orderByDesc('updated_at')
            ->first();

        return $existing ?? QuoteRequest::create(['avinode_trip_id' => $tripId]);
    }

    /**
     * Every previously searched trip ID and every manually-created quote,
     * most recent first — powers the Quotes page's search-history list,
     * so a trip ID already looked up once (or a quote entered by hand)
     * never has to be remembered or retyped.
     *
     * Grouped case-insensitively by trip ID (rather than trusting one row
     * per trip ID) so any legacy duplicate QuoteRequest rows — see
     * resolveQuoteRequest()'s doc comment — never show up as separate
     * history entries. Each group collapses to whichever row has the
     * most offers imported (freshest data as tie-break), the same pick
     * resolveQuoteRequest() itself makes, so clicking a trip ID here
     * always lands on the exact record this list described. A
     * manually-created quote (avinode_trip_id null) has no case-variant
     * duplicate concept to begin with — see the groupBy key below — so
     * every one of those is always its own singleton group.
     *
     * @return list<array<string, mixed>>
     */
    private function searchHistory(AvinodeQuoteEmailParser $parser, TripScheduleResolver $scheduleResolver): array
    {
        return QuoteRequest::withCount('offers')
            ->with([
                'offers' => fn ($query) => $query->orderBy('offered_price'),
                'legs.departureAirport',
                'legs.arrivalAirport',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->groupBy(fn (QuoteRequest $quoteRequest) => $quoteRequest->avinode_trip_id !== null
                ? strtoupper($quoteRequest->avinode_trip_id)
                : 'manual-'.$quoteRequest->id)
            ->map(function ($group) use ($parser, $scheduleResolver) {
                $canonical = $group->reduce(function (?QuoteRequest $best, QuoteRequest $quoteRequest) {
                    if ($best === null || $quoteRequest->offers_count !== $best->offers_count) {
                        return $best === null || $quoteRequest->offers_count > $best->offers_count
                            ? $quoteRequest
                            : $best;
                    }

                    return $quoteRequest->updated_at->gt($best->updated_at) ? $quoteRequest : $best;
                });

                return [
                    'id' => $canonical->id,
                    'avinode_trip_id' => $canonical->avinode_trip_id !== null
                        ? strtoupper($canonical->avinode_trip_id)
                        : null,
                    // The Quotes/Index.vue history list shows this in the
                    // trip ID's place whenever avinode_trip_id is null —
                    // see QuoteRequestController::store()/generateReferenceLabel().
                    'reference_label' => $canonical->reference_label,
                    // The true first-searched time for this trip ID, even
                    // if that happened on a different (duplicate) row than
                    // the canonical one picked above.
                    'first_searched_at' => $group->min('created_at')->toIso8601String(),
                    'offers_count' => $canonical->offers_count,
                    'status' => $group->contains(fn ($quoteRequest) => $quoteRequest->status === 'offers_received')
                        ? 'offers_received'
                        : 'pending',
                    'schedule' => $this->resolveSchedule($canonical, $parser, $scheduleResolver),
                ];
            })
            ->sortByDesc('first_searched_at')
            ->values()
            ->all();
    }

    /**
     * The trip's schedule for its search-history row — date, departure,
     * and arrival, each as its own field so the page formats them rather
     * than parsing a pre-built string.
     *
     * A manually-created quote (avinode_trip_id null) short-circuits
     * straight to TripScheduleResolver::summarizeLegs() — it has no raw
     * email for AvinodeQuoteEmailParser to pull an itinerary out of at
     * all, only quote_request_legs (see QuoteRequestController::saveLegs()).
     *
     * Otherwise, the date/departure side is available from any offer's
     * raw_email_body (the "Itinerary" section is per email, shared by
     * every offer parsed out of it — see
     * AvinodeQuoteEmailParser::extractSimpleItinerary()), but an arrival
     * time specifically requires that particular offer's own detail block
     * — the top-level Itinerary line never carries one. Offers are
     * checked cheapest-first (matching their display order everywhere
     * else); the first one quoting an arrival time wins, falling back to
     * the first offer's (arrival-time-less) schedule if none of them
     * quoted one, or null if the trip has no offers yet.
     *
     * @return array{date: ?string, departure_time: ?string, departure_icao: ?string, arrival_time: ?string, arrival_icao: ?string}|null
     */
    private function resolveSchedule(
        QuoteRequest $quoteRequest,
        AvinodeQuoteEmailParser $parser,
        TripScheduleResolver $scheduleResolver
    ): ?array {
        if ($quoteRequest->legs->isNotEmpty()) {
            $schedule = $scheduleResolver->summarizeLegs($quoteRequest->legs);

            return [
                'date' => $schedule['departure_date'],
                'departure_time' => $schedule['departure_time'],
                'departure_icao' => $schedule['departure_icao'],
                'arrival_time' => $schedule['arrival_time'],
                'arrival_icao' => $schedule['arrival_icao'],
            ];
        }

        $fallback = null;

        foreach ($quoteRequest->offers as $offer) {
            $itinerary = $parser->findOfferItinerary(
                $offer->raw_email_body,
                $offer->aircraft_type,
                $offer->aircraft_registration,
                (float) $offer->offered_price
            );

            if ($itinerary === null) {
                continue;
            }

            $schedule = [
                'date' => $itinerary['departure_date'],
                'departure_time' => $itinerary['departure_time'],
                'departure_icao' => $itinerary['departure_icao'],
                'arrival_time' => $itinerary['arrival_time'],
                'arrival_icao' => $itinerary['arrival_icao'],
            ];

            if ($schedule['arrival_time'] !== null) {
                return $schedule;
            }

            $fallback ??= $schedule;
        }

        return $fallback;
    }
}
