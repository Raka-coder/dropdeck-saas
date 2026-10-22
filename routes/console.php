<?php

use App\Jobs\PruneAccessLogs;
use App\Jobs\PruneExpiredDropLinks;
use App\Jobs\ReconcileStorageUsage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PruneExpiredDropLinks)->daily();
Schedule::job(new PruneAccessLogs)->daily();
Schedule::job(new ReconcileStorageUsage)->daily();
Schedule::command('queue:prune-failed')->weekly();
