<?php
    $legCount = $contract->legs->count();
    $airportLabel = fn ($airport) => $airport->name . ' (' . ($airport->iata_code ?: $airport->icao_code) . ')';
    $durationLabel = function (int $minutes) {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    };
    $currencySymbols = ['EUR' => '€', 'USD' => '$', 'RON' => 'RON '];
    $priceLabel = ($currencySymbols[$contract->currency] ?? $contract->currency . ' ')
        . number_format((float) $contract->price, 2);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Charter Agreement {{ $contract->reference_number }}</title>
    <style>
        @page {
            margin: 70px 45px 90px 45px;
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
            bottom: -70px;
            left: 0;
            right: 0;
            height: 60px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }

        .footer .placeholder {
            color: #9ca3af;
            font-style: italic;
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
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .section {
            margin-bottom: 18px;
            clear: both;
        }

        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #2563eb;
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
            font-size: 12px;
            font-weight: bold;
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
            font-size: 9.5px;
        }

        table.specs th {
            background-color: #f9fafb;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.4px;
            color: #6b7280;
        }

        table.specs td.leg-label {
            font-weight: bold;
            color: #2563eb;
            white-space: nowrap;
        }

        table.specs th.date-col, table.specs td.date-col {
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
            font-size: 12px;
            font-weight: bold;
            color: #111827;
        }

        .aircraft-model {
            font-size: 12px;
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

        .terms-page {
            page-break-before: always;
            padding-top: 10px;
        }

        table.signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }

        table.signatures td {
            width: 50%;
            padding-top: 8px;
            border-top: 1px solid #111827;
            font-size: 9.5px;
            color: #4b5563;
        }

        table.signatures td.right-cell {
            padding-left: 20px;
        }

        table.signatures td.left-cell {
            padding-right: 20px;
        }
    </style>
</head>
<body>

    <div class="footer">
        <div class="placeholder">[Company footer details to be added — name, contact info, bank details]</div>
    </div>

    <table class="header-row">
        <tr>
            <td>
                <h1 class="agreement-title">CHARTER AGREEMENT</h1>
            </td>
            <td class="meta-cell">
                <div>Date Generated: {{ now()->format('d M Y') }}</div>
                <div>Contract Ref: {{ $contract->reference_number }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Charterer</div>
        <div class="box">
            <div class="client-name">{{ $contract->client->company_name ?? 'Untitled client' }}</div>
            @if ($contract->client->address)
                <div class="client-address">{{ $contract->client->address }}</div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Itinerary</div>
        <table class="specs">
            <thead>
                <tr>
                    @if ($legCount > 1)
                        <th>Leg</th>
                    @endif
                    <th class="date-col">Date</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Departure (local)</th>
                    <th>Arrival (local)</th>
                    <th>Flight Time</th>
                    <th>Pax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contract->legs as $index => $leg)
                    <tr>
                        @if ($legCount > 1)
                            <td class="leg-label">LEG {{ $index + 1 }}</td>
                        @endif
                        <td class="date-col">{{ $leg->flight_date->format('d M Y') }}</td>
                        <td>{{ $airportLabel($leg->departureAirport) }}</td>
                        <td>{{ $airportLabel($leg->arrivalAirport) }}</td>
                        <td>{{ substr($leg->departure_time, 0, 5) }}</td>
                        <td>{{ $leg->arrival_datetime->format('H:i') }}</td>
                        <td>{{ $durationLabel($leg->flight_duration_minutes) }}</td>
                        <td>{{ $leg->pax }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
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
                        <div class="price-amount">{{ $priceLabel }} {{ $contract->currency }}</div>
                    </div>
                </td>
            </tr>
        </table>
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

    <div class="section">
        <div class="section-title">Signatures</div>
        <table class="signatures">
            <tr>
                <td class="left-cell">
                    Charterer Signature &amp; Date
                </td>
                <td class="right-cell">
                    Broker Signature &amp; Date
                </td>
            </tr>
        </table>
    </div>

    <div class="terms-page">
        <div class="section">
            <div class="section-title">Terms and Conditions</div>
            <p class="placeholder-note">[Insert your company's terms and conditions here]</p>
        </div>
    </div>

</body>
</html>
