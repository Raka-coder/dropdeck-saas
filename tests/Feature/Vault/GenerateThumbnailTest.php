<?php

use App\Enums\ThumbnailStatus;
use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('vault');
});

function pngBytes(int $width = 640, int $height = 480): string
{
    $image = imagecreatetruecolor($width, $height);

    if ($image === false) {
        throw new RuntimeException('Gagal membuat gambar uji.');
    }

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

it('creates a webp thumbnail for an image', function () {
    [$user, $card] = vaultCard();
    $path = 'users/'.$user->id.'/cards/'.$card->id.'/foto.png';

    Storage::disk('vault')->put($path, pngBytes());

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'disk' => 'vault',
        'path' => $path,
        'original_name' => 'foto.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    (new GenerateThumbnail($attachment->id))->handle();

    $attachment->refresh();

    expect($attachment->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($attachment->thumbnail_path)->toBe(
            'users/'.$user->id.'/cards/'.$card->id.'/thumbs/foto.webp',
        );

    Storage::disk('vault')->assertExists((string) $attachment->thumbnail_path);

    $bytes = (string) Storage::disk('vault')->get((string) $attachment->thumbnail_path);

    expect(substr($bytes, 8, 4))->toBe('WEBP');

    $size = getimagesizefromstring($bytes);

    expect($size)->not->toBeFalse()
        ->and($size[0])->toBeLessThanOrEqual(480)
        ->and($size[1])->toBeLessThanOrEqual(480);
});

it('marks non image attachments without generating a thumbnail', function () {
    [$user, $card] = vaultCard();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'mime_type' => 'application/pdf',
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    (new GenerateThumbnail($attachment->id))->handle();

    expect($attachment->fresh()->thumbnail_status)->toBe(ThumbnailStatus::None)
        ->and($attachment->fresh()->thumbnail_path)->toBeNull();
});

it('marks the thumbnail as failed when the object is missing', function () {
    [$user, $card] = vaultCard();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'disk' => 'vault',
        'path' => 'users/'.$user->id.'/cards/'.$card->id.'/hilang.png',
        'mime_type' => 'image/png',
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    (new GenerateThumbnail($attachment->id))->handle();

    expect($attachment->fresh()->thumbnail_status)->toBe(ThumbnailStatus::Failed);
});

it('refuses to process images above the pixel limit', function () {
    config()->set('dropdeck.vault.thumbnail.max_source_pixels', 100);

    [$user, $card] = vaultCard();
    $path = 'users/'.$user->id.'/cards/'.$card->id.'/besar.png';

    Storage::disk('vault')->put($path, pngBytes(50, 50));

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'disk' => 'vault',
        'path' => $path,
        'mime_type' => 'image/png',
        'thumbnail_status' => ThumbnailStatus::Pending,
    ]);

    (new GenerateThumbnail($attachment->id))->handle();

    expect($attachment->fresh()->thumbnail_status)->toBe(ThumbnailStatus::Failed)
        ->and($attachment->fresh()->thumbnail_path)->toBeNull();
});

it('ignores attachments that no longer exist', function () {
    (new GenerateThumbnail(999_999))->handle();

    expect(Attachment::query()->count())->toBe(0);
});
