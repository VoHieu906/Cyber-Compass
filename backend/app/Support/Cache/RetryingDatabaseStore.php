<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Database\QueryException;

/**
 * The database cache store (D6), retrying a write that MySQL chose as a deadlock victim.
 *
 * Two requests of one user that start together (a page loading its lists in parallel) both create
 * the rate limiter's cache row; InnoDB's gap locks can deadlock those `insert ignore` statements,
 * and one request failed with a 500 (seen in CI, F3). A deadlock or lock-wait timeout leaves nothing
 * half written, so running the statement again is safe. Anything else is thrown as before.
 */
final class RetryingDatabaseStore extends DatabaseStore
{
    /** MySQL error codes: deadlock, lock wait timeout. */
    private const RETRYABLE = [1213, 1205];

    private const ATTEMPTS = 3;

    public function put($key, $value, $seconds)
    {
        return self::retrying(fn () => parent::put($key, $value, $seconds));
    }

    public function add($key, $value, $seconds)
    {
        return self::retrying(fn () => parent::add($key, $value, $seconds));
    }

    public function increment($key, $value = 1)
    {
        return self::retrying(fn () => parent::increment($key, $value));
    }

    public function decrement($key, $value = 1)
    {
        return self::retrying(fn () => parent::decrement($key, $value));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    public static function retrying(Closure $write): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $write();
            } catch (QueryException $e) {
                if ($attempt >= self::ATTEMPTS || ! in_array($e->errorInfo[1] ?? null, self::RETRYABLE, true)) {
                    throw $e;
                }
                usleep(random_int(5, 25) * 1000 * $attempt);
            }
        }
    }
}
