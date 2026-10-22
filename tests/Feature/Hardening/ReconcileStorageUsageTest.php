<?php

use App\Jobs\ReconcileStorageUsage;
use App\Models\Attachment;

it('recomputes storage usage from attachment rows', function () {
    [$user, $card] = vaultCard();

    foreach ([100, 250, 650] as $size) {
        Attachment::factory()->create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'size_bytes' => $size,
        ]);
    }

    $user->forceFill(['storage_used_bytes' => 9_999])->save();

    [$emptyUser] = vaultCard();
    $emptyUser->forceFill(['storage_used_bytes' => 500])->save();

    [$correctUser, $correctCard] = vaultCard();
    Attachment::factory()->create([
        'user_id' => $correctUser->id,
        'card_id' => $correctCard->id,
        'size_bytes' => 42,
    ]);
    $correctUser->forceFill(['storage_used_bytes' => 42])->save();

    $corrected = (new ReconcileStorageUsage)->handle();

    expect($corrected)->toBe(2)
        ->and($user->fresh()->storage_used_bytes)->toBe(1000)
        ->and($emptyUser->fresh()->storage_used_bytes)->toBe(0)
        ->and($correctUser->fresh()->storage_used_bytes)->toBe(42);
});

it('is a no-op when usage already matches', function () {
    [$user] = vaultCard();
    $user->forceFill(['storage_used_bytes' => 0])->save();

    expect((new ReconcileStorageUsage)->handle())->toBe(0);
});
