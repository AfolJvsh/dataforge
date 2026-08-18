<?php

use App\Jobs\PruneExpiredImports;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PruneExpiredImports)->hourly()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
