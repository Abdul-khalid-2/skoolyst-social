<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tokens:check-expiry')->daily();
// Note: do not use withoutOverlapping() here — Facebook calls can exceed 1 minute and would skip the next run.
Schedule::command('posts:publish-scheduled')->everyMinute();
