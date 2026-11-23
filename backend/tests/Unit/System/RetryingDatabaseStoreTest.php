<?php

use App\Support\Cache\RetryingDatabaseStore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/* The database cache retries writes MySQL chose as deadlock victims (seen in CI, F3). */

function deadlock(int $code = 1213): QueryException
{
    $pdo = new PDOException('Deadlock found when trying to get lock');
    $pdo->errorInfo = ['40001', $code, 'Deadlock found when trying to get lock'];

    return new QueryException('mysql', 'insert ignore into cache', [], $pdo);
}

it('retries a deadlocked write and returns its result', function () {
    $calls = 0;
    $result = RetryingDatabaseStore::retrying(function () use (&$calls) {
        if (++$calls < 3) {
            throw deadlock();
        }

        return 'written';
    });

    expect($result)->toBe('written')->and($calls)->toBe(3);
});

it('gives up after three attempts and rethrows anything else at once', function () {
    $calls = 0;
    expect(function () use (&$calls) {
        RetryingDatabaseStore::retrying(function () use (&$calls) {
            $calls++;
            throw deadlock();
        });
    })->toThrow(QueryException::class);
    expect($calls)->toBe(3);

    $calls = 0;
    expect(function () use (&$calls) {
        RetryingDatabaseStore::retrying(function () use (&$calls) {
            $calls++;
            throw deadlock(1062);
        });
    })->toThrow(QueryException::class);
    expect($calls)->toBe(1);
});

it('is the store behind the database cache', function () {
    expect(Cache::store('database')->getStore())->toBeInstanceOf(RetryingDatabaseStore::class);
});
