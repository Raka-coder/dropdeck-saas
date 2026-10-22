<?php

use App\Models\Board;
use App\Models\User;
use Livewire\Livewire;

it('archives and restores a board from the kanban page', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('archiveBoard')
        ->assertRedirect(route('boards.index'));

    expect($board->fresh()->archived_at)->not->toBeNull();

    Livewire::test('pages::boards.show', ['board' => $board->fresh()])
        ->call('restoreBoard');

    expect($board->fresh()->archived_at)->toBeNull();
});

it('deletes a board from the kanban page', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('confirmDeleteBoard')
        ->call('deleteBoard')
        ->assertRedirect(route('boards.index'));

    expect(Board::query()->find($board->id))->toBeNull();
});
