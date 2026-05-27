<?php

declare(strict_types=1);

use App\Jobs\AutoJoinScheduledMeetingsJob;
use App\Jobs\FlagDelayedTranscriptsJob;
use App\Jobs\PurgeSoftDeletedMeetingsJob;
use App\Jobs\RefreshExpiredOAuthTokensJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RefreshExpiredOAuthTokensJob())->everyFifteenMinutes();
Schedule::job(new AutoJoinScheduledMeetingsJob())->everyMinute();
Schedule::job(new FlagDelayedTranscriptsJob())->everyFiveMinutes();
Schedule::job(new PurgeSoftDeletedMeetingsJob())->dailyAt('02:00');
