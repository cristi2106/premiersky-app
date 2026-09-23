<?php
    $optionCount = count($offers);
    $legCount = count($itineraryLegs);
    $formatAmount = fn (float $amount, string $currency) => number_format($amount, 2).' '.$currency;
    $durationLabel = function (int $minutes) {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    };
    $logoPath = resource_path('images/logo.png');
    $logoData = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quoteRequest->quotation_reference }}</title>
    <style>
        {{-- Shared with contracts/pdf.blade.php by design — same design
             system, so most of this is intentionally identical rather than
             reinvented. Only what's specific to a quotation's own content
             (offer option boxes, photos, amenity/cabin chips) is new. --}}
        :root {
            /* Premier Sky brand accent, matched to the logo's wing color —
               same variable contracts/pdf.blade.php defines, needed here
               too now that a manually-created quote's multi-leg Itinerary
               table uses the same .leg-label styling as a Contract's. */
            --accent-gold: #873F1E;
        }

        @page {
            margin: 90px 45px 90px 45px;
        }

        @page :first {
            margin: 30px 45px 90px 45px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #111827;
        }

        h1, h2, h3 {
            margin: 0;
            padding: 0;
            color: #111827;
        }

        .footer {
            position: fixed;
            bottom: -80px;
            left: 0;
            right: 0;
            height: 56px;
            padding-top: 16px;
            border-top: 1px solid #d1d5db;
            font-size: 7px;
            line-height: 1.6;
            color: #6b7280;
            text-align: center;
        }

        .footer-legal-name {
            font-weight: bold;
            color: #4b5563;
        }

        .footer-contact-row {
            margin-top: 6px;
        }

        table.header-row {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #111827;
            margin-bottom: 20px;
        }

        table.header-row td {
            padding: 0 0 12px 0;
            vertical-align: bottom;
        }

        .header-row .meta-cell {
            width: 40%;
            text-align: right;
            font-size: 9.5px;
            color: #4b5563;
        }

        .quotation-title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #111827;
        }

        .header-row .logo-cell {
            vertical-align: bottom;
        }

        .header-row .logo-cell img {
            /* Only width is set here — logo.png is cropped tight to its
               artwork (891x195), so height is left unset and scales
               proportionally from that intrinsic ratio. A fixed height
               alongside a fixed width previously stretched the logo. */
            width: 185px;
            margin-bottom: 70px;
        }

        .section {
            margin-bottom: 20px;
            clear: both;
        }

        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #111827;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
        }

        .client-name {
            font-size: 9.5px;
            font-weight: bold;
        }

        .client-vat {
            margin-top: 3px;
            color: #374151;
        }

        .client-address {
            margin-top: 3px;
            color: #374151;
            white-space: pre-line;
        }

        table.specs {
            width: 100%;
            border-collapse: collapse;
        }

        table.specs th, table.specs td {
            border: 1px solid #e5e7eb;
            padding: 6px 7px;
            text-align: left;
        }

        table.specs th {
            background-color: #f9fafb;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.4px;
            color: #6b7280;
        }

        table.specs td {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
            padding: 7px 7px;
        }

        table.specs th.nowrap-col, table.specs td.nowrap-col {
            white-space: nowrap;
        }

        table.specs td.leg-label {
            font-weight: bold;
            color: var(--accent-gold);
            white-space: nowrap;
        }

        .aircraft-model {
            font-size: 10.5px;
            font-weight: bold;
        }

        /* .price-amount (the div wrapping the price span, in
           quotes.partials.price-block) intentionally has no rule here —
           the price amount is an .aircraft-model span, so all its font
           styling comes from that shared class instead. The div itself
           still exists as a plain block-level wrapper, keeping the price
           on its own line under .price-label. */

        /* --- Offer options --- */

        .offer-option {
            margin-bottom: 10px;
            /* Keep each option on one page as a unit — without this, a
               box can split with its label stranded at the bottom of one
               page and its photos/price pushed to the next. */
            page-break-inside: avoid;
        }

        .offer-option:last-child {
            margin-bottom: 0;
        }

        /* Shared font/color for every card-level uppercase label —
           "Option N" and "Total Price" — so they read as one family
           rather than each being its own independently-set style.
           .option-label / .price-label now only carry their own spacing
           (margin-bottom differs since they sit in different positions
           in the card). */
        .card-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #434c60;
        }

        .option-label {
            margin-bottom: 4px;
        }

        /* Left column: text details (type, amenities, seats, cabin line,
           Total Price). Right column: the two photos, side by side. */
        table.offer-columns {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.offer-columns td {
            padding: 0;
            vertical-align: top;
        }

        td.offer-text-col {
            width: 48%;
            padding-right: 16px;
        }

        td.offer-photos-col {
            width: 52%;
        }

        /* Exterior/interior side by side rather than stacked — halves the
           photo block's height so it doesn't tower over the text column. */
        table.photo-row {
            border-collapse: collapse;
            width: 100%;
        }

        table.photo-row td.photo-cell {
            padding: 0;
        }

        table.photo-row td.photo-gap {
            width: 8px;
        }

        /* Every photo — exterior, interior, any option, any upload's
           original dimensions — renders at this exact size. dompdf
           doesn't support object-fit (kept here in case that ever
           changes; harmless no-op today), so the actual crop-to-fit
           happens server-side before the image is embedded — see
           QuoteRequestController::croppedPhotoDataUri(). The text column
           gave up width (56% → 48%) to let these grow — amenity badges
           may now wrap onto a second line, which is the acceptable
           trade-off for photos this size. */
        .photo-row img {
            display: block;
            width: 177px;
            height: 100px;
            object-fit: cover;
            border-radius: 7px;
        }

        /* "AMENITIES" title line — same font as .detail-label-inline (size,
           weight, color, letter-spacing) but standing on its own line
           above the badges row rather than inline before a value; the
           badges wrap across multiple lines, so unlike Seats/Cabin size
           there's no single value to sit inline with. */
        .detail-label {
            display: block;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 8px;
            color: #6b7280;
            margin-bottom: 7px;
        }

        .amenities {
            margin-top: 6px;
        }

        /* Shared font-size/weight for every detail *value* below an
           uppercase .detail-label / .detail-label-inline — amenity
           badges, the Seats figure, and the Cabin size figure (baggage
           included, same cell). Applied alongside each element's own
           class (.amenity-badge, .cabin-seats, .cabin-summary) so those
           keep their own color/background/spacing, but can't drift onto
           a different size or weight from one another again. */
        .detail-value {
            font-size: 8.5px;
            font-weight: normal;
        }

        .amenity-badge {
            display: inline-block;
            background-color: #f3f4f6;
            color: #374151;
            border-radius: 9px;
            padding: 3px 8px;
            margin: 0 4px 4px 0;
        }

        .cabin-block {
            margin-top: 6px;
        }

        /* "SEATS – 13" / "CABIN SIZE – …" as a two-cell table rather than
           a label <span> immediately followed by its value as plain text:
           dompdf has a rendering bug where that pattern — plain text
           right after a differently-sized inline <span> on the same line
           — renders the text bold no matter what font-weight it's
           actually given (confirmed in isolation; not a deliberate style,
           dompdf just ignores font-weight there). Table cells don't share
           the bug, since each cell is its own layout/font context, and
           still sit flush on one line the same way inline content would. */
        table.detail-row {
            border-collapse: collapse;
        }

        table.detail-row td {
            padding: 0;
        }

        table.detail-row td.detail-label-inline {
            white-space: nowrap;
            padding-right: 4px;
        }

        table.detail-row.cabin-size-row {
            margin-top: 3px;
        }

        .cabin-seats {
            color: #111827;
        }

        /* The "SEATS –" / "CABIN SIZE –" label cell itself — same font as
           .detail-label (Amenities' own standalone line) but staying
           inline as a table cell rather than a block. */
        .detail-label-inline {
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 8px;
            color: #6b7280;
        }

        .cabin-summary {
            color: #374151;
        }

        table.offer-footer-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.offer-footer-row td {
            vertical-align: bottom;
        }

        table.offer-footer-row td.price-cell {
            text-align: right;
        }

        /* Total Price tucked directly under the left column's cabin/seats
           details (two-column layout only) instead of isolated in its own
           row at the very bottom of the card — see table.offer-footer-row
           above, still used for the single-column fallback. */
        .price-block {
            margin-top: 8px;
        }

        .price-label {
            margin-bottom: 2px;
        }

        .standard-notes {
            font-size: 7.5px;
            line-height: 1.5;
            color: #6b7280;
        }

        .standard-notes ul {
            margin: 0;
            padding-left: 12px;
        }

        .standard-notes li {
            margin-bottom: 3px;
        }

        .standard-notes li:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>

    @include('pdf.partials.footer')

    <table class="header-row">
        <tr>
            <td class="logo-cell">
                <div>@include('pdf.partials.logo')</div>
                <h1 class="quotation-title">QUOTATION</h1>
            </td>
            <td class="meta-cell">
                <div>Date Generated: {{ now()->format('d M Y') }}</div>
                <div>Quote Ref: {{ $quoteRequest->quotation_reference }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Client</div>
        <div class="box">
            <div class="client-name">{{ $client->company_name ?? 'Untitled client' }}</div>
            @if ($client->vat_code)
                <div class="client-vat">VAT: {{ $client->vat_code }}</div>
            @endif
            @if ($client->address)
                <div class="client-address">{{ $client->address }}</div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Itinerary</div>
        <table class="specs">
            <thead>
                <tr>
                    @if ($legCount > 1)
                        <th class="nowrap-col">Leg</th>
                    @endif
                    <th class="nowrap-col">Date</th>
                    <th>From</th>
                    <th>To</th>
                    <th class="nowrap-col">Take-off</th>
                    <th class="nowrap-col">Arrival</th>
                    @if ($showFlightTime)
                        <th class="nowrap-col">Flight Time</th>
                    @endif
                    <th class="nowrap-col">Pax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($itineraryLegs as $index => $leg)
                    <tr>
                        @if ($legCount > 1)
                            <td class="leg-label">{{ $index + 1 }}</td>
                        @endif
                        <td class="nowrap-col">{{ $leg['date'] ?? '—' }}</td>
                        <td>{{ $leg['departure'] ?? '—' }}</td>
                        <td>{{ $leg['arrival'] ?? '—' }}</td>
                        <td class="nowrap-col">{{ $leg['departure_time'] ?? '—' }}</td>
                        <td class="nowrap-col">{{ $leg['arrival_time'] ?? '—' }}</td>
                        @if ($showFlightTime)
                            <td class="nowrap-col">
                                {{ $leg['flight_duration_minutes'] !== null ? $durationLabel($leg['flight_duration_minutes']) : '—' }}
                            </td>
                        @endif
                        <td class="nowrap-col">{{ $leg['pax'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section standard-notes">
        <ul>
            <li>Departure and arrival times are given according to the local time zone of the origin/destination airports.</li>
            <li>Schedule changes will take into account crew duty limitations as well as slots and permits.</li>
            <li>Costs for de-icing, or for parking the aircraft in a hangar to avoid de-icing, are not included in the flight price. Premier Sky has the right to charge these costs separately, upon presentation of supporting documents.</li>
        </ul>
    </div>

    <div class="section">
        <div class="section-title">
            {{ $optionCount > 1 ? 'Aircraft Options' : 'Aircraft' }}
        </div>

        @foreach ($offers as $index => $offer)
            <div class="box offer-option">
                @if ($optionCount > 1)
                    <div class="card-label option-label">Option {{ $index + 1 }}</div>
                @endif

                <div class="aircraft-model">{{ $offer['aircraft_type'] }}</div>

                @if ($offer['tail'] && count($offer['tail']['photos']) > 0)
                    {{-- Text details on the left, the two photos side by
                         side on the right. Total Price sits directly under
                         the left column's cabin details rather than in its
                         own row below — the left column is the shorter of
                         the two, so that's where the card has room. --}}
                    <table class="offer-columns">
                        <tr>
                            <td class="offer-text-col">
                                @include('quotes.partials.tail-details', ['tail' => $offer['tail']])
                                <div class="price-block">
                                    @include('quotes.partials.price-block', ['amount' => $offer['total_price'], 'currency' => $offer['currency'], 'formatAmount' => $formatAmount])
                                </div>
                            </td>
                            <td class="offer-photos-col">
                                <table class="photo-row">
                                    <tr>
                                        @foreach ($offer['tail']['photos'] as $photoIndex => $photo)
                                            @if ($photoIndex > 0)
                                                <td class="photo-gap"></td>
                                            @endif
                                            <td class="photo-cell"><img src="{{ $photo }}" alt="Aircraft photo"></td>
                                        @endforeach
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                @else
                    @if ($offer['tail'])
                        {{-- Tail matched but no photos on file — amenities/cabin
                             info still applies, just without a photo column. --}}
                        @include('quotes.partials.tail-details', ['tail' => $offer['tail']])
                    @elseif ($offer['cabin'])
                        {{-- No Tail match, but the aircraft type was found in
                             aircraft_speed_reference — same cabin/seats line,
                             minus amenities and photos, neither of which
                             exist outside a Tail record. tail-details already
                             skips its amenities block on an empty array, so
                             this reuses it as-is rather than forking it. --}}
                        @include('quotes.partials.tail-details', ['tail' => ['amenities' => [], 'seats' => $offer['cabin']['seats'], 'cabin_summary' => $offer['cabin']['cabin_summary']]])
                    @endif

                    {{-- Single-column cases (no tail, or tail without
                         photos) have no left/right imbalance to fix, so
                         Total Price stays in its own row, right-aligned. --}}
                    <table class="offer-footer-row">
                        <tr>
                            <td></td>
                            <td class="price-cell">
                                @include('quotes.partials.price-block', ['amount' => $offer['total_price'], 'currency' => $offer['currency'], 'formatAmount' => $formatAmount])
                            </td>
                        </tr>
                    </table>
                @endif
            </div>
        @endforeach
    </div>

</body>
</html>
