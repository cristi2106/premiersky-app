<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Retries an operation that assigns a freshly-generated sequential
 * reference (Contract::reference_number, QuoteRequest::quotation_reference)
 * when it collides with one a concurrent request assigned first in the
 * same calendar month.
 *
 * SequentialReferenceGenerator finding the next number from the highest
 * existing suffix (rather than counting rows) is enough on its own to
 * stop reusing a number a gap left behind — this only guards the
 * remaining window where two requests both read that same "highest
 * suffix" before either one has written its row, on a database that
 * either doesn't enforce SELECT ... FOR UPDATE row locks (SQLite
 * compiles it to a no-op) or interleaves them across two separate
 * transactions. When that happens, the second write to actually commit
 * fails its unique constraint — recomputing and retrying resolves it
 * exactly like a human hitting refresh would.
 */
trait RetriesOnReferenceCollision
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $attempt  Recomputes the reference *and*
     *         performs the write, wrapped together — the whole thing must
     *         be retried as a unit, since the stale reference computed
     *         before the collision is exactly what needs to change.
     * @return TReturn
     */
    private function retryOnReferenceCollision(Closure $attempt, int $maxAttempts = 5)
    {
        $attemptNumber = 0;

        while (true) {
            $attemptNumber++;

            try {
                return $attempt();
            } catch (UniqueConstraintViolationException $e) {
                if ($attemptNumber >= $maxAttempts) {
                    throw $e;
                }
            }
        }
    }
}
