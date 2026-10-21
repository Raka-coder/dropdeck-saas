<?php

use App\Models\Board;
use App\Models\DropLink;
use App\Models\User;

it('renders the dashboard widgets for an authenticated user', function () {
    $user = User::factory()->create([
        'storage_used_bytes' => 1024 * 1024,
        'storage_quota_bytes' => 2048 * 1024 * 1024,
    ]);
    Board::factory()->create(['user_id' => $user->id]);
    DropLink::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.stats.boards'))
        ->assertSee(__('dashboard.storage.title'))
        ->assertSee(__('dashboard.activity.title'))
        ->assertSee(__('dashboard.activity.empty'));
});
