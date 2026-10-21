<?php

use App\Livewire\Dashboard\ActivityLog;
use App\Models\DropLink;
use App\Models\DropLinkAccess;
use App\Models\User;
use Livewire\Livewire;

function logAccess(DropLink $link, string $event, string $ip, ?string $createdAt = null): DropLinkAccess
{
    return DropLinkAccess::query()->forceCreate([
        'droplink_id' => $link->id,
        'event' => $event,
        'ip_address' => $ip,
        'user_agent' => 'Pest Agent/1.0',
        'created_at' => $createdAt ?? now(),
    ]);
}

it('lists only accesses to the owner links', function () {
    $user = User::factory()->create();
    $own = DropLink::factory()->create(['user_id' => $user->id, 'label' => 'Link saya']);
    logAccess($own, 'view', '10.0.0.1');

    $other = User::factory()->create();
    $foreign = DropLink::factory()->create(['user_id' => $other->id]);
    logAccess($foreign, 'view', '10.9.9.9');

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->assertSee('10.0.0.1')
        ->assertSee('Link saya')
        ->assertDontSee('10.9.9.9');
});

it('filters by event', function () {
    $user = User::factory()->create();
    $link = DropLink::factory()->create(['user_id' => $user->id]);

    logAccess($link, 'view', '10.0.0.1');
    logAccess($link, 'download', '10.0.0.2');

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->set('event', 'download')
        ->assertSee('10.0.0.2')
        ->assertDontSee('10.0.0.1');
});

it('filters by date', function () {
    $user = User::factory()->create();
    $link = DropLink::factory()->create(['user_id' => $user->id]);

    logAccess($link, 'view', '10.0.0.1');
    logAccess($link, 'view', '10.0.0.2', now()->subDays(3)->toDateTimeString());

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->set('date', now()->toDateString())
        ->assertSee('10.0.0.1')
        ->assertDontSee('10.0.0.2');
});

it('ignores an invalid date filter', function () {
    $user = User::factory()->create();
    $link = DropLink::factory()->create(['user_id' => $user->id]);
    logAccess($link, 'view', '10.0.0.1');

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->set('date', 'bukan-tanggal')
        ->assertSee('10.0.0.1');
});

it('clears the filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->set('event', 'download')
        ->set('date', now()->toDateString())
        ->call('clearFilters')
        ->assertSet('event', '')
        ->assertSet('date', '');
});

it('shows the empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ActivityLog::class)
        ->assertSee(__('dashboard.activity.empty'));
});
