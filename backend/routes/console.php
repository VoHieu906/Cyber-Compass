<?php

use App\Modules\Admin\Services\QueueMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. Locally the `scheduler` compose service runs `schedule:work`; on AWS an
| `schedule:work` container in the worker task does the same (D38, docs/ARCHITECTURE.md §8).
*/

// Learning reminders at 18:00 in each learner's timezone (D34). Hourly catches every offset.
Schedule::command('notifications:send-reminders')->hourly()->withoutOverlapping()->onOneServer();

// The queue's backlog and failed jobs for the CloudWatch alarms (D48): one JSON line a minute on
// stderr. A closure, not a command, so it runs inside schedule:run, whose output schedule:work
// passes on to the container log.
Schedule::call(fn (QueueMetrics $metrics) => Log::channel('metrics')->info('queue', $metrics->snapshot()))
    ->everyMinute()->name('queue-metrics')->withoutOverlapping()->onOneServer();
