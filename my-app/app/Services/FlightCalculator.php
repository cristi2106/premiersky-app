<?php

namespace App\Services;

use App\Models\Airport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Great-circle distance and timezone-aware arrival time for a single leg.
 * Used by the standalone Flight Calculator page and, going forward, by the
 * Contracts module — keep the math here rather than duplicating it.
 */
class FlightCalculator
{
    private const EARTH_RADIUS_NAUTICAL_MILES = 3440.065;

    public function calculate(
        Airport $departureAirport,
        Airport $arrivalAirport,
        int $cruiseSpeedKnots,
        CarbonInterface $departureLocal,
    ): FlightCalculationResult {
        $distanceNauticalMiles = $this->haversineDistanceNauticalMiles(
            (float) $departureAirport->latitude,
            (float) $departureAirport->longitude,
            (float) $arrivalAirport->latitude,
            (float) $arrivalAirport->longitude,
        );

        $flightMinutes = ($distanceNauticalMiles / $cruiseSpeedKnots) * 60;
        $bufferMinutes = (float) config('flight_calculator.arrival_buffer_minutes');
        $durationMinutes = (int) round($flightMinutes + $bufferMinutes);

        $departureLocal = CarbonImmutable::instance($departureLocal);
        $arrivalLocal = $departureLocal
            ->addMinutes($durationMinutes)
            ->setTimezone($arrivalAirport->timezone);

        return new FlightCalculationResult(
            distanceNauticalMiles: $distanceNauticalMiles,
            durationMinutes: $durationMinutes,
            departureLocal: $departureLocal,
            arrivalLocal: $arrivalLocal,
        );
    }

    /**
     * Great-circle distance between two points, in nautical miles.
     */
    private function haversineDistanceNauticalMiles(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2,
    ): float {
        $lat1Rad = deg2rad($latitude1);
        $lat2Rad = deg2rad($latitude2);
        $deltaLatRad = deg2rad($latitude2 - $latitude1);
        $deltaLonRad = deg2rad($longitude2 - $longitude1);

        $a = sin($deltaLatRad / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLonRad / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_NAUTICAL_MILES * $c;
    }
}
