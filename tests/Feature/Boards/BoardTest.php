<?php

use App\Actions\Boards\ArchiveBoard;
use App\Actions\Boards\CreateBoard;
use App\Actions\Boards\DeleteBoard;
use App\Actions\Boards\RestoreBoard;
use App\Actions\Boards\UpdateBoard;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;

it('lists only the authenticated user boards', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->create(['title' => 'Papan saya']);
    Board::factory()->create(['title' => 'Papan orang lain']);

    $this->actingAs($user)
        ->get(route('boards.index'))
        ->assertOk()
        ->assertSee('Papan saya')
        ->assertDontSee('Papan orang lain');
});

it('creates a board owned by the user', function () {
    $user = User::factory()->create();

    $board = app(CreateBoard::class)->handle($user, 'Alur Konten', 'Deskripsi', '#112233');

    expect($board->user_id)->toBe($user->id)
        ->and($board->title)->toBe('Alur Konten');

    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'user_id' => $user->id,
        'title' => 'Alur Konten',
    ]);
});

it('updates a board', function () {
    $board = Board::factory()->create(['title' => 'Lama']);

    $updated = app(UpdateBoard::class)->handle($board, 'Baru', null, '#445566');

    expect($updated->title)->toBe('Baru')
        ->and($updated->description)->toBeNull();
});

it('archives and restores a board', function () {
    $board = Board::factory()->create();

    app(ArchiveBoard::class)->handle($board);
    expect($board->fresh()->archived_at)->not->toBeNull();

    app(RestoreBoard::class)->handle($board);
    expect($board->fresh()->archived_at)->toBeNull();
});

it('deletes a board with its columns and cards', function () {
    $board = Board::factory()->create();
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);
    Card::factory()->create([
        'user_id' => $board->user_id,
        'board_id' => $board->id,
        'column_id' => $column->id,
        'position' => 1024,
    ]);

    app(DeleteBoard::class)->handle($board);

    $this->assertDatabaseMissing('boards', ['id' => $board->id]);
    $this->assertDatabaseMissing('board_columns', ['board_id' => $board->id]);
    $this->assertDatabaseMissing('cards', ['board_id' => $board->id]);
});

it('returns 404 when opening another users board', function () {
    $board = Board::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('boards.show', $board))
        ->assertNotFound();
});

it('renders the kanban board for the owner', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['title' => 'Kanban Saya']);
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);
    Card::factory()->create([
        'user_id' => $user->id,
        'board_id' => $board->id,
        'column_id' => $column->id,
        'title' => 'Kartu Pertama',
        'position' => 1024,
    ]);

    $this->actingAs($user)
        ->get(route('boards.show', $board))
        ->assertOk()
        ->assertSee('Kanban Saya')
        ->assertSee('Kartu Pertama');
});

it('redirects guests away from boards', function () {
    $this->get(route('boards.index'))->assertRedirect('/login');
});
