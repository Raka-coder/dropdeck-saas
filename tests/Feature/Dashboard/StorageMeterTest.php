<?php

use App\Livewire\Dashboard\StorageMeter;
use App\Models\User;
use App\Support\Bytes;
use Livewire\Livewire;

function meterFor(int $usedMegabytes, int $quotaMegabytes): array
{
    $user = User::factory()->create([
        'storage_used_bytes' => Bytes::megabytes($usedMegabytes),
        'storage_quota_bytes' => Bytes::megabytes($quotaMegabytes),
    ]);

    test()->actingAs($user);

    return Livewire::test(StorageMeter::class)->instance()->usage;
}

it('reports a healthy usage below the warning threshold', function () {
    $usage = meterFor(500, 2048);

    expect($usage['level'])->toBe('ok')
        ->and($usage['percent'])->toBe(24.4)
        ->and($usage['remainingLabel'])->toBe(Bytes::human(Bytes::megabytes(1548)));
});

it('warns at or above 80 percent', function () {
    $usage = meterFor(1700, 2048);

    expect($usage['level'])->toBe('warning')
        ->and($usage['barPercent'])->toBe(83.0);
});

it('shows the full state at or above the quota', function () {
    $usage = meterFor(2048, 2048);

    expect($usage['level'])->toBe('full')
        ->and($usage['percent'])->toBe(100.0)
        ->and($usage['remainingLabel'])->toBe('0 B');
});

it('clamps the bar when usage exceeds the quota', function () {
    $usage = meterFor(3000, 2048);

    expect($usage['percent'])->toBeGreaterThan(100.0)
        ->and($usage['barPercent'])->toBe(100.0)
        ->and($usage['level'])->toBe('full');
});

it('renders the warning copy on the dashboard', function () {
    $user = User::factory()->create([
        'storage_used_bytes' => Bytes::megabytes(1700),
        'storage_quota_bytes' => Bytes::megabytes(2048),
    ]);

    $this->actingAs($user);

    Livewire::test(StorageMeter::class)
        ->assertSee(__('dashboard.storage.levels.warning'))
        ->assertSee(__('dashboard.storage.warning', ['percent' => 80]));
});
