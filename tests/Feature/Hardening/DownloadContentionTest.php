<?php

use App\Models\DropLink;

it('never exceeds max_downloads under repeated contention', function () {
    [$user, $card] = vaultCard();
    $link = droplinkForCard($user, $card, ['max_downloads' => 3]);

    $results = [];

    for ($i = 0; $i < 10; $i++) {
        $results[] = DropLink::query()->whereKey($link->id)->firstOrFail()->consumeDownload();
    }

    $successes = count(array_filter($results));

    expect($successes)->toBe(3)
        ->and($link->fresh()->download_count)->toBe(3)
        ->and($link->fresh()->isActive())->toBeFalse();
});
