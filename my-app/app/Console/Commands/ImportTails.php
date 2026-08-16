<?php

namespace App\Console\Commands;

use App\Models\AircraftSpeedReference;
use App\Models\Tail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportTails extends Command
{
    protected $signature = 'tails:import {path : Path to the CSV file} {--dry-run : Parse and validate without writing to the database}';

    protected $description = 'Bulk-import tails from a CSV, upserting on tail (registration); category and aircraft type are left null when the CSV value has no (exact, then substring) match';

    /**
     * Header keywords for the five columns we look up by name. 'category'
     * is deliberately not listed here — see resolveCategoryIndex().
     */
    private const HEADER_KEYWORDS = [
        'tail' => ['regnr', 'registration', 'tailnumber', 'tail'],
        'operator' => ['operator', 'company'],
        'year_of_make' => ['yom', 'yearofmake', 'year'],
        'max_pax' => ['pax', 'maxpax', 'passengers'],
        'type' => ['type', 'aircrafttype', 'model'],
    ];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $this->error("Could not read file: {$path}");

            return self::FAILURE;
        }

        // Strip a UTF-8 byte-order mark, which spreadsheet exports commonly
        // prepend and which would otherwise corrupt the first header cell.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $delimiter = $this->detectDelimiter($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($header === false) {
            $this->error('CSV file appears to be empty.');
            fclose($handle);

            return self::FAILURE;
        }

        $columns = $this->resolveColumns($header);

        if (in_array(null, [$columns['tail'], $columns['operator'], $columns['year_of_make'], $columns['max_pax']], true)) {
            $this->error('Could not confidently identify the tail, operator, year of make, and max pax columns. Found headers: '.implode(' | ', $header));
            fclose($handle);

            return self::FAILURE;
        }

        $this->showColumnMapping($header, $columns, $delimiter);

        $categoryLookup = $this->buildCategoryLookup();
        $typeLookup = $this->buildTypeLookup();

        $currentYear = (int) now()->year;
        $columnCount = count($header);

        $rowNumber = 1; // header was row 1
        $imported = 0;
        $updated = 0;
        $categoryMismatches = [];
        $typeContainsMatches = [];
        $typeAmbiguous = [];
        $typeMismatches = [];
        $skipped = [];

        $dryRun = (bool) $this->option('dry-run');

        DB::transaction(function () use (
            $handle, $delimiter, $columns, $categoryLookup, $typeLookup, $currentYear, $columnCount,
            &$rowNumber, &$imported, &$updated, &$categoryMismatches,
            &$typeContainsMatches, &$typeAmbiguous, &$typeMismatches, &$skipped, $dryRun
        ) {
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $rowNumber++;

                // A single trailing blank line at EOF parses as one empty
                // field — not a real data row.
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                if (count($row) !== $columnCount) {
                    $skipped[] = "Row {$rowNumber}: expected {$columnCount} columns, found ".count($row);

                    continue;
                }

                $tailRaw = trim((string) ($row[$columns['tail']] ?? ''));

                if ($tailRaw === '') {
                    $skipped[] = "Row {$rowNumber}: missing tail number";

                    continue;
                }

                $tail = mb_strtoupper($tailRaw);

                $operator = trim((string) ($row[$columns['operator']] ?? ''));

                if ($operator === '') {
                    $skipped[] = "Row {$rowNumber} ({$tail}): missing operator";

                    continue;
                }

                $yearRaw = trim((string) ($row[$columns['year_of_make']] ?? ''));

                if (! ctype_digit($yearRaw) || (int) $yearRaw < 1900 || (int) $yearRaw > $currentYear + 1) {
                    $skipped[] = "Row {$rowNumber} ({$tail}): invalid year of make '{$yearRaw}'";

                    continue;
                }

                $paxRaw = trim((string) ($row[$columns['max_pax']] ?? ''));

                if (! ctype_digit($paxRaw) || (int) $paxRaw < 1) {
                    $skipped[] = "Row {$rowNumber} ({$tail}): invalid max pax '{$paxRaw}'";

                    continue;
                }

                $category = null;

                if ($columns['category'] !== null) {
                    $categoryRaw = trim((string) ($row[$columns['category']] ?? ''));
                    $category = $categoryLookup[mb_strtoupper($categoryRaw)] ?? null;

                    if ($category === null) {
                        $categoryMismatches[] = $categoryRaw === ''
                            ? "Row {$rowNumber} ({$tail}): no category provided"
                            : "Row {$rowNumber} ({$tail}): category '{$categoryRaw}' doesn't match any of our categories";
                    }
                }

                $aircraftSpeedReferenceId = null;

                if ($columns['type'] !== null) {
                    $typeRaw = trim((string) ($row[$columns['type']] ?? ''));
                    $resolution = $this->resolveAircraftType($typeRaw, $typeLookup);
                    $aircraftSpeedReferenceId = $resolution['id'];

                    switch ($resolution['status']) {
                        case 'exact':
                            // Matched cleanly — nothing to report.
                            break;
                        case 'contains':
                            $typeContainsMatches[] = "Row {$rowNumber} ({$tail}): type '{$typeRaw}' matched via contains-fallback to '{$resolution['matchedName']}'";
                            break;
                        case 'ambiguous':
                            $typeAmbiguous[] = "Row {$rowNumber} ({$tail}): type '{$typeRaw}' matches more than one aircraft_speed_reference entry (".implode(', ', $resolution['candidates']).') — left null for manual review';
                            break;
                        case 'blank':
                            $typeMismatches[] = "Row {$rowNumber} ({$tail}): no type provided";
                            break;
                        case 'none':
                            $typeMismatches[] = "Row {$rowNumber} ({$tail}): type '{$typeRaw}' has no matching aircraft_speed_reference entry, exact or partial";
                            break;
                    }
                }

                $data = [
                    'tail' => $tail,
                    'operator' => $operator,
                    'category' => $category,
                    'aircraft_speed_reference_id' => $aircraftSpeedReferenceId,
                    'year_of_make' => (int) $yearRaw,
                    'max_pax' => (int) $paxRaw,
                ];

                if ($dryRun) {
                    // Existence still needs checking so the summary's
                    // imported/updated split is accurate in a dry run.
                    $exists = Tail::whereRaw('UPPER(tail) = ?', [$tail])->exists();
                    $exists ? $updated++ : $imported++;

                    continue;
                }

                try {
                    $existing = Tail::whereRaw('UPPER(tail) = ?', [$tail])->first();

                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        Tail::create($data);
                        $imported++;
                    }
                } catch (Throwable $e) {
                    $skipped[] = "Row {$rowNumber} ({$tail}): database error — {$e->getMessage()}";
                }
            }
        });

        fclose($handle);

        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry run — no rows were written.');
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Data rows processed', $rowNumber - 1],
                ['Imported (new)', $imported],
                ['Updated (existing)', $updated],
                ['Skipped', count($skipped)],
                ['Category mismatches (row imported, category left null)', count($categoryMismatches)],
                ['Type matched via contains-fallback', count($typeContainsMatches)],
                ['Type ambiguous — multiple partial matches (needs manual review)', count($typeAmbiguous)],
                ['Type mismatches — no match at all (row imported, aircraft type left null)', count($typeMismatches)],
            ]
        );

        $this->reportList('Skipped row(s)', $skipped);
        $this->reportList('Category mismatch(es)', $categoryMismatches);
        $this->reportList('Type contains-fallback match(es)', $typeContainsMatches);
        $this->reportList('Type ambiguous match(es)', $typeAmbiguous);
        $this->reportList('Type mismatch(es) — no match at all', $typeMismatches);

        Log::info('tails:import completed', [
            'path' => $path,
            'dry_run' => $dryRun,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'category_mismatches' => $categoryMismatches,
            'type_contains_matches' => $typeContainsMatches,
            'type_ambiguous' => $typeAmbiguous,
            'type_mismatches' => $typeMismatches,
        ]);

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Prints up to 15 entries from a reason list to the console, noting the
     * full list is in storage/logs when there are more.
     */
    private function reportList(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf('%d %s — see storage/logs for the full list. First few:', count($items), $label));

        foreach (array_slice($items, 0, 15) as $item) {
            $this->line("  - {$item}");
        }
    }

    /**
     * Prints the resolved header → field mapping so it can be checked
     * against the source file before (or immediately after) rows are
     * processed.
     */
    private function showColumnMapping(array $header, array $columns, string $delimiter): void
    {
        $delimiterLabel = match ($delimiter) {
            ',' => 'comma',
            ';' => 'semicolon',
            "\t" => 'tab',
            default => $delimiter,
        };

        $this->info("Detected delimiter: {$delimiterLabel}");
        $this->info('CSV headers found: '.implode(' | ', array_map(fn ($h) => $h === '' ? '(blank)' : $h, $header)));

        $rows = [];

        foreach ($columns as $field => $index) {
            $rows[] = [
                $field,
                $index === null ? '(not found — left null for every row)' : ($header[$index] === '' ? '(blank header, column '.($index + 1).')' : $header[$index]),
            ];
        }

        $this->table(['Tail field', 'CSV column'], $rows);
    }

    /**
     * Matches header cells to column indexes for the five keyword-based
     * fields, then resolves 'category' separately since this dataset's
     * category column has no header label at all (a trailing ';' with
     * nothing after it in the source file).
     *
     * @return array<string, int|null>
     */
    private function resolveColumns(array $header): array
    {
        $normalized = array_map(
            static fn ($cell) => preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $cell))),
            $header
        );

        $columns = [];
        $claimed = [];

        foreach (self::HEADER_KEYWORDS as $field => $keywords) {
            $index = $this->findColumnByKeywords($normalized, $keywords, $claimed);
            $columns[$field] = $index;

            if ($index !== null) {
                $claimed[$index] = true;
            }
        }

        $columns['category'] = $this->resolveCategoryIndex($normalized, $claimed);

        return $columns;
    }

    /**
     * A header literally named "category" wins if present. Otherwise, this
     * dataset's real header row (Company;Type;Reg.nr.;PAX;YOM;) has a
     * trailing delimiter with no label for its 6th column, which holds the
     * category values — so a single unclaimed column left over after the
     * other five are matched is assumed to be it. If more than one column
     * is left unclaimed, we don't guess.
     */
    private function resolveCategoryIndex(array $normalized, array $claimed): ?int
    {
        $named = $this->findColumnByKeywords($normalized, ['category'], $claimed);

        if ($named !== null) {
            return $named;
        }

        $leftover = array_values(array_diff(array_keys($normalized), array_keys($claimed)));

        return count($leftover) === 1 ? $leftover[0] : null;
    }

    /**
     * @param  array<int, bool>  $exclude  column indexes to skip (already claimed by another field)
     */
    private function findColumnByKeywords(array $normalizedHeader, array $keywords, array $exclude = []): ?int
    {
        foreach ($keywords as $keyword) {
            foreach ($normalizedHeader as $index => $cell) {
                if (! isset($exclude[$index]) && str_contains($cell, $keyword)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string> uppercased category value => canonical Tail::CATEGORIES value
     */
    private function buildCategoryLookup(): array
    {
        $lookup = [];

        foreach (Tail::CATEGORIES as $category) {
            $lookup[mb_strtoupper($category)] = $category;
        }

        return $lookup;
    }

    /**
     * Resolves a CSV Type value against aircraft_speed_reference in two
     * passes: an exact (case-insensitive) match first, then — since the
     * CSV frequently uses a short model name ("Challenger 300") where
     * aircraft_speed_reference spells out the manufacturer too
     * ("Bombardier Challenger 300") — a boundary-aware substring match
     * tried in both directions (see boundaryAwareContains()). A substring
     * match is only trusted when it's unique; if it's satisfied by more
     * than one type_name (e.g. a bare "Citation" would match every Cessna
     * Citation variant), that's ambiguous and left for manual review
     * rather than guessed.
     *
     * @param  array<string, int>  $typeLookup  uppercased type_name => id
     * @return array{id: int|null, status: 'blank'|'exact'|'contains'|'ambiguous'|'none', matchedName?: string, candidates?: array<int, string>}
     */
    private function resolveAircraftType(string $typeRaw, array $typeLookup): array
    {
        $typeUpper = mb_strtoupper($typeRaw);

        if ($typeUpper === '') {
            return ['id' => null, 'status' => 'blank'];
        }

        if (isset($typeLookup[$typeUpper])) {
            return ['id' => $typeLookup[$typeUpper], 'status' => 'exact'];
        }

        $candidates = [];

        foreach ($typeLookup as $refUpper => $id) {
            if ($this->boundaryAwareContains($refUpper, $typeUpper) || $this->boundaryAwareContains($typeUpper, $refUpper)) {
                $candidates[$refUpper] = $id;
            }
        }

        if (count($candidates) === 1) {
            return ['id' => array_values($candidates)[0], 'status' => 'contains', 'matchedName' => array_key_first($candidates)];
        }

        if (count($candidates) > 1) {
            return ['id' => null, 'status' => 'ambiguous', 'candidates' => array_keys($candidates)];
        }

        return ['id' => null, 'status' => 'none'];
    }

    /**
     * str_contains(), but a match immediately followed by another digit
     * doesn't count — otherwise "Challenger 350" reads as contained in
     * "Bombardier Challenger 3500" (it's the first four characters of
     * "3500"), which is a different aircraft, not a naming variation of
     * the same one. A match at the very end of the haystack, or followed
     * by anything that isn't 0-9 ("Airbus ACJ319" inside "Airbus
     * ACJ319neo", stopping at "n"), still counts. Only the trailing
     * boundary is checked — a leading digit run-on ("50" inside "350")
     * isn't this codebase's concern since needle is always a whole model
     * name/number, never a bare numeric fragment.
     *
     * Byte-based (strpos/ctype_digit) rather than mb_-aware: every
     * type_name and CSV Type value in this data is plain ASCII.
     */
    private function boundaryAwareContains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $needleLength = strlen($needle);
        $haystackLength = strlen($haystack);
        $offset = 0;

        while (($position = strpos($haystack, $needle, $offset)) !== false) {
            $nextCharPosition = $position + $needleLength;

            if ($nextCharPosition >= $haystackLength || ! ctype_digit($haystack[$nextCharPosition])) {
                return true;
            }

            $offset = $position + 1;
        }

        return false;
    }

    /**
     * @return array<string, int> uppercased type_name => aircraft_speed_reference id
     */
    private function buildTypeLookup(): array
    {
        $lookup = [];

        foreach (AircraftSpeedReference::query()->pluck('id', 'type_name') as $typeName => $id) {
            $lookup[mb_strtoupper((string) $typeName)] = $id;
        }

        return $lookup;
    }

    /**
     * Sniffs the delimiter from the first line — this dataset uses ';'
     * (semicolon-locale export) rather than ','.
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n") ?: '';
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }
}
