<?php

use App\Modules\Admin\Services\QueueMetrics;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Monolog\Formatter\JsonFormatter;

/*
| The queue figures behind the CloudWatch backlog and failed-job alarms (D48).
*/

it('reports pending, reserved and failed jobs and the age of the oldest pending one', function () {
    $this->travelTo(now()->startOfMinute());
    $job = fn (int $availableAt, ?int $reservedAt = null) => [
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => $reservedAt, 'available_at' => $availableAt, 'created_at' => $availableAt,
    ];
    DB::table('jobs')->insert([$job(now()->subSeconds(90)->getTimestamp()), $job(now()->subSeconds(10)->getTimestamp()), $job(now()->getTimestamp(), now()->getTimestamp())]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'Boom', 'failed_at' => now(),
    ]);

    expect(app(QueueMetrics::class)->snapshot())->toBe([
        'pending' => 2,
        'reserved' => 1,
        'failed' => 1,
        'oldest_pending_seconds' => 90,
    ]);
});

it('reports an empty queue as zeros', function () {
    expect(app(QueueMetrics::class)->snapshot())->toBe(['pending' => 0, 'reserved' => 0, 'failed' => 0, 'oldest_pending_seconds' => 0]);
});

it('logs the figures every minute, once across servers', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'queue-metrics');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and(config('logging.channels.metrics.formatter'))->toBe(JsonFormatter::class);
});
