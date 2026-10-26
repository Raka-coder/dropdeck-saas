<?php

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
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

it('renders attachment thumbnails on kanban cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $column = BoardColumn::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_id' => $board->id,
        'column_id' => $column->id,
        'user_id' => $user->id,
    ]);
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'thumbnail_path' => "users/{$user->id}/cards/{$card->id}/thumbs/test.webp",
    ]);
    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->assertSee(route('attachments.thumbnail', $attachment), false);
});
