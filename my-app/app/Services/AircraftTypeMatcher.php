<?php

namespace App\Services;

use App\Models\AircraftSpeedReference;

/**
 * Resolves a free-text aircraft type string — a Tails CSV import "Type"
 * cell, a Quotes offer's parsed aircraft_type — against
 * aircraft_speed_reference in two passes: an exact (case-insensitive)
 * match first, then — since the input frequently uses a short model name
 * ("Challenger 300") where aircraft_speed_reference spells out the
 * manufacturer too ("Bombardier Challenger 300") — a boundary-aware
 * substring match tried in both directions (see boundaryAwareContains()).
 * A substring match is only trusted when it's unique; if it's satisfied
 * by more than one type_name (e.g. a bare "Citation" would match every
 * Cessna Citation variant), that's ambiguous and left unmatched rather
 * than guessed.
 *
 * Originally built for ImportTails (tails:import), which still uses this
 * unchanged; QuoteOfferController::generateContract() shares it for the
 * exact same reason — a wrong guess is worse than a blank field left for
 * manual review.
 */
class AircraftTypeMatcher
{
    /**
     * @return array{id: int|null, status: 'blank'|'exact'|'contains'|'ambiguous'|'none', matchedName?: string, candidates?: array<int, string>}
     */
    public function match(string $typeRaw): array
    {
        $typeUpper = mb_strtoupper(trim($typeRaw));

        if ($typeUpper === '') {
            return ['id' => null, 'status' => 'blank'];
        }

        $typeLookup = $this->buildTypeLookup();

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
     * type_name and input value this matches against is plain ASCII.
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
}
