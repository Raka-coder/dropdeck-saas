<?php

use App\Enums\AccessEvent;
use App\Enums\DropLinkStatus;
use App\Enums\DropLinkTarget;
use App\Models\DropLink;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the drop link status precedence', function () {
    expect((new DropLink([
        'revoked_at' => CarbonImmutable::now(),
        'expires_at' => CarbonImmutable::now()->subDay(),
        'max_downloads' => 1,
        'download_count' => 1,
    ]))->status())->toBe(DropLinkStatus::Revoked);

    expect((new DropLink([
        'expires_at' => CarbonImmutable::now()->subDay(),
        'max_downloads' => 1,
        'download_count' => 1,
    ]))->status())->toBe(DropLinkStatus::Expired);

    expect((new DropLink([
        'expires_at' => CarbonImmutable::now()->addDay(),
        'max_downloads' => 1,
        'download_count' => 1,
    ]))->status())->toBe(DropLinkStatus::Exhausted);

    expect((new DropLink([
        'expires_at' => CarbonImmutable::now()->addDay(),
        'max_downloads' => null,
        'download_count' => 99,
    ]))->status())->toBe(DropLinkStatus::Active);
});

it('maps access event and target values', function () {
    expect(AccessEvent::tryFrom('download'))->toBe(AccessEvent::Download)
        ->and(AccessEvent::tryFrom('nope'))->toBeNull()
        ->and(DropLinkTarget::File->value)->toBe('file')
        ->and(DropLinkTarget::Card->value)->toBe('card');
});
