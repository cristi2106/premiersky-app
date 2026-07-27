<?php

namespace App\Console\Commands;

use App\Models\AircraftSpeedReference;
use Illuminate\Console\Command;

class ImportAircraftSpeeds extends Command
{
    protected $signature = 'aircraft-speeds:import {path : Path to the CSV file}';

    protected $description = 'Import aircraft cruise speed reference data from a CSV, upserting records matched on type_name';

    /**
     * Header keywords used to identify the type-name and cruise-speed
     * columns when a header row is present. Checked in order.
     */
    private const NAME_HEADER_KEYWORDS = ['type_name', 'typename', 'aircrafttype', 'aircraft', 'model', 'type', 'name'];

    private const SPEED_HEADER_KEYWORDS = ['cruisespeedknots', 'cruisespeed', 'speedknots', 'knots', 'speed', 'kts'];

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

        $firstRow = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($firstRow === false) {
            $this->error('CSV file appears to be empty.');
            fclose($handle);

            return self::FAILURE;
        }

        [$nameIndex, $speedIndex, $firstRowIsHeader] = $this->resolveColumns($firstRow);

        if ($nameIndex === null || $speedIndex === null) {
            $this->error('Could not determine which columns hold the aircraft type name and cruise speed.');
            fclose($handle);

            return self::FAILURE;
        }

        if (! $firstRowIsHeader) {
            rewind($handle);
        }

        $rowNumber = $firstRowIsHeader ? 1 : 0;
        $imported = 0;
        $skipped = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rowNumber++;

            if (! array_key_exists($nameIndex, $row) || ! array_key_exists($speedIndex, $row)) {
                $skipped[] = "Row {$rowNumber}: missing columns";

                continue;
            }

            $typeName = trim((string) $row[$nameIndex]);
            $speedRaw = trim((string) $row[$speedIndex]);

            if ($typeName === '') {
                $skipped[] = "Row {$rowNumber}: missing aircraft type name";

                continue;
            }

            if (! is_numeric($speedRaw)) {
                $skipped[] = "Row {$rowNumber} ({$typeName}): non-numeric cruise speed '{$speedRaw}'";

                continue;
            }

            $cruiseSpeedKnots = (int) round((float) $speedRaw);

            if ($cruiseSpeedKnots <= 0) {
                $skipped[] = "Row {$rowNumber} ({$typeName}): cruise speed must be positive";

                continue;
            }

            AircraftSpeedReference::updateOrCreate(
                ['type_name' => $typeName],
                ['cruise_speed_knots' => $cruiseSpeedKnots],
            );

            $imported++;
        }

        fclose($handle);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported (created or updated)', $imported],
                ['Skipped', count($skipped)],
            ]
        );

        if ($skipped !== []) {
            $this->warn(sprintf('%d row(s) skipped. First few:', count($skipped)));

            foreach (array_slice($skipped, 0, 15) as $reason) {
                $this->line("  - {$reason}");
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Works out which column holds the aircraft type name and which holds
     * the cruise speed, and whether the first row is a header (to be
     * skipped) or already a data row.
     *
     * A row is treated as a header only if none of its cells are numeric —
     * a real data row always has a numeric speed value. When a header is
     * present, columns are matched by keyword; otherwise the first numeric
     * cell is assumed to be the speed and the first non-numeric cell the
     * name.
     *
     * @return array{0: int|null, 1: int|null, 2: bool} [nameIndex, speedIndex, firstRowIsHeader]
     */
    private function resolveColumns(array $firstRow): array
    {
        $numericIndexes = [];
        $textIndexes = [];

        foreach ($firstRow as $index => $cell) {
            if (is_numeric(trim((string) $cell))) {
                $numericIndexes[] = $index;
            } else {
                $textIndexes[] = $index;
            }
        }

        if ($numericIndexes !== []) {
            return [$textIndexes[0] ?? null, $numericIndexes[0], false];
        }

        $nameIndex = $this->findColumnByKeywords($firstRow, self::NAME_HEADER_KEYWORDS);
        $speedIndex = $this->findColumnByKeywords($firstRow, self::SPEED_HEADER_KEYWORDS);

        if ($nameIndex === null && $speedIndex === null && count($firstRow) === 2) {
            return [0, 1, true];
        }

        return [$nameIndex, $speedIndex, true];
    }

    /**
     * Sniffs the delimiter from the first line — spreadsheet exports from
     * semicolon-locale regions commonly use ';' instead of ','.
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

    private function findColumnByKeywords(array $row, array $keywords): ?int
    {
        $normalized = array_map(
            static fn ($cell) => preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $cell))),
            $row
        );

        foreach ($keywords as $keyword) {
            foreach ($normalized as $index => $cell) {
                if (str_contains($cell, $keyword)) {
                    return $index;
                }
            }
        }

        return null;
    }
}
