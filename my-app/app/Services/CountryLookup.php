<?php

namespace App\Services;

/**
 * Maps country names to ISO 3166-1 alpha-2 codes and back, from a static
 * snapshot of the OurAirports countries.csv reference list bundled at
 * resources/data/countries.csv. Used to resolve a country code for
 * manually-entered airports (which only have a free-text country name)
 * so TimezoneResolver can narrow its search the same way the import
 * command's iso_country column does.
 *
 * This is a static snapshot rather than a live download: the ISO country
 * list changes rarely, and it only feeds a nearest-point approximation,
 * so freshness here isn't worth a network call on every form save.
 */
class CountryLookup
{
    /** @var array<string, string> Country name (lowercase) => ISO code */
    private array $codesByName;

    /** @var array<string, string> ISO code => country name */
    private array $namesByCode;

    public function __construct(?string $dataPath = null)
    {
        [$this->codesByName, $this->namesByCode] = $this->load(
            $dataPath ?? resource_path('data/countries.csv')
        );
    }

    public function codeForName(string $name): ?string
    {
        return $this->codesByName[strtolower(trim($name))] ?? null;
    }

    public function nameForCode(string $code): ?string
    {
        return $this->namesByCode[strtoupper(trim($code))] ?? null;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function load(string $path): array
    {
        $codesByName = [];
        $namesByCode = [];

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return [$codesByName, $namesByCode];
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');

        if ($header !== false) {
            $codeIndex = array_search('code', $header, true);
            $nameIndex = array_search('name', $header, true);

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($codeIndex === false || $nameIndex === false) {
                    continue;
                }

                $code = $row[$codeIndex] ?? null;
                $name = $row[$nameIndex] ?? null;

                if (! $code || ! $name) {
                    continue;
                }

                $codesByName[strtolower($name)] = $code;
                $namesByCode[strtoupper($code)] = $name;
            }
        }

        fclose($handle);

        return [$codesByName, $namesByCode];
    }
}
