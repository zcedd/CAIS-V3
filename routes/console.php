<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('assistances:notify-stale')
    ->daily()
    ->withoutOverlapping();

Schedule::command('workflow-tasks:notify-due')
    ->daily()
    ->withoutOverlapping();
