<?php

namespace App\Services;

/**
 * Derives an IANA timezone identifier (e.g. "Europe/Bucharest") from
 * geographic coordinates.
 *
 * There is no well-established, trustworthy offline PHP package for
 * point-in-polygon timezone-boundary lookups — the only close match on
 * Packagist (mamluk/geo-tz) has no verifiable source repository and
 * negligible adoption, so it was skipped for supply-chain safety.
 *
 * Instead this resolves the timezone whose "principal location" —
 * published in the IANA tzdata project's zone1970.tab reference table,
 * bundled at resources/data/zone1970.tab and public domain — is
 * geographically closest to the given coordinates, using great-circle
 * (Haversine) distance. This is the same reference table the tz database
 * itself ships for country/location-based zone selection.
 *
 * Whenever an ISO 3166-1 alpha-2 country code is known, the search is
 * restricted to that country's rows first. This matters more than it
 * might seem: some countries share civil time history with a zone whose
 * reference point is geographically distant (e.g. Iceland is grouped
 * under "Africa/Abidjan" because both have followed UTC+0 with no DST
 * since 1970), so an unconstrained nearest-point search can pick the
 * wrong zone entirely. Without a country code, it falls back to a global
 * nearest-point search across all reference points.
 */
class TimezoneResolver
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** @var array<int, array{codes: string[], lat: float, lon: float, tz: string}> */
    private array $referencePoints;

    public function __construct(?string $dataPath = null)
    {
        $this->referencePoints = $this->loadReferencePoints(
            $dataPath ?? resource_path('data/zone1970.tab')
        );
    }

    /**
     * Returns the IANA timezone identifier closest to the given
     * coordinates, or null if the reference table failed to load.
     *
     * @param  string|null  $countryCode  ISO 3166-1 alpha-2 code, if known.
     */
    public function resolve(float $latitude, float $longitude, ?string $countryCode = null): ?string
    {
        $candidates = $this->referencePoints;

        if ($countryCode !== null) {
            $countryCode = strtoupper($countryCode);
            $inCountry = array_filter(
                $this->referencePoints,
                fn (array $point) => in_array($countryCode, $point['codes'], true)
            );

            if ($inCountry !== []) {
                $candidates = $inCountry;
            }
        }

        $closestTimezone = null;
        $closestDistance = null;

        foreach ($candidates as $point) {
            $distance = $this->haversineDistanceKm(
                $latitude,
                $longitude,
                $point['lat'],
                $point['lon']
            );

            if ($closestDistance === null || $distance < $closestDistance) {
                $closestDistance = $distance;
                $closestTimezone = $point['tz'];
            }
        }

        return $closestTimezone;
    }

    /**
     * @return array<int, array{codes: string[], lat: float, lon: float, tz: string}>
     */
    private function loadReferencePoints(string $path): array
    {
        $points = [];

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return $points;
        }

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", $line);

            if (count($columns) < 3) {
                continue;
            }

            [$countryCodes, $coordinates, $timezone] = $columns;

            $coords = $this->parseIso6709($coordinates);

            if ($coords === null) {
                continue;
            }

            $points[] = [
                'codes' => explode(',', $countryCodes),
                'lat' => $coords[0],
                'lon' => $coords[1],
                'tz' => $timezone,
            ];
        }

        fclose($handle);

        return $points;
    }

    /**
     * Parses an ISO 6709 sign-degrees-minutes(-seconds) coordinate pair,
     * e.g. "+4426+02606" or "+404251-0740023", into [latitude, longitude].
     *
     * @return array{0: float, 1: float}|null
     */
    private function parseIso6709(string $value): ?array
    {
        $pattern = '/^([+-]\d{2})(\d{2})(\d{2})?([+-]\d{3})(\d{2})(\d{2})?$/';

        if (! preg_match($pattern, $value, $m)) {
            return null;
        }

        $latitude = $this->toDecimalDegrees(
            (int) $m[1],
            (int) $m[2],
            isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0
        );

        $longitude = $this->toDecimalDegrees(
            (int) $m[4],
            (int) $m[5],
            isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0
        );

        return [$latitude, $longitude];
    }

    private function toDecimalDegrees(int $degrees, int $minutes, int $seconds): float
    {
        $sign = $degrees < 0 ? -1 : 1;

        return $sign * (abs($degrees) + $minutes / 60 + $seconds / 3600);
    }

    private function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
