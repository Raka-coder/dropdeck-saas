<?php

use App\Enums\ThumbnailStatus;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('vault');
});

function storedAttachment(User $user, int $cardId, array $overrides = []): Attachment
{
    $attachment = Attachment::factory()->create(array_merge([
        'user_id' => $user->id,
        'card_id' => $cardId,
        'disk' => 'vault',
        'path' => 'users/'.$user->id.'/cards/'.$cardId.'/berkas.pdf',
        'original_name' => 'laporan akhir.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 11,
    ], $overrides));

    Storage::disk('vault')->put($attachment->path, 'hello world');

    return $attachment;
}

it('streams an attachment to its owner', function () {
    [$user, $card] = vaultCard();
    $attachment = storedAttachment($user, $card->id);

    $response = $this->actingAs($user)->get(route('attachments.download', $attachment));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('content-length', '11');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment; filename="laporan_akhir.pdf"; filename*=UTF-8\'\'');

    expect($response->streamedContent())->toBe('hello world');
});

it('downgrades unsafe mime types to a generic download', function () {
    [$user, $card] = vaultCard();
    $attachment = storedAttachment($user, $card->id, [
        'mime_type' => 'text/html',
        'original_name' => 'halaman.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('attachments.download', $attachment))
        ->assertOk()
        ->assertHeader('content-type', 'application/octet-stream');
});

it('hides attachments owned by another user', function () {
    [$user, $card] = vaultCard();
    [$intruder] = vaultCard();
    $attachment = storedAttachment($user, $card->id);

    $this->actingAs($intruder)
        ->get(route('attachments.download', $attachment))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('attachments.thumbnail', $attachment))
        ->assertNotFound();
});

it('requires authentication to download', function () {
    [$user, $card] = vaultCard();
    $attachment = storedAttachment($user, $card->id);

    $this->get(route('attachments.download', $attachment))
        ->assertRedirect(route('login'));
});

it('streams a ready thumbnail inline', function () {
    [$user, $card] = vaultCard();
    $attachment = storedAttachment($user, $card->id, [
        'thumbnail_path' => 'users/'.$user->id.'/cards/'.$card->id.'/thumbs/berkas.webp',
        'thumbnail_status' => ThumbnailStatus::Ready,
    ]);

    Storage::disk('vault')->put($attachment->thumbnail_path, 'webp-bytes');

    $response = $this->actingAs($user)->get(route('attachments.thumbnail', $attachment));

    $response->assertOk()->assertHeader('content-type', 'image/webp');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline; filename="laporan_akhir.webp"');

    expect($response->streamedContent())->toBe('webp-bytes');
});

it('returns 404 when no thumbnail exists yet', function () {
    [$user, $card] = vaultCard();
    $attachment = storedAttachment($user, $card->id);

    $this->actingAs($user)
        ->get(route('attachments.thumbnail', $attachment))
        ->assertNotFound();
});
