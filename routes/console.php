<?php

use App\Jobs\SendEventReminderEmails;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendEventReminderEmails('24hours'))->dailyAt('08:00');
Schedule::job(new SendEventReminderEmails('3days'))->dailyAt('08:00');
