<?php

use App\Livewire\Vault\AttachmentList;
use App\Livewire\Vault\UploadZone;
use App\Models\Attachment;
use Livewire\Livewire;

it('renders the vault components inside the card modal', function () {
    [$user, $card] = vaultCard();

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $card->board])
        ->call('openCard', $card->id)
        ->assertSeeLivewire(UploadZone::class)
        ->assertSeeLivewire(AttachmentList::class)
        ->assertSee(__('attachments.upload_hint', ['size' => '100 MB', 'count' => 20]));
});

it('shows existing attachments in the modal', function () {
    [$user, $card] = vaultCard();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'original_name' => 'rencana-kerja.pdf',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::boards.show', ['board' => $card->board])
        ->call('openCard', $card->id)
        ->assertSee($attachment->original_name);
});
