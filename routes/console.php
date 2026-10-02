<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work (requires one cron entry: * * * * * php artisan schedule:run)
|--------------------------------------------------------------------------
| Each command only finds what is due and queues the actual sending, so the
| scheduler stays fast and the queue worker does the slow work.
*/
Schedule::command('itizan:dose-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('itizan:appointment-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('itizan:process-subscriptions')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('itizan:lapsed-prescriptions')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('itizan:stale-appointments')->hourly()->withoutOverlapping()->onOneServer();

// Keep the database-backed queue tidy.
Schedule::command('queue:prune-failed --hours=720')->daily();
