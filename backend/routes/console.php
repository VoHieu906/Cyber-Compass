<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. Locally the `scheduler` compose service runs `schedule:work`; on AWS an
| `schedule:work` container in the worker task does the same (D38, docs/ARCHITECTURE.md §8).
*/

// Learning reminders at 18:00 in each learner's timezone (D34). Hourly catches every offset.
Schedule::command('notifications:send-reminders')->hourly()->withoutOverlapping()->onOneServer();
