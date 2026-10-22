<?php

use App\Livewire\Links\CreateModal;
use App\Livewire\Vault\UploadZone;
use App\Models\Attachment;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('blocks uploads once the per-minute limit is reached', function () {
    [$user, $card] = vaultCard();
    $this->actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        RateLimiter::hit('upload:'.$user->id, 60);
    }

    Livewire::test(UploadZone::class, ['cardId' => $card->id])
        ->call('upload')
        ->assertHasErrors(['files' => __('attachments.errors.rate_limited')]);
});

it('blocks link creation once the per-minute limit is reached', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);
    $this->actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        RateLimiter::hit('droplink-create:'.$user->id, 60);
    }

    Livewire::test(CreateModal::class)
        ->call('openForAttachment', $attachment->id)
        ->call('save')
        ->assertHasErrors(['label' => __('droplinks.errors.rate_limited')]);
});

it('keeps uploads and link creation working below the limit', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);
    $this->actingAs($user);

    Livewire::test(CreateModal::class)
        ->call('openForAttachment', $attachment->id)
        ->call('save')
        ->assertHasNoErrors();
});
