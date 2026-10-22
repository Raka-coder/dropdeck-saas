<?php

use App\Actions\Vault\StoreAttachment;
use App\Exceptions\QuotaExceededException;
use App\Jobs\GenerateThumbnail;
use App\Livewire\Vault\UploadZone;
use App\Models\Attachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;

const PDF_BYTES = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

beforeEach(function () {
    Storage::fake('vault');
});

it('stores an attachment and reserves quota', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    $attachment = app(StoreAttachment::class)->handle($user, $card, $file);

    expect($attachment->exists)->toBeTrue()
        ->and($attachment->user_id)->toBe($user->id)
        ->and($attachment->card_id)->toBe($card->id)
        ->and($attachment->disk)->toBe('vault')
        ->and($attachment->extension)->toBe('pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size_bytes)->toBe(strlen(PDF_BYTES))
        ->and($attachment->checksum_sha256)->toBe(hash('sha256', PDF_BYTES))
        ->and($attachment->path)->toStartWith("users/{$user->id}/cards/{$card->id}/");

    Storage::disk('vault')->assertExists($attachment->path);

    expect($user->fresh()->storage_used_bytes)->toBe($attachment->size_bytes);

    Queue::assertNothingPushed();
});

it('queues a thumbnail for images', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->image('foto.png', 640, 480);

    $attachment = app(StoreAttachment::class)->handle($user, $card, $file);

    expect($attachment->thumbnail_status->value)->toBe('pending');

    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job) => $job->attachmentId === $attachment->id);
});

it('rejects an upload when the quota is exhausted', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $user->forceFill(['storage_quota_bytes' => 10])->save();

    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(QuotaExceededException::class);

    expect(Attachment::query()->count())->toBe(0)
        ->and($user->fresh()->storage_used_bytes)->toBe(0)
        ->and(Storage::disk('vault')->allFiles())->toBe([]);
});

it('rejects an upload beyond the per card limit', function () {
    config()->set('dropdeck.limits.max_files_per_card', 1);

    [$user, $card] = vaultCard();
    Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);

    $file = UploadedFile::fake()->createWithContent('kedua.pdf', PDF_BYTES);

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(1);
});

it('rejects an upload larger than the file limit', function () {
    config()->set('dropdeck.limits.max_file_mb', 1);

    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf');

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(ValidationException::class);
});

it('rejects an empty file', function () {
    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->createWithContent('kosong.txt', '');

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(ValidationException::class);
});

it('rejects a forbidden extension even when the content is harmless', function () {
    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->createWithContent('skrip.php', PDF_BYTES);

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0);
});

it('rejects content whose real mime type is forbidden', function () {
    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->createWithContent('halaman.txt', '<html><body>halo</body></html>');

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(ValidationException::class);

    expect(Attachment::query()->count())->toBe(0);
});

it('refuses to write into another users card', function () {
    [$user] = vaultCard();
    [, $foreignCard] = vaultCard();

    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    expect(fn () => app(StoreAttachment::class)->handle($user, $foreignCard, $file))
        ->toThrow(AuthorizationException::class);

    expect(Attachment::query()->count())->toBe(0);
});

it('does not leave usage reserved when storage fails', function () {
    Queue::fake();

    Storage::shouldReceive('disk')->andThrow(new RuntimeException('storage down'));

    [$user, $card] = vaultCard();
    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    expect(fn () => app(StoreAttachment::class)->handle($user, $card, $file))
        ->toThrow(RuntimeException::class);

    expect($user->fresh()->storage_used_bytes)->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

it('enforces the quota atomically under concurrent reservations', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $user->forceFill(['storage_quota_bytes' => strlen(PDF_BYTES) + 5])->save();

    $action = app(StoreAttachment::class);
    $first = UploadedFile::fake()->createWithContent('satu.pdf', PDF_BYTES);
    $action->handle($user, $card, $first);

    $second = UploadedFile::fake()->createWithContent('dua.pdf', PDF_BYTES);

    expect(fn () => $action->handle($user, $card, $second))
        ->toThrow(QuotaExceededException::class);

    expect($user->fresh()->storage_used_bytes)->toBe(strlen(PDF_BYTES))
        ->and(Attachment::query()->count())->toBe(1);
});

it('uploads automatically when files are selected', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    Livewire::test(UploadZone::class, ['cardId' => $card->id])
        ->set('files', [$file])
        ->assertHasNoErrors()
        ->assertDispatched('attachment-uploaded', cardId: $card->id);

    expect(Attachment::query()->where('card_id', $card->id)->count())->toBe(1)
        ->and($user->fresh()->storage_used_bytes)->toBe(strlen(PDF_BYTES));
});

it('reports quota errors in the upload zone', function () {
    Queue::fake();

    [$user, $card] = vaultCard();
    $user->forceFill(['storage_quota_bytes' => 5])->save();
    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('catatan.pdf', PDF_BYTES);

    Livewire::test(UploadZone::class, ['cardId' => $card->id])
        ->set('files', [$file])
        ->assertHasErrors('files');

    expect(Attachment::query()->count())->toBe(0);
});

it('cannot mount the upload zone for a foreign card', function () {
    [, $foreignCard] = vaultCard();
    [$user] = vaultCard();
    $this->actingAs($user);

    Livewire::test(UploadZone::class, ['cardId' => $foreignCard->id])
        ->assertStatus(404);
});
