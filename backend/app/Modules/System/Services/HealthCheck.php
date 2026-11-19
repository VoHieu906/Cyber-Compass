<?php

namespace App\Modules\System\Services;

use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Reports whether the API's dependencies are usable. The API answers even when the
 * database is down, so a client can tell "API unreachable" from "API degraded".
 */
final class HealthCheck
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return array{status: 'ok'|'degraded', database: 'ok'|'down', version: string}
     */
    public function run(): array
    {
        $database = $this->databaseIsReachable() ? 'ok' : 'down';

        return [
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'database' => $database,
            'version' => (string) config('app.version'),
        ];
    }

    private function databaseIsReachable(): bool
    {
        try {
            $this->db->select('select 1');

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
