<?php

use App\Actions\Cards\CreateCard;
use App\Actions\Labels\CreateLabel;
use App\Actions\Labels\DeleteLabel;
use App\Actions\Labels\UpdateLabel;
use App\Models\Board;
use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('updates a label name and color', function () {
    $board = Board::factory()->create();
    $label = Label::factory()->create(['board_id' => $board->id, 'name' => 'Lama', 'color' => '#111111']);

    $updated = app(UpdateLabel::class)->handle($board, $label, 'Baru', '#22cc88');

    expect($updated->name)->toBe('Baru')
        ->and($updated->color)->toBe('#22cc88')
        ->and($label->fresh()->name)->toBe('Baru');
});

it('rejects updating a label that belongs to another board', function () {
    $board = Board::factory()->create();
    $foreignLabel = Label::factory()->create();

    expect(fn () => app(UpdateLabel::class)->handle($board, $foreignLabel, 'Baru', '#22cc88'))
        ->toThrow(ModelNotFoundException::class);
});

it('deletes a label and detaches it from its cards', function () {
    $board = Board::factory()->create();
    $column = $board->columns()->create(['title' => 'Todo', 'position' => 1024]);
    $card = app(CreateCard::class)->handle($board, $column, 'Kartu');
    $label = Label::factory()->create(['board_id' => $board->id]);
    $card->labels()->attach($label);

    app(DeleteLabel::class)->handle($board, $label);

    $this->assertDatabaseMissing('labels', ['id' => $label->id]);
    $this->assertDatabaseMissing('card_label', ['label_id' => $label->id, 'card_id' => $card->id]);
});

it('updates and deletes labels from the board page', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $label = Label::factory()->create(['board_id' => $board->id, 'name' => 'Lama']);

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('editLabel', $label->id)
        ->assertSet('editingLabelId', $label->id)
        ->set('labelForm.name', 'Baru')
        ->set('labelForm.color', '#00ff00')
        ->call('updateLabel')
        ->assertHasNoErrors();

    expect($label->fresh()->name)->toBe('Baru')
        ->and($label->fresh()->color)->toBe('#00ff00');

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('deleteLabel', $label->id);

    $this->assertDatabaseMissing('labels', ['id' => $label->id]);
});

it('allows keeping the same label name when editing', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $label = Label::factory()->create(['board_id' => $board->id, 'name' => 'Sama']);

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->call('editLabel', $label->id)
        ->set('labelForm.color', '#abcdef')
        ->call('updateLabel')
        ->assertHasNoErrors();

    expect($label->fresh()->color)->toBe('#abcdef');
});

it('authorizes label changes to the board owner only', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $owner->id]);
    $label = Label::factory()->create(['board_id' => $board->id]);

    expect(Gate::forUser($owner)->allows('update', $label))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $label))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $label))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $label))->toBeFalse();
});

it('enforces the configured label limit per board', function () {
    config(['dropdeck.limits.max_labels' => 2]);

    $board = Board::factory()->create();

    app(CreateLabel::class)->handle($board, 'Satu', '#111111');
    app(CreateLabel::class)->handle($board, 'Dua', '#222222');

    expect(fn () => app(CreateLabel::class)->handle($board, 'Tiga', '#333333'))
        ->toThrow(ValidationException::class);
});

it('shows the label limit error on the board page', function () {
    config(['dropdeck.limits.max_labels' => 1]);

    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    Label::factory()->create(['board_id' => $board->id]);

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $board])
        ->set('labelForm.name', 'Baru')
        ->call('createLabel')
        ->assertHasErrors('labelForm.name');
});
