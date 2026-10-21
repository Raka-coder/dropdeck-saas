<?php

use App\Actions\Columns\CreateColumn;
use App\Actions\Columns\DeleteColumn;
use App\Actions\Columns\MoveColumn;
use App\Actions\Columns\RenameColumn;
use App\Models\Board;
use App\Models\Card;
use App\Support\Position;

it('appends new columns with increasing positions', function () {
    $board = Board::factory()->create();

    $first = app(CreateColumn::class)->handle($board, 'Todo');
    $second = app(CreateColumn::class)->handle($board, 'Doing');

    expect($first->position)->toBe(Position::GAP)
        ->and($second->position)->toBe(Position::GAP * 2);
});

it('renames a column', function () {
    $column = Board::factory()->create()->columns()->create(['title' => 'Lama', 'position' => 1024]);

    $renamed = app(RenameColumn::class)->handle($column, 'Baru');

    expect($renamed->title)->toBe('Baru');
});

it('moves a column to the requested index', function () {
    $board = Board::factory()->create();
    $todo = app(CreateColumn::class)->handle($board, 'Todo');
    app(CreateColumn::class)->handle($board, 'Doing');
    app(CreateColumn::class)->handle($board, 'Done');

    app(MoveColumn::class)->handle($todo, 2);

    $titles = $board->columns()->orderBy('position')->orderBy('id')->pluck('title')->all();

    expect($titles)->toBe(['Doing', 'Done', 'Todo']);
});

it('rebalances columns when there is no room left', function () {
    $board = Board::factory()->create();
    $board->columns()->create(['title' => 'A', 'position' => 1]);
    $board->columns()->create(['title' => 'B', 'position' => 2]);
    $third = $board->columns()->create(['title' => 'C', 'position' => 3]);

    app(MoveColumn::class)->handle($third, 1);

    $positions = $board->columns()->orderBy('position')->orderBy('id')->pluck('position')->all();

    expect($positions)->toBe([Position::GAP, Position::GAP + 512, Position::GAP * 2]);
});

it('deletes a column and its cards', function () {
    $board = Board::factory()->create();
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);
    Card::factory()->create([
        'user_id' => $board->user_id,
        'board_id' => $board->id,
        'column_id' => $column->id,
        'position' => 1024,
    ]);

    app(DeleteColumn::class)->handle($column);

    $this->assertDatabaseMissing('board_columns', ['id' => $column->id]);
    $this->assertDatabaseMissing('cards', ['column_id' => $column->id]);
});
