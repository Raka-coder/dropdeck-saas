<?php

use App\Actions\Boards\DeleteBoard;
use App\Actions\Cards\DeleteCard;
use App\Actions\Columns\DeleteColumn;
use App\Jobs\DeleteStoredObjects;
use App\Models\Attachment;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('vault');
    Queue::fake();
});

it('refunds quota and purges objects when a card is deleted', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 3000])->save();

    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'disk' => 'vault',
        'path' => 'users/'.$user->id.'/cards/'.$card->id.'/berkas.pdf',
        'size_bytes' => 1200,
    ]);

    app(DeleteCard::class)->handle($card);

    expect(Attachment::query()->find($attachment->id))->toBeNull()
        ->and($user->fresh()->storage_used_bytes)->toBe(1800);

    Queue::assertPushed(DeleteStoredObjects::class, fn (DeleteStoredObjects $job): bool => $job->paths === [$attachment->path]);
});

it('refunds quota when the whole column is deleted', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 1000])->save();

    Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'users/'.$user->id.'/cards/'.$card->id.'/kolom.pdf',
        'size_bytes' => 600,
    ]);

    app(DeleteColumn::class)->handle($card->column);

    expect($user->fresh()->storage_used_bytes)->toBe(400);
});

it('refunds quota when the whole board is deleted', function () {
    [$user, $card] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 900])->save();

    Attachment::factory()->create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'path' => 'users/'.$user->id.'/cards/'.$card->id.'/papan.pdf',
        'size_bytes' => 500,
    ]);

    app(DeleteBoard::class)->handle($card->board);

    expect($user->fresh()->storage_used_bytes)->toBe(400);
});
