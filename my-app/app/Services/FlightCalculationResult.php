<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * The result of a FlightCalculator::calculate() call. Shared value object so
 * the Flight Calculator page and the future Contracts module render (and
 * eventually store) exactly the same numbers.
 */
final readonly class FlightCalculationResult
{
    public function __construct(
        public float $distanceNauticalMiles,
        public int $durationMinutes,
        public CarbonImmutable $departureLocal,
        public CarbonImmutable $arrivalLocal,
    ) {}
}
