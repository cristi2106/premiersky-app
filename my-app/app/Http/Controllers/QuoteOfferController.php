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
     * Sets (or clears) an offer's commission and recomputes final_price
     * server-side — the client shows a live preview as you type, but the
     * stored, authoritative number is always calculated here, not trusted
     * from the request.
     *
     * Plain JSON, not an Inertia response: this is called from the Quotes
     * page's commission inputs and only needs to patch one offer in the
     * page's already-loaded list, not re-render/replace the whole page
     * (which would also mean re-deciding what an Inertia partial reload
     * should and shouldn't touch on a route that isn't quotes.index).
     */
    public function update(Request $request, QuoteOffer $quoteOffer, QuoteOfferPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'commission_type' => ['nullable', Rule::in(QuoteOffer::COMMISSION_TYPES)],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quoteOffer->fill($data);
        $quoteOffer->final_price = $quoteOffer->calculateFinalPrice();
        $quoteOffer->save();

        return response()->json([
            'offer' => $presenter->present($quoteOffer->fresh('tail')),
        ]);
    }
}
