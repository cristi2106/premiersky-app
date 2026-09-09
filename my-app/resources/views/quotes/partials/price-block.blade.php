{{-- Total Price label + amount. Shared between the two-column layout
     (tucked under the left column's cabin details) and the single-column
     fallback (right-aligned in its own footer row) so the two don't
     drift apart. Both pieces reuse another element's class outright
     rather than a copy of its font-size/weight/color: the label is
     .card-label (same as "Option N"), and the amount is .aircraft-model
     (same as the aircraft type name) — so neither can drift out of sync
     the way they previously had, independently-set. --}}
<div class="card-label price-label">Total Price</div>
<div class="price-amount">
    @if ($amount === null || $currency === null)
        {{-- Defensive backstop only — QuoteRequestController::pdf() already
             blocks generation before this partial is ever reached for a
             selected offer with no price/currency (a manual quote's
             auto-created reference offer starts with both null; see
             QuoteOffer::forTail()). $formatAmount is typed
             (float $amount, string $currency), so either being null would
             otherwise throw a TypeError mid-render — show a plain
             placeholder instead of failing the whole PDF. --}}
        <span class="aircraft-model">Price pending</span>
    @else
        <span class="aircraft-model">{{ $formatAmount($amount, $currency) }}</span>
    @endif
</div>
