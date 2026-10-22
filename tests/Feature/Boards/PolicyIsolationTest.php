<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('authorizes column actions to the board owner only', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $owner->id]);
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);

    expect(Gate::forUser($owner)->allows('update', $column))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $column))->toBeTrue()
        ->and(Gate::forUser($other)->allows('view', $column))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $column))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $column))->toBeFalse();
});
