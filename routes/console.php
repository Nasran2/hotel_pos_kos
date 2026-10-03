<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('demo:reset')->everyMinute()
    ->when(fn (): bool => (bool) config('demo.enabled'))
    ->withoutOverlapping(30)->onOneServer();
