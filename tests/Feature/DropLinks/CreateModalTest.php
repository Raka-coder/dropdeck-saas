<?php

use App\Livewire\Links\CreateModal;
use App\Models\Attachment;
use App\Models\DropLink;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('vault');
});

it('creates a file link from the modal', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);

    $this->actingAs($user);

    $component = Livewire::test(CreateModal::class)
        ->call('openForAttachment', $attachment->id)
        ->set('expiresInHours', 24)
        ->set('maxDownloads', 2)
        ->set('password', 'secret')
        ->set('label', 'Klien')
        ->call('save')
        ->assertHasNoErrors();

    $link = DropLink::query()->firstOrFail();

    expect($link->attachment_id)->toBe($attachment->id)
        ->and($link->max_downloads)->toBe(2)
        ->and($link->label)->toBe('Klien')
        ->and(Hash::check('secret', $link->password_hash))->toBeTrue()
        ->and($component->get('createdUrl'))->toContain('/d/'.$link->token);
});

it('creates a card link from the modal', function () {
    [$user, $card] = vaultCard();

    $this->actingAs($user);

    Livewire::test(CreateModal::class)
        ->call('openForCard', $card->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(DropLink::query()->firstOrFail()->card_id)->toBe($card->id);
});

it('validates the expiry range', function () {
    [$user, $card] = vaultCard();

    $this->actingAs($user);

    Livewire::test(CreateModal::class)
        ->call('openForCard', $card->id)
        ->set('expiresInHours', 0)
        ->call('save')
        ->assertHasErrors(['expiresInHours']);
});

it('validates the password length', function () {
    [$user, $card] = vaultCard();

    $this->actingAs($user);

    Livewire::test(CreateModal::class)
        ->call('openForCard', $card->id)
        ->set('password', 'abc')
        ->call('save')
        ->assertHasErrors(['password']);
});

it('refuses to open for another user attachment', function () {
    [$user] = vaultCard();
    [$other, $otherCard] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $other->id, 'card_id' => $otherCard->id]);

    $this->actingAs($user);

    Livewire::test(CreateModal::class)->call('openForAttachment', $attachment->id);

    expect(DropLink::query()->count())->toBe(0);
});

it('resets the created url when reopened', function () {
    [$user, $card] = vaultCard();

    $this->actingAs($user);

    $component = Livewire::test(CreateModal::class)
        ->call('openForCard', $card->id)
        ->call('save');

    expect($component->get('createdUrl'))->not->toBeNull();

    $component->call('close');

    expect($component->get('createdUrl'))->toBeNull();
});
