<?php

use App\Actions\Cards\CreateCard;
use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\MoveCard;
use App\Actions\Cards\UpdateCard;
use App\Actions\Checklists\AddItem;
use App\Actions\Checklists\RemoveItem;
use App\Actions\Checklists\ToggleItem;
use App\Actions\Labels\AttachLabel;
use App\Actions\Labels\DetachLabel;
use App\Models\Board;
use App\Models\Card;
use App\Models\Label;
use App\Support\Position;
use Illuminate\Validation\ValidationException;

function boardWithColumn(): array
{
    $board = Board::factory()->create();
    $column = $board->columns()->create(['title' => 'Todo', 'position' => Position::GAP]);

    return [$board, $column];
}

it('appends a card to the bottom of its column', function () {
    [$board, $column] = boardWithColumn();

    $first = app(CreateCard::class)->handle($board, $column, 'Kartu A');
    $second = app(CreateCard::class)->handle($board, $column, 'Kartu B');

    expect($first->position)->toBe(Position::GAP)
        ->and($second->position)->toBe(Position::GAP * 2)
        ->and($first->user_id)->toBe($board->user_id);
});

it('rejects a card for a column on another board', function () {
    [$board] = boardWithColumn();
    $foreignColumn = Board::factory()->create()->columns()->create(['title' => 'X', 'position' => 1024]);

    expect(fn () => app(CreateCard::class)->handle($board, $foreignColumn, 'Nope'))
        ->toThrow(ValidationException::class);
});

it('moves a card between columns and persists the order', function () {
    [$board, $todo] = boardWithColumn();
    $doing = $board->columns()->create(['title' => 'Doing', 'position' => Position::GAP * 2]);

    app(CreateCard::class)->handle($board, $doing, 'Existing');
    $card = app(CreateCard::class)->handle($board, $todo, 'Pindah');

    app(MoveCard::class)->handle($card, $doing, 0);

    $card->refresh();

    expect($card->column_id)->toBe($doing->id)
        ->and($card->position)->toBeLessThan($doing->cards()->whereKeyNot($card->id)->value('position'));
});

it('rebalances a destination column that ran out of room', function () {
    [$board, $todo] = boardWithColumn();
    $doing = $board->columns()->create(['title' => 'Doing', 'position' => 2048]);

    Card::factory()->create([
        'user_id' => $board->user_id,
        'board_id' => $board->id,
        'column_id' => $doing->id,
        'position' => 1,
    ]);
    Card::factory()->create([
        'user_id' => $board->user_id,
        'board_id' => $board->id,
        'column_id' => $doing->id,
        'position' => 2,
    ]);

    $card = app(CreateCard::class)->handle($board, $todo, 'Pindah');

    app(MoveCard::class)->handle($card, $doing, 1);

    $positions = $doing->cards()->orderBy('position')->pluck('position')->all();

    expect($positions)->toBe([Position::GAP, Position::GAP + 512, Position::GAP * 2]);
});

it('rejects moving a card to another board', function () {
    [$board, $todo] = boardWithColumn();
    $card = app(CreateCard::class)->handle($board, $todo, 'Kartu');
    $foreignColumn = Board::factory()->create()->columns()->create(['title' => 'X', 'position' => 1024]);

    expect(fn () => app(MoveCard::class)->handle($card, $foreignColumn, 0))
        ->toThrow(ValidationException::class);
});

it('updates and deletes a card', function () {
    [$board, $column] = boardWithColumn();
    $card = app(CreateCard::class)->handle($board, $column, 'Lama');

    $updated = app(UpdateCard::class)->handle($card, 'Baru', 'Deskripsi');

    expect($updated->title)->toBe('Baru')
        ->and($updated->description)->toBe('Deskripsi');

    app(DeleteCard::class)->handle($updated);

    $this->assertDatabaseMissing('cards', ['id' => $card->id]);
});

it('attaches and detaches a label', function () {
    [$board, $column] = boardWithColumn();
    $card = app(CreateCard::class)->handle($board, $column, 'Kartu');
    $label = $board->labels()->create(['name' => 'Urgent', 'color' => '#ff0000']);

    app(AttachLabel::class)->handle($card, $label);
    expect($card->labels()->count())->toBe(1);

    app(DetachLabel::class)->handle($card, $label);
    expect($card->labels()->count())->toBe(0);
});

it('rejects attaching a label from another board', function () {
    [$board, $column] = boardWithColumn();
    $card = app(CreateCard::class)->handle($board, $column, 'Kartu');
    $foreignLabel = Label::factory()->create();

    expect(fn () => app(AttachLabel::class)->handle($card, $foreignLabel))
        ->toThrow(ValidationException::class);
});

it('adds toggles and removes checklist items', function () {
    [$board, $column] = boardWithColumn();
    $card = app(CreateCard::class)->handle($board, $column, 'Kartu');

    $item = app(AddItem::class)->handle($card, 'Langkah satu');
    expect($item->is_done)->toBeFalse();

    $toggled = app(ToggleItem::class)->handle($item);
    expect($toggled->is_done)->toBeTrue();

    app(RemoveItem::class)->handle($toggled);

    $this->assertDatabaseMissing('checklist_items', ['id' => $item->id]);
});
