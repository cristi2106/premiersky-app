<?php
    $legCount = $contract->legs->count();
    $airportLabel = fn ($airport) => $airport->name . ' (' . ($airport->iata_code ?: $airport->icao_code) . ')';
    $durationLabel = function (int $minutes) {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    };
    $formatAmount = fn (float $amount) => number_format($amount, 0);
    $vatPercentage = (float) $contract->vat_percentage;
    $priceWithVat = (float) $contract->price * (1 + $vatPercentage / 100);
    $totalLabel = 'Total ' . $formatAmount($priceWithVat) . ' ' . $contract->currency;
    $priceBreakdownHtml = $vatPercentage > 0
        ? e($formatAmount((float) $contract->price) . ' ' . $contract->currency . ' + VAT ' . rtrim(rtrim(number_format($vatPercentage, 2), '0'), '.') . '% = ') . '<span class="price-total-hero">' . e($totalLabel) . '</span>'
        : e($formatAmount((float) $contract->price) . ' ' . $contract->currency);
    $logoPath = resource_path('images/LOGO2023.png');
    $logoData = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
    $termsPath = app_path('terms/terms_and_conditions.txt');
    $termsContent = is_file($termsPath) ? trim(file_get_contents($termsPath)) : null;
    $termsParagraphs = [];
    if ($termsContent) {
        foreach (preg_split('/\n\s*\n/', $termsContent) as $block) {
            $block = trim($block, "\n");
            if ($block === '') {
                continue;
            }
            $lines = explode("\n", $block);
            $firstLine = trim($lines[0]);
            $isTitle = (bool) preg_match('/^[A-Z][A-Z0-9 ,.\'&\-]{3,}$/', $firstLine);
            $isSectionHeading = (bool) preg_match('/^\d+\.\s+\S.*$/', $firstLine);
            $isHeading = $isTitle || $isSectionHeading;
            $bodyLines = array_values(array_filter(
                array_map('trim', $isHeading ? array_slice($lines, 1) : $lines),
                fn ($line) => $line !== ''
            ));
            $termsParagraphs[] = [
                'heading' => $isHeading ? $firstLine : null,
                'isTitle' => $isTitle,
                'lines' => $bodyLines,
            ];
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Charter Agreement {{ $contract->reference_number }}</title>
    <style>
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

        .agreement-title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #111827;
        }

        .header-row .logo-cell {
            vertical-align: bottom;
        }

        .header-row .logo-cell img {
            width: 185px;
            height: 74px;
            margin-bottom: 70px;
        }

        .section {
            margin-bottom: 36px;
            clear: both;
        }

        .section-charterer {
            margin-bottom: 14px;
        }

        .section-itinerary {
            margin-bottom: 14px;
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
            padding: 10px 12px;
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

        table.specs td.leg-label {
            font-weight: bold;
            color: #9c7a2a;
            white-space: nowrap;
        }

        table.specs th.date-col, table.specs td.date-col {
            white-space: nowrap;
        }

        table.specs th.nowrap-col, table.specs td.nowrap-col {
            white-space: nowrap;
        }

        table.side-by-side {
            width: 100%;
            border-collapse: collapse;
        }

        table.side-by-side td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        table.side-by-side td.left-cell {
            padding-right: 8px;
        }

        table.side-by-side td.right-cell {
            padding-left: 8px;
        }

        .price-amount {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
        }

        .price-total-hero {
            font-size: 11px;
            font-weight: bold;
        }

        .aircraft-model {
            font-size: 9.5px;
            font-weight: bold;
        }

        .text-block {
            white-space: pre-line;
            color: #374151;
        }

        .placeholder-note {
            color: #9ca3af;
            font-style: italic;
        }

        .terms-block {
            margin-top: 9px;
        }

        .terms-block:first-child {
            margin-top: 0;
        }

        .terms-block.has-heading {
            margin-top: 20px;
        }

        .terms-block.has-heading:first-child {
            margin-top: 0;
        }

        .terms-heading {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .terms-title {
            text-align: center;
        }

        .terms-body {
            color: #374151;
        }

        .terms-line {
            margin: 0 0 9px;
        }

        .terms-line:last-child {
            margin-bottom: 0;
        }

        .section-aircraft-price {
            margin-bottom: 12px;
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

        .signature-note {
            font-size: 7.5px;
            line-height: 1.5;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .terms-page {
            page-break-before: always;
            font-size: 9px;
        }

        .terms-repeating-header {
            position: fixed;
            top: -70px;
            left: 0;
            right: 0;
        }

        table.terms-header-row {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
        }

        table.terms-header-row td {
            padding-bottom: 10px;
            vertical-align: middle;
        }

        .terms-header-row .terms-logo-cell img {
            width: 70px;
            height: 28px;
        }

        .terms-header-row .terms-ref-cell {
            text-align: right;
            font-size: 8.5px;
            color: #6b7280;
        }

        .signatures-section {
            position: absolute;
            left: 0;
            right: 0;
            bottom: -10px;
        }

        table.signatures {
            width: 100%;
            border-collapse: collapse;
        }

        table.signatures td {
            width: 50%;
            padding-top: 8px;
            font-size: 9.5px;
            color: #4b5563;
        }

        table.signatures td.right-cell {
            padding-left: 24px;
            border-left: 1px solid #d1d5db;
        }

        table.signatures td.left-cell {
            padding-right: 24px;
        }

        .signature-line {
            height: 28px;
            border-bottom: 1px solid #9ca3af;
        }

        .signature-label {
            margin-top: 4px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    @include('pdf.partials.footer')

    <div class="terms-repeating-header">
        <table class="terms-header-row">
            <tr>
                <td class="terms-logo-cell">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="Company logo">
                    @endif
                </td>
                <td class="terms-ref-cell">
                    Contract Ref: {{ $contract->reference_number }}
                </td>
            </tr>
        </table>
    </div>

    <table class="header-row">
        <tr>
            <td class="logo-cell">
                @if ($logoData)
                    <div><img src="{{ $logoData }}" alt="Company logo"></div>
                @endif
                <h1 class="agreement-title">CHARTER AGREEMENT</h1>
            </td>
            <td class="meta-cell">
                <div>Date Generated: {{ now()->format('d M Y') }}</div>
                <div>Contract Ref: {{ $contract->reference_number }}</div>
            </td>
        </tr>
    </table>

    <div class="section section-charterer">
        <div class="section-title">Charterer</div>
        <div class="box">
            <div class="client-name">{{ $contract->client->company_name ?? 'Untitled client' }}</div>
            @if ($contract->client->vat_code)
                <div class="client-vat">VAT: {{ $contract->client->vat_code }}</div>
            @endif
            @if ($contract->client->address)
                <div class="client-address">{{ $contract->client->address }}</div>
            @endif
        </div>
    </div>

    <div class="section section-itinerary">
        <div class="section-title">Itinerary</div>
        <table class="specs">
            <thead>
                <tr>
                    @if ($legCount > 1)
                        <th class="nowrap-col">Leg</th>
                    @endif
                    <th class="date-col">Date</th>
                    <th>From</th>
                    <th>To</th>
                    <th class="nowrap-col">Take-off</th>
                    <th class="nowrap-col">Arrival</th>
                    <th class="nowrap-col">Flight Time</th>
                    <th class="nowrap-col">Pax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contract->legs as $index => $leg)
                    <tr>
                        @if ($legCount > 1)
                            <td class="leg-label">{{ $index + 1 }}</td>
                        @endif
                        <td class="date-col">{{ $leg->flight_date->format('d M Y') }}</td>
                        <td>{{ $airportLabel($leg->departureAirport) }}</td>
                        <td>{{ $airportLabel($leg->arrivalAirport) }}</td>
                        <td class="nowrap-col">{{ substr($leg->departure_time, 0, 5) }}</td>
                        <td class="nowrap-col">{{ $leg->arrival_datetime->format('H:i') }}</td>
                        <td class="nowrap-col">{{ $durationLabel($leg->flight_duration_minutes) }}</td>
                        <td class="nowrap-col">{{ $leg->pax }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section section-aircraft-price">
        <table class="side-by-side">
            <tr>
                <td class="left-cell">
                    <div class="section-title">Aircraft</div>
                    <div class="box">
                        <div class="aircraft-model">{{ $contract->aircraft->type_name }}</div>
                    </div>
                </td>
                <td class="right-cell">
                    <div class="section-title">Price</div>
                    <div class="box">
                        <div class="price-amount">{!! $priceBreakdownHtml !!}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section standard-notes">
        <ul>
            <li>Departure and arrival times are given according to the local time zone of the origin/destination airports</li>
            <li>Schedule changes will take into account crew duty limitations as well as slots and permits.</li>
            <li>Costs for de-icing, or for parking the aircraft in a hangar to avoid de-icing, are not included in the flight price. Premier Sky has the right to charge these costs separately, upon presentation of supporting documents.</li>
        </ul>
    </div>

    @if ($contract->special_information)
        <div class="section">
            <div class="section-title">Special Information</div>
            <div class="text-block">{{ $contract->special_information }}</div>
        </div>
    @endif

    @if ($contract->cancellation_policy)
        <div class="section">
            <div class="section-title">Cancellation Policy</div>
            <div class="text-block">{{ $contract->cancellation_policy }}</div>
        </div>
    @endif

    <div class="section signatures-section">
        <div class="section-title">Signatures</div>
        <p class="signature-note">By signing below, the Charterer accepts and agrees to the Terms and Conditions set out on the following page.</p>
        <table class="signatures">
            <tr>
                <td class="left-cell">
                    <div class="signature-line"></div>
                    <div class="signature-label">Charterer Signature</div>
                </td>
                <td class="right-cell">
                    <div class="signature-line"></div>
                    <div class="signature-label">Broker Signature</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="terms-page">
        <div class="section">
            @if ($termsParagraphs)
                @foreach ($termsParagraphs as $paragraph)
                    <div class="terms-block @if ($paragraph['heading']) has-heading @endif @if ($paragraph['isTitle']) terms-title @endif">
                        @if ($paragraph['heading'])
                            <div class="terms-heading">{{ $paragraph['heading'] }}</div>
                        @endif
                        @if ($paragraph['lines'])
                            <div class="terms-body">
                                @foreach ($paragraph['lines'] as $line)
                                    <p class="terms-line">{{ $line }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <p class="placeholder-note">[Terms and conditions file not found — add it at app/terms/terms_and_conditions.txt]</p>
            @endif
        </div>
    </div>

</body>
</html>
