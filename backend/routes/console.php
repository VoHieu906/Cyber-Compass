<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. Locally the `scheduler` compose service runs `schedule:work`; on AWS an
| EventBridge rule runs `schedule:run` every minute (docs/ARCHITECTURE.md §8).
*/

// Learning reminders at 18:00 in each learner's timezone (D34). Hourly catches every offset.
Schedule::command('notifications:send-reminders')->hourly()->withoutOverlapping()->onOneServer();
