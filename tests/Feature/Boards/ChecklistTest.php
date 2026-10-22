<?php

use App\Actions\Cards\CreateCard;
use App\Actions\Checklists\AddItem;
use App\Actions\Checklists\UpdateItem;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * @return array{0: Board, 1: Card}
 */
function checklistCard(): array
{
    $board = Board::factory()->create();
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);
    $card = app(CreateCard::class)->handle($board, $column, 'Kartu');

    return [$board, $card];
}

it('updates a checklist item content', function () {
    [, $card] = checklistCard();

    $item = app(AddItem::class)->handle($card, 'Langkah lama');
    $updated = app(UpdateItem::class)->handle($item, 'Langkah baru');

    expect($updated->content)->toBe('Langkah baru')
        ->and($item->fresh()->content)->toBe('Langkah baru');
});

it('enforces the configured checklist limit per card', function () {
    config(['dropdeck.limits.max_checklist' => 2]);

    [, $card] = checklistCard();

    app(AddItem::class)->handle($card, 'Satu');
    app(AddItem::class)->handle($card, 'Dua');

    expect(fn () => app(AddItem::class)->handle($card, 'Tiga'))
        ->toThrow(ValidationException::class);
});

it('updates a checklist item from the card modal', function () {
    $user = User::factory()->create();
    [$board, $card] = checklistCard();
    $board->update(['user_id' => $user->id]);
    $card->update(['user_id' => $user->id]);

    $item = app(AddItem::class)->handle($card, 'Langkah lama');

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('openCard', $card->id)
        ->call('editChecklistItem', $item->id)
        ->assertSet('editingChecklistItemId', $item->id)
        ->set('checklistForm.content', 'Langkah baru')
        ->call('updateChecklistItem')
        ->assertHasNoErrors();

    expect($item->fresh()->content)->toBe('Langkah baru');
});

it('shows the checklist limit error in the card modal', function () {
    config(['dropdeck.limits.max_checklist' => 1]);

    $user = User::factory()->create();
    [$board, $card] = checklistCard();
    $board->update(['user_id' => $user->id]);
    $card->update(['user_id' => $user->id]);

    app(AddItem::class)->handle($card, 'Satu');

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('checklistForm.content', 'Dua')
        ->call('addChecklistItem')
        ->assertHasErrors('checklistForm.content');
});
