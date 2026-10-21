<?php

use App\Actions\DropLinks\CreateDropLink;
use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('creates a file link with hashed password', function () {
    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);

    $link = app(CreateDropLink::class)->handle(
        $user,
        $attachment,
        now()->addDay(),
        3,
        'secret',
        'Untuk klien',
    );

    expect($link->target_type)->toBe(DropLinkTarget::File)
        ->and($link->attachment_id)->toBe($attachment->id)
        ->and($link->card_id)->toBeNull()
        ->and(strlen($link->token))->toBe(40)
        ->and($link->max_downloads)->toBe(3)
        ->and($link->label)->toBe('Untuk klien')
        ->and($link->password_hash)->not->toBe('secret')
        ->and(Hash::check('secret', $link->password_hash))->toBeTrue();
});

it('creates a card link without password', function () {
    [$user, $card] = vaultCard();

    $link = app(CreateDropLink::class)->handle($user, $card, now()->addHours(6));

    expect($link->target_type)->toBe(DropLinkTarget::Card)
        ->and($link->card_id)->toBe($card->id)
        ->and($link->attachment_id)->toBeNull()
        ->and($link->password_hash)->toBeNull()
        ->and($link->max_downloads)->toBeNull();
});

it('rejects targets owned by another user', function () {
    [$user] = vaultCard();
    [$other] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $other->id]);

    app(CreateDropLink::class)->handle($user, $attachment, now()->addDay());
})->throws(AuthorizationException::class);

it('rejects creation above the active link limit', function () {
    config()->set('dropdeck.limits.max_active_links', 1);

    [$user, $card] = vaultCard();
    $attachment = Attachment::factory()->create(['user_id' => $user->id, 'card_id' => $card->id]);

    $action = app(CreateDropLink::class);
    $action->handle($user, $attachment, now()->addDay());

    $action->handle($user, $card, now()->addDay());
})->throws(ValidationException::class);

it('rejects expiry shorter than one hour', function () {
    [$user, $card] = vaultCard();

    app(CreateDropLink::class)->handle($user, $card, now()->addMinutes(30));
})->throws(ValidationException::class);

it('rejects expiry beyond the configured maximum', function () {
    config()->set('dropdeck.limits.link_max_days', 30);

    [$user, $card] = vaultCard();

    app(CreateDropLink::class)->handle($user, $card, now()->addDays(31));
})->throws(ValidationException::class);

it('rejects download limits outside the allowed range', function (int $max) {
    [$user, $card] = vaultCard();

    app(CreateDropLink::class)->handle($user, $card, now()->addDay(), $max);
})->with([0, 101])->throws(ValidationException::class);

it('rejects passwords outside four to sixty-four characters', function (string $password) {
    [$user, $card] = vaultCard();

    app(CreateDropLink::class)->handle($user, $card, now()->addDay(), null, $password);
})->with(['abc', str_repeat('a', 65)])->throws(ValidationException::class);

it('treats an empty password as no password', function () {
    [$user, $card] = vaultCard();

    $link = app(CreateDropLink::class)->handle($user, $card, now()->addDay(), null, '   ');

    expect($link->password_hash)->toBeNull();
});

it('does not count dead links against the active limit', function () {
    config()->set('dropdeck.limits.max_active_links', 1);

    [$user, $card] = vaultCard();
    droplinkForCard($user, $card, ['revoked_at' => now()]);

    $link = app(CreateDropLink::class)->handle($user, $card, now()->addDay());

    expect($link->exists)->toBeTrue();
});
