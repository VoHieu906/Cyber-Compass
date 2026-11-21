<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\DB;

/**
 * The database queue's figures for monitoring (D48). The scheduler logs them once a minute to the
 * `metrics` channel; on AWS a CloudWatch Logs metric filter turns them into the queue backlog and
 * failed-job metrics that the alarms watch.
 */
final class QueueMetrics
{
    /** @return array{pending: int, reserved: int, failed: int, oldest_pending_seconds: int} */
    public function snapshot(): array
    {
        $now = now()->getTimestamp();
        $pending = DB::table('jobs')->whereNull('reserved_at');
        $oldest = (clone $pending)->min('available_at');

        return [
            'pending' => (clone $pending)->count(),
            'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'oldest_pending_seconds' => $oldest === null ? 0 : max(0, $now - (int) $oldest),
        ];
    }
}
