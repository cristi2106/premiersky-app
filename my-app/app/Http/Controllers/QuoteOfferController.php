<?php

namespace App\Http\Controllers;

use App\Models\QuoteOffer;
use App\Services\QuoteOfferPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuoteOfferController extends Controller
{
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
}
