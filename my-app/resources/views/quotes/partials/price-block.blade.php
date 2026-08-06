{{-- Total Price label + hero amount. Shared between the two-column layout
     (tucked under the left column's cabin details) and the single-column
     fallback (right-aligned in its own footer row) so the two don't
     drift apart. --}}
<div class="price-label">Total Price</div>
<div class="price-amount"><span class="price-total-hero">{{ $formatAmount($amount, $currency) }}</span></div>
