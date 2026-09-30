<?php

namespace App\Services;

/**
 * Generates "MM-YYYY/<marker>NN" reference numbers — Contract's
 * reference_number, QuoteRequest's quotation_reference — that restart
 * from 01 each calendar month.
 *
 * Finds the highest existing suffix for the current month rather than
 * counting matching rows. Counting silently reused a number whenever a
 * row was deleted (or a previous collision skipped one), because the
 * count of remaining rows no longer matched the highest number actually
 * assigned — e.g. with "Q01" and "Q03" on file (Q02 deleted), the count
 * is 2, so count+1 regenerates "Q03" and collides with the row that
 * already has it. Taking the max suffix instead always picks a number
 * one past the highest ever assigned, gap or no gap.
 *
 * This alone doesn't make the read-then-write atomic — two concurrent
 * requests can still read the same "highest suffix" before either
 * writes. Callers are responsible for running the write inside a
 * transaction and retrying both the write and this generator on a
 * UniqueConstraintViolationException; see
 * App\Http\Controllers\Concerns\RetriesOnReferenceCollision.
 */
class SequentialReferenceGenerator
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    public static function next(string $modelClass, string $column, string $marker = ''): string
    {
        $prefix = now()->format('m-Y');

        $maxSuffix = $modelClass::query()
            ->where($column, 'like', "{$prefix}/{$marker}%")
            ->lockForUpdate()
            ->pluck($column)
            ->map(function (string $reference) {
                preg_match('/(\d+)$/', $reference, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return sprintf('%s/%s%02d', $prefix, $marker, $maxSuffix + 1);
    }
}
