<?php

use App\Actions\Vault\DeleteAttachment;
use App\Jobs\DeleteStoredObjects;
use App\Livewire\Vault\AttachmentList;
use App\Models\Attachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('vault');
    Queue::fake();
});

it('deletes an attachment and refunds the quota', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 5000])->save();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'users/'.$user->id.'/cards/'.$card->id.'/berkas.pdf',
        'size_bytes' => 2000,
        'thumbnail_path' => 'users/'.$user->id.'/cards/'.$card->id.'/thumbs/berkas.webp',
    ]);

    app(DeleteAttachment::class)->handle($user, $attachment);

    expect(Attachment::query()->find($attachment->id))->toBeNull()
        ->and($user->fresh()->storage_used_bytes)->toBe(3000);

    Queue::assertPushed(DeleteStoredObjects::class, function (DeleteStoredObjects $job) use ($attachment): bool {
        return $job->paths === [$attachment->path, $attachment->thumbnail_path];
    });
});

it('never drives usage below zero', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 100])->save();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'size_bytes' => 2000,
    ]);

    app(DeleteAttachment::class)->handle($user, $attachment);

    expect($user->fresh()->storage_used_bytes)->toBe(0);
});

it('refuses to delete another users attachment', function () {
    [$user, $card] = vaultCard();
    [$intruder] = vaultCard();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
    ]);

    expect(fn () => app(DeleteAttachment::class)->handle($intruder, $attachment))
        ->toThrow(AuthorizationException::class);

    expect(Attachment::query()->find($attachment->id))->not->toBeNull();
});

it('deletes through the list component with confirmation', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 400])->save();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'size_bytes' => 400,
    ]);

    $this->actingAs($user);

    Livewire::test(AttachmentList::class, ['cardId' => $card->id])
        ->assertSee($attachment->original_name)
        ->call('confirmDelete', $attachment->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Attachment::query()->find($attachment->id))->toBeNull()
        ->and($user->fresh()->storage_used_bytes)->toBe(0);
});

it('keeps the attachment when confirmation is cancelled', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
    ]);

    $this->actingAs($user);

    Livewire::test(AttachmentList::class, ['cardId' => $card->id])
        ->call('confirmDelete', $attachment->id)
        ->call('cancelDelete')
        ->call('delete');

    expect(Attachment::query()->find($attachment->id))->not->toBeNull();
});

it('cannot mount the list for a foreign card', function () {
    [, $foreignCard] = vaultCard();
    [$user] = vaultCard();

    $this->actingAs($user);

    Livewire::test(AttachmentList::class, ['cardId' => $foreignCard->id])
        ->assertStatus(404);
});
