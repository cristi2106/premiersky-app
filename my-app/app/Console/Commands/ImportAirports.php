<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Services\TimezoneResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportAirports extends Command
{
    protected $signature = 'airports:import';

    protected $description = 'Import large/medium airports from the OurAirports open dataset, deriving each timezone from its coordinates';

    private const AIRPORTS_URL = 'https://davidmegginson.github.io/ourairports-data/airports.csv';

    private const COUNTRIES_URL = 'https://davidmegginson.github.io/ourairports-data/countries.csv';

    private const ALLOWED_TYPES = ['large_airport', 'medium_airport'];

    public function handle(TimezoneResolver $timezoneResolver): int
    {
        $countries = $this->downloadCountries();

        if ($countries === null) {
            $this->error('Failed to download countries.csv — aborting.');

            return self::FAILURE;
        }

        $this->info(sprintf('Downloaded %d countries.', count($countries)));

        $airportsPath = $this->download(self::AIRPORTS_URL, 'airports');

        if ($airportsPath === null) {
            return self::FAILURE;
        }

        $rawTotal = 0;
        $candidates = 0;
        $imported = 0;
        $skipped = [];

        $handle = fopen($airportsPath, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');

        if ($header === false) {
            $this->error('airports.csv appears to be empty.');
            fclose($handle);
            unlink($airportsPath);

            return self::FAILURE;
        }

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rawTotal++;

            if (count($row) !== count($header)) {
                $skipped[] = "Row {$rawTotal}: column count mismatch, skipped";

                continue;
            }

            $record = array_combine($header, $row);

            if (! in_array($record['type'] ?? '', self::ALLOWED_TYPES, true)) {
                continue;
            }

            $candidates++;

            $result = $this->buildAirportData($record, $countries, $timezoneResolver);

            if ($result['error'] !== null) {
                $identifier = $record['ident'] ?: ($record['name'] ?? 'unknown');
                $skipped[] = "{$identifier}: {$result['error']}";

                continue;
            }

            Airport::updateOrCreate(
                ['icao_code' => $result['data']['icao_code']],
                $result['data']
            );

            $imported++;
        }

        fclose($handle);
        unlink($airportsPath);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total rows in source file', $rawTotal],
                ['Matched large_airport / medium_airport', $candidates],
                ['Imported (created or updated)', $imported],
                ['Skipped', count($skipped)],
            ]
        );

        if ($skipped !== []) {
            $this->warn(sprintf('%d record(s) skipped — see storage/logs for the full list. First few:', count($skipped)));

            foreach (array_slice($skipped, 0, 15) as $reason) {
                $this->line("  - {$reason}");
            }

            Log::info('airports:import skipped records', ['skipped' => $skipped]);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $countries  ISO country code => country name
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    private function buildAirportData(array $record, array $countries, TimezoneResolver $timezoneResolver): array
    {
        $name = trim($record['name'] ?? '');

        if ($name === '') {
            return ['data' => null, 'error' => 'missing name'];
        }

        $icaoCode = strtoupper(trim($record['icao_code'] ?? ''));

        if ($icaoCode === '') {
            $icaoCode = strtoupper(trim($record['ident'] ?? ''));
        }

        if (! preg_match('/^[A-Z]{4}$/', $icaoCode)) {
            return ['data' => null, 'error' => "invalid or missing ICAO code ('{$icaoCode}')"];
        }

        $iataCode = strtoupper(trim($record['iata_code'] ?? ''));
        $iataCode = preg_match('/^[A-Z]{3}$/', $iataCode) ? $iataCode : null;

        $countryCode = strtoupper(trim($record['iso_country'] ?? ''));
        $countryName = $countries[$countryCode] ?? null;

        if ($countryName === null) {
            return ['data' => null, 'error' => "unrecognized country code '{$countryCode}'"];
        }

        $latitude = $record['latitude_deg'] ?? '';
        $longitude = $record['longitude_deg'] ?? '';

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return ['data' => null, 'error' => 'missing or non-numeric coordinates'];
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['data' => null, 'error' => 'coordinates out of range'];
        }

        $timezone = $timezoneResolver->resolve($latitude, $longitude, $countryCode);

        if ($timezone === null) {
            return ['data' => null, 'error' => 'could not determine timezone'];
        }

        return [
            'data' => [
                'name' => $name,
                'icao_code' => $icaoCode,
                'iata_code' => $iataCode,
                'country' => $countryName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $timezone,
            ],
            'error' => null,
        ];
    }

    /**
     * @return array<string, string>|null ISO country code => country name
     */
    private function downloadCountries(): ?array
    {
        $path = $this->download(self::COUNTRIES_URL, 'countries');

        if ($path === null) {
            return null;
        }

        $countries = [];

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');

        if ($header === false) {
            fclose($handle);
            unlink($path);

            return null;
        }

        $codeIndex = array_search('code', $header, true);
        $nameIndex = array_search('name', $header, true);

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($codeIndex === false || $nameIndex === false) {
                continue;
            }

            $code = trim($row[$codeIndex] ?? '');
            $name = trim($row[$nameIndex] ?? '');

            if ($code === '' || $name === '') {
                continue;
            }

            $countries[strtoupper($code)] = $name;
        }

        fclose($handle);
        unlink($path);

        return $countries;
    }

    /**
     * Downloads a URL to a temp file and returns its path, or null on failure.
     */
    private function download(string $url, string $label): ?string
    {
        $this->info("Downloading {$label}...");

        $response = Http::timeout(120)->get($url);

        if ($response->failed()) {
            $this->error("Failed to download {$label}: HTTP {$response->status()}");

            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'ourairports_');
        file_put_contents($path, $response->body());

        return $path;
    }
}
