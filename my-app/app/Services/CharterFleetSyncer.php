<?php

namespace App\Services;

use App\Models\CharterFleetAircraft;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CharterFleetSyncer
{
    private const PAGE_DELAY_MICROSECONDS = 300_000;

    private const MAX_RATE_LIMIT_RETRIES = 5;

    /**
     * Pull every page of the Aviapages charter_aircraft endpoint and
     * upsert it into charter_fleet, keyed on aviapages_id.
     *
     * @param  callable(string): void|null  $onProgress  called with a status line after each page
     * @return array{imported: int, updated: int, skipped: int, skipped_reasons: array<int, string>}
     */
    public function sync(?callable $onProgress = null): array
    {
        $apiKey = config('services.aviapages.key');

        if (blank($apiKey)) {
            throw new \RuntimeException('AVIAPAGES_API_KEY is not configured.');
        }

        $url = rtrim(config('services.aviapages.base_url'), '/').'/charter_aircraft/';

        $imported = 0;
        $updated = 0;
        $skippedReasons = [];
        $page = 1;

        while ($url !== null) {
            $response = $this->fetchWithRetry($url, $apiKey);

            $body = $response->json();
            $results = $body['results'] ?? [];

            foreach ($results as $result) {
                $data = $this->mapRecord($result);

                if ($data === null) {
                    $skippedReasons[] = sprintf('aviapages id %s: missing registration number', $result['id'] ?? 'unknown');

                    continue;
                }

                $aircraft = CharterFleetAircraft::updateOrCreate(
                    ['aviapages_id' => $data['aviapages_id']],
                    $data
                );

                $aircraft->wasRecentlyCreated ? $imported++ : $updated++;
            }

            if ($onProgress !== null) {
                $onProgress(sprintf('Page %d: %d record(s) processed (%d imported, %d updated so far).', $page, count($results), $imported, $updated));
            }

            $url = $body['next'] ?? null;
            $page++;

            if ($url !== null) {
                usleep(self::PAGE_DELAY_MICROSECONDS);
            }
        }

        if ($skippedReasons !== []) {
            Log::info('fleet:sync skipped records', ['skipped' => $skippedReasons]);
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => count($skippedReasons),
            'skipped_reasons' => $skippedReasons,
        ];
    }

    /**
     * Fetches a URL, transparently waiting and retrying on a 429 response.
     *
     * @throws \RuntimeException if the request fails for a non-rate-limit reason,
     *                           or is still rate limited after all retries.
     */
    private function fetchWithRetry(string $url, string $apiKey): Response
    {
        $attempt = 0;

        while (true) {
            $response = Http::withHeaders(['Authorization' => "Token {$apiKey}"])
                ->timeout(30)
                ->get($url);

            if ($response->status() === 429) {
                $detail = (string) ($response->json('detail') ?? '');

                // A monthly quota is a hard stop for the rest of the billing
                // period — waiting and retrying can never succeed, so fail
                // fast instead of burning through retries first.
                if (str_contains(strtolower($detail), 'monthly api limit')) {
                    throw new \RuntimeException("Aviapages monthly API limit exceeded — sync cannot continue until it resets. ({$detail})");
                }

                $attempt++;

                if ($attempt > self::MAX_RATE_LIMIT_RETRIES) {
                    throw new \RuntimeException("Rate limited by Aviapages after {$attempt} attempts fetching {$url}.");
                }

                $retryAfter = (int) ($response->header('Retry-After') ?: 5);
                sleep(max($retryAfter, 1));

                continue;
            }

            if ($response->failed()) {
                throw new \RuntimeException("Aviapages request failed: HTTP {$response->status()} for {$url}.");
            }

            return $response;
        }
    }

    /**
     * @return array<string, mixed>|null null if the record has no usable registration number
     */
    private function mapRecord(array $result): ?array
    {
        $registration = trim((string) ($result['registration_number'] ?? ''));

        if ($registration === '') {
            return null;
        }

        $extension = $result['aircraft_extension'] ?? [];
        $exteriorImageUrl = null;

        foreach ($result['images'] ?? [] as $image) {
            if (($image['tag']['value'] ?? null) === 'exterior') {
                $exteriorImageUrl = $image['media']['path'] ?? null;

                break;
            }
        }

        return [
            'aviapages_id' => $result['id'],
            'registration_number' => $registration,
            'operator_name' => $result['company']['name'] ?? null,
            'aircraft_type_name' => $result['aircraft_type']['name'] ?? null,
            'aircraft_type_icao' => $result['aircraft_type']['icao'] ?? null,
            'aircraft_class' => $result['aircraft_type']['aircraft_class']['name'] ?? null,
            'year_of_production' => $result['year_of_production'] ?? null,
            'passengers_max' => $result['passengers_max'] ?? null,
            'lavatory' => $extension['lavatory'] ?? null,
            'beds' => $extension['beds'] ?? null,
            'wireless_internet' => $extension['wireless_internet'] ?? null,
            'entertainment_system' => $extension['entertainment_system'] ?? null,
            'pets_allowed' => $extension['pets_allowed'] ?? null,
            'smoking' => $extension['smoking'] ?? null,
            'cabin_height' => $extension['cabin_height'] ?? null,
            'cabin_length' => $extension['cabin_length'] ?? null,
            'cabin_width' => $extension['cabin_width'] ?? null,
            'luggage_volume' => $extension['luggage_volume'] ?? null,
            'sleeping_places' => $extension['sleeping_places'] ?? null,
            'divan_seats' => $extension['divan_seats'] ?? null,
            'hot_meal' => $extension['hot_meal'] ?? null,
            'medical_ramp' => $extension['medical_ramp'] ?? null,
            'refurbishment' => $extension['refurbishment'] ?? null,
            'description' => $extension['description'] ?? null,
            'exterior_image_url' => $exteriorImageUrl,
        ];
    }
}
