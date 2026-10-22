<?php

use App\Jobs\PruneAccessLogs;
use App\Models\DropLink;
use App\Models\DropLinkAccess;

it('deletes access logs older than the retention window', function () {
    $retention = (int) config('dropdeck.limits.log_retention_days');
    $link = DropLink::factory()->create();

    $old = DropLinkAccess::query()->forceCreate([
        'droplink_id' => $link->id,
        'event' => 'view',
        'ip_address' => '10.0.0.1',
        'created_at' => now()->subDays($retention + 1),
    ]);
    $fresh = DropLinkAccess::query()->forceCreate([
        'droplink_id' => $link->id,
        'event' => 'view',
        'ip_address' => '10.0.0.2',
        'created_at' => now()->subDays($retention - 1),
    ]);

    $deleted = (new PruneAccessLogs)->handle();

    expect($deleted)->toBe(1)
        ->and(DropLinkAccess::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(DropLinkAccess::query()->whereKey($fresh->id)->exists())->toBeTrue();
});
