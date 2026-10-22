<?php

use App\Jobs\PruneExpiredDropLinks;
use App\Models\DropLink;
use Illuminate\Support\Facades\DB;

it('deletes dead links past the retention window only', function () {
    $cutoffDays = (int) config('dropdeck.limits.link_retention_days');

    $oldRevoked = DropLink::factory()->revoked()->create();
    $oldRevoked->forceFill(['revoked_at' => now()->subDays($cutoffDays + 5)])->saveQuietly();

    $oldExpired = DropLink::factory()->create(['expires_at' => now()->subDays($cutoffDays + 5)]);
    $oldExhausted = DropLink::factory()->create([
        'max_downloads' => 1,
        'download_count' => 1,
    ]);
    DB::table('droplinks')
        ->where('id', $oldExhausted->id)
        ->update(['updated_at' => now()->subDays($cutoffDays + 5)]);

    $recentRevoked = DropLink::factory()->revoked()->create();
    $recentExpired = DropLink::factory()->create(['expires_at' => now()->subDay()]);
    $active = DropLink::factory()->create();

    $deleted = (new PruneExpiredDropLinks)->handle();

    expect($deleted)->toBe(3)
        ->and(DropLink::query()->whereKey($oldRevoked->id)->exists())->toBeFalse()
        ->and(DropLink::query()->whereKey($oldExpired->id)->exists())->toBeFalse()
        ->and(DropLink::query()->whereKey($oldExhausted->id)->exists())->toBeFalse()
        ->and(DropLink::query()->whereKey($recentRevoked->id)->exists())->toBeTrue()
        ->and(DropLink::query()->whereKey($recentExpired->id)->exists())->toBeTrue()
        ->and(DropLink::query()->whereKey($active->id)->exists())->toBeTrue();
});
