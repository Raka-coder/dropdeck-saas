<?php

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('vault');
});

it('requires authentication', function () {
    $this->get(route('links.index'))->assertRedirect(route('login'));
});

it('lists only the owner links', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'original_name' => 'milik-saya.pdf',
    ]);
    droplinkForFile($user, $attachment);

    [$other, $otherCard] = vaultCard();
    $foreign = Attachment::factory()->create([
        'user_id' => $other->id,
        'card_id' => $otherCard->id,
        'original_name' => 'milik-orang.pdf',
    ]);
    droplinkForFile($other, $foreign);

    $this->actingAs($user)
        ->get(route('links.index'))
        ->assertOk()
        ->assertSee('milik-saya.pdf')
        ->assertDontSee('milik-orang.pdf');
});

it('shows the status and remaining downloads', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);
    droplinkForFile($user, $attachment, ['max_downloads' => 3, 'download_count' => 1]);

    $this->actingAs($user)
        ->get(route('links.index'))
        ->assertOk()
        ->assertSee(__('droplinks.statuses.active'))
        ->assertSee('1 / 3')
        ->assertSee('2');
});

it('revokes an active link', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);
    $link = droplinkForFile($user, $attachment);

    $this->actingAs($user);

    Livewire::test('pages::links.index')
        ->call('confirmRevoke', $link->id)
        ->call('revoke');

    expect($link->fresh()->revoked_at)->not->toBeNull();
});

it('cannot revoke a link owned by somebody else', function () {
    [$user] = vaultCard();
    [$other, $otherCard] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $other->id, 'card_id' => $otherCard->id]);
    $foreign = droplinkForFile($other, $attachment);

    $this->actingAs($user);

    Livewire::test('pages::links.index')->call('confirmRevoke', $foreign->id);

    expect($foreign->fresh()->revoked_at)->toBeNull();
});

it('does not expose another user link in the public page', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'original_name' => 'rahasia.pdf',
    ]);
    $link = droplinkForFile($user, $attachment);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertDontSee($user->email);
});

it('renders a card link with its attachments', function () {
    [$user, $card] = vaultCard();
    Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'original_name' => 'kartu-satu.pdf',
    ]);
    $link = droplinkForCard($user, $card);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee('kartu-satu.pdf');
});
