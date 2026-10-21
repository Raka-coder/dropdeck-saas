<?php

use App\Livewire\Dashboard\Overview;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\DropLink;
use App\Models\User;
use Livewire\Livewire;

it('counts only the authenticated user data', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['user_id' => $user->id]);
    $column = BoardColumn::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'user_id' => $user->id,
        'board_id' => $board->id,
        'column_id' => $column->id,
    ]);
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
    ]);

    DropLink::factory()->create([
        'user_id' => $user->id,
        'attachment_id' => $attachment->id,
    ]);
    DropLink::factory()->revoked()->create([
        'user_id' => $user->id,
        'attachment_id' => $attachment->id,
    ]);
    DropLink::factory()->expired()->create([
        'user_id' => $user->id,
        'attachment_id' => $attachment->id,
    ]);

    Board::factory()->archived()->create(['user_id' => $user->id]);

    [$other, $otherCard] = vaultCard();
    Attachment::factory()->create(['user_id' => $other->id, 'card_id' => $otherCard->id]);
    DropLink::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    expect(Livewire::test(Overview::class)->instance()->stats)
        ->toMatchArray([
            'boards' => 1,
            'cards' => 1,
            'files' => 1,
            'activeLinks' => 1,
        ]);
});

it('renders zeroed stats for an empty account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Overview::class)
        ->assertSee(__('dashboard.stats.boards'))
        ->assertSee('0');
});
