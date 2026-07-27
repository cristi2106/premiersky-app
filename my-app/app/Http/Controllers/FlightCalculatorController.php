<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\AircraftSpeedReference;
use App\Services\FlightCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlightCalculatorController extends Controller
{
    /**
     * Display the calculator page.
     */
    public function index(): Response
    {
        return Inertia::render('FlightCalculator/Index');
    }

    /**
     * Run a calculation and return the result as JSON, for the page's live
     * (no save button) calculation.
     */
    public function calculate(Request $request, FlightCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'departure_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'arrival_airport_id' => ['required', 'integer', 'different:departure_airport_id', 'exists:airports,id'],
            'aircraft_speed_reference_id' => ['required', 'integer', 'exists:aircraft_speed_reference,id'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['required', 'date_format:H:i'],
        ]);

        $departureAirport = Airport::findOrFail($data['departure_airport_id']);
        $arrivalAirport = Airport::findOrFail($data['arrival_airport_id']);
        $aircraft = AircraftSpeedReference::findOrFail($data['aircraft_speed_reference_id']);

        $departureLocal = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$data['departure_date']} {$data['departure_time']}",
            $departureAirport->timezone,
        );

        $result = $calculator->calculate(
            $departureAirport,
            $arrivalAirport,
            $aircraft->cruise_speed_knots,
            $departureLocal,
        );

        return response()->json([
            'distance_nautical_miles' => round($result->distanceNauticalMiles, 1),
            'duration_minutes' => $result->durationMinutes,
            'duration_formatted' => sprintf('%dh %02dm', intdiv($result->durationMinutes, 60), $result->durationMinutes % 60),
            'departure' => [
                'airport' => $departureAirport->only(['name', 'icao_code', 'iata_code']),
                'datetime' => $result->departureLocal->toIso8601String(),
                'formatted' => $result->departureLocal->format('D, M j, Y \a\t H:i'),
                'timezone' => $result->departureLocal->timezone->getName(),
                'utc_offset' => $this->formatUtcOffset($result->departureLocal),
            ],
            'arrival' => [
                'airport' => $arrivalAirport->only(['name', 'icao_code', 'iata_code']),
                'datetime' => $result->arrivalLocal->toIso8601String(),
                'formatted' => $result->arrivalLocal->format('D, M j, Y \a\t H:i'),
                'timezone' => $result->arrivalLocal->timezone->getName(),
                'utc_offset' => $this->formatUtcOffset($result->arrivalLocal),
            ],
        ]);
    }

    /**
     * Formats a moment's UTC offset as "UTC+3" / "UTC-4" / "UTC+5:30",
     * derived from the moment's actual date so it reflects whichever side
     * of a DST transition that date falls on — never a fixed value.
     */
    private function formatUtcOffset(CarbonImmutable $moment): string
    {
        $offsetMinutes = $moment->utcOffset();
        $sign = $offsetMinutes < 0 ? '-' : '+';
        $absMinutes = abs($offsetMinutes);
        $hours = intdiv($absMinutes, 60);
        $minutes = $absMinutes % 60;

        return $minutes > 0
            ? sprintf('UTC%s%d:%02d', $sign, $hours, $minutes)
            : sprintf('UTC%s%d', $sign, $hours);
    }
}
