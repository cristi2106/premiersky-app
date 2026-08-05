<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Services\QuoteEmailSearcher;
use App\Services\QuoteOfferImporter;
use App\Services\QuoteOfferPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

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
     */
    public function index(
        Request $request,
        QuoteEmailSearcher $searcher,
        QuoteOfferImporter $importer,
        QuoteOfferPresenter $presenter
    ): Response {
        $tripId = trim((string) $request->query('trip_id', ''));
        $emails = [];
        $totalMatches = 0;
        $truncated = false;
        $searchScope = null;
        $searchError = null;
        $offers = [];

        if ($tripId !== '') {
            try {
                $result = $searcher->search($tripId);
                $emails = $result['emails'];
                $totalMatches = $result['total_matches'];
                $truncated = $result['truncated'];
                $searchScope = $result['search_scope'];

                $quoteRequest = QuoteRequest::firstOrCreate(['avinode_trip_id' => $tripId]);
                $importer->importFromEmails($quoteRequest, $emails);

                $offerModels = $quoteRequest->offers()->with('tail')->orderBy('offered_price')->get();

                if ($offerModels->isNotEmpty() && $quoteRequest->status !== 'offers_received') {
                    $quoteRequest->update(['status' => 'offers_received']);
                }

                $offers = $offerModels->map(fn ($offer) => $presenter->present($offer))->all();
            } catch (\Throwable $e) {
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
            'offers' => $offers,
        ]);
    }
}
