<?php

use App\Enums\AccessEvent;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('vault');
});

/**
 * @return array{0: User, 1: Card, 2: Attachment}
 */
function publicDropLinkFile(): array
{
    [$user, $card] = vaultCard();

    uploadToVault('files/laporan.pdf');

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'files/laporan.pdf',
        'original_name' => 'laporan.pdf',
        'size_bytes' => 7,
    ]);

    return [$user, $card, $attachment];
}

it('returns 404 for an unknown token', function () {
    $this->get('/d/'.Str::random(40))->assertNotFound();
});

it('shows a file link without consuming a download', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment);

    $response = $this->get(route('droplink.show', $link->token));

    $response->assertOk()
        ->assertSee('laporan.pdf')
        ->assertDontSee($user->name)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($link->fresh()->download_count)->toBe(0)
        ->and($link->accesses()->where('event', AccessEvent::View->value)->count())->toBe(1);
});

it('shows the revoked page and logs a blocked access', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['revoked_at' => now()]);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee(__('droplinks.public.revoked_title'));

    expect($link->accesses()->where('event', AccessEvent::Blocked->value)->value('blocked_reason'))
        ->toBe('revoked');
});

it('shows the expired page and logs a blocked access', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['expires_at' => now()->subMinute()]);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee(__('droplinks.public.expired_title'));

    expect($link->accesses()->where('event', AccessEvent::Blocked->value)->value('blocked_reason'))
        ->toBe('expired');
});

it('shows the exhausted page and logs a blocked access', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['max_downloads' => 1, 'download_count' => 1]);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee(__('droplinks.public.exhausted_title'));

    expect($link->accesses()->where('event', AccessEvent::Blocked->value)->value('blocked_reason'))
        ->toBe('exhausted');
});

it('requires the password before revealing the file', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['password_hash' => bcrypt('secret')]);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee(__('droplinks.public.password_title'))
        ->assertDontSee('laporan.pdf');

    expect($link->accesses()->where('event', AccessEvent::View->value)->exists())->toBeFalse();
});

it('rejects a wrong password and logs the failure', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['password_hash' => bcrypt('secret')]);

    $this->post(route('droplink.unlock', $link->token), ['password' => 'salah'])
        ->assertSessionHasErrors('password');

    expect($link->accesses()->where('event', AccessEvent::PasswordFailed->value)->count())->toBe(1)
        ->and($link->accesses()->where('event', AccessEvent::PasswordOk->value)->count())->toBe(0);
});

it('unlocks the link with the correct password', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['password_hash' => bcrypt('secret')]);

    $this->post(route('droplink.unlock', $link->token), ['password' => 'secret'])
        ->assertRedirect(route('droplink.show', $link->token));

    expect($link->accesses()->where('event', AccessEvent::PasswordOk->value)->count())->toBe(1);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee('laporan.pdf');
});

it('throttles repeated password attempts', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['password_hash' => bcrypt('secret')]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('droplink.unlock', $link->token), ['password' => 'salah']);
    }

    $this->post(route('droplink.unlock', $link->token), ['password' => 'salah'])
        ->assertStatus(429);
});

it('throttles repeated download attempts', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment);

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->post(route('droplink.download', $link->token));
    }

    $this->post(route('droplink.download', $link->token))
        ->assertStatus(429);
});

it('streams a file and increments the download counter', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment);

    $response = $this->post(route('droplink.download', $link->token));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->streamedContent())->toBe('payload')
        ->and($link->fresh()->download_count)->toBe(1)
        ->and($link->fresh()->last_accessed_at)->not->toBeNull()
        ->and($link->accesses()->where('event', AccessEvent::Download->value)->count())->toBe(1);
});

it('does not download past the configured limit', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['max_downloads' => 1]);

    $this->post(route('droplink.download', $link->token))->assertOk();

    $this->post(route('droplink.download', $link->token))
        ->assertRedirect(route('droplink.show', $link->token));

    expect($link->fresh()->download_count)->toBe(1);
});

it('blocks download until the password is unlocked', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['password_hash' => bcrypt('secret')]);

    $this->post(route('droplink.download', $link->token))
        ->assertRedirect(route('droplink.show', $link->token));

    expect($link->fresh()->download_count)->toBe(0);
});

it('lists every card attachment and downloads the selected one', function () {
    [$user, $card] = vaultCard();

    uploadToVault('files/satu.pdf');
    uploadToVault('files/dua.pdf');

    $first = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'files/satu.pdf',
        'original_name' => 'satu.pdf',
        'size_bytes' => 7,
    ]);

    Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'files/dua.pdf',
        'original_name' => 'dua.pdf',
        'size_bytes' => 7,
    ]);

    $link = droplinkForCard($user, $card);

    $this->get(route('droplink.show', $link->token))
        ->assertOk()
        ->assertSee('satu.pdf')
        ->assertSee('dua.pdf');

    $response = $this->post(route('droplink.download.attachment', [$link->token, $first->id]));

    $response->assertOk();

    expect($response->streamedContent())->toBe('payload')
        ->and($link->fresh()->download_count)->toBe(1);
});

it('rejects attachments outside the linked card', function () {
    [$user, $card] = vaultCard();
    [, $otherCard] = vaultCard();

    uploadToVault('files/asing.pdf');

    $foreign = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $otherCard->id,
        'path' => 'files/asing.pdf',
        'original_name' => 'asing.pdf',
    ]);

    $link = droplinkForCard($user, $card);

    $this->post(route('droplink.download.attachment', [$link->token, $foreign->id]))
        ->assertNotFound();

    expect($link->fresh()->download_count)->toBe(0);
});

it('consumes a download slot atomically only once', function () {
    [$user, , $attachment] = publicDropLinkFile();
    $link = droplinkForFile($user, $attachment, ['max_downloads' => 1]);

    expect($link->consumeDownload())->toBeTrue()
        ->and($link->consumeDownload())->toBeFalse()
        ->and($link->fresh()->download_count)->toBe(1);
});
