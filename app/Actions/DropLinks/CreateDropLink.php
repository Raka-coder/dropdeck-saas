<?php

declare(strict_types=1);

namespace App\Actions\DropLinks;

use App\Enums\DropLinkTarget;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\DropLink;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateDropLink
{
    public function handle(
        User $user,
        Attachment|Card $target,
        CarbonInterface $expiresAt,
        ?int $maxDownloads = null,
        ?string $password = null,
        ?string $label = null,
    ): DropLink {
        if ($target->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $this->ensureActiveCapacity($user);
        $this->ensureExpiry($expiresAt);
        $this->ensureMaxDownloads($maxDownloads);

        $password = $password === null ? null : trim($password);

        if ($password === '') {
            $password = null;
        }

        $this->ensurePassword($password);

        $label = $label === null ? null : trim($label);

        return DropLink::query()->create([
            'user_id' => $user->id,
            'target_type' => $target instanceof Attachment ? DropLinkTarget::File : DropLinkTarget::Card,
            'attachment_id' => $target instanceof Attachment ? $target->id : null,
            'card_id' => $target instanceof Card ? $target->id : null,
            'token' => Str::random(40),
            'label' => $label === '' ? null : $label,
            'password_hash' => $password === null ? null : Hash::make($password),
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
        ]);
    }

    private function ensureActiveCapacity(User $user): void
    {
        $max = (int) config('dropdeck.limits.max_active_links');

        if ($user->dropLinks()->active()->count() >= $max) {
            throw ValidationException::withMessages([
                'link' => __('droplinks.errors.too_many_active', ['max' => $max]),
            ]);
        }
    }

    private function ensureExpiry(CarbonInterface $expiresAt): void
    {
        $maxDays = (int) config('dropdeck.limits.link_max_days');

        if ($expiresAt->lessThan(now()->addHour())) {
            throw ValidationException::withMessages([
                'expires_at' => __('droplinks.errors.expiry_too_soon'),
            ]);
        }

        if ($expiresAt->greaterThan(now()->addDays($maxDays))) {
            throw ValidationException::withMessages([
                'expires_at' => __('droplinks.errors.expiry_too_far', ['max' => $maxDays]),
            ]);
        }
    }

    private function ensureMaxDownloads(?int $maxDownloads): void
    {
        if ($maxDownloads === null) {
            return;
        }

        $max = (int) config('dropdeck.limits.link_max_downloads');

        if ($maxDownloads < 1 || $maxDownloads > $max) {
            throw ValidationException::withMessages([
                'max_downloads' => __('droplinks.errors.max_downloads_range', ['max' => $max]),
            ]);
        }
    }

    private function ensurePassword(?string $password): void
    {
        if ($password === null) {
            return;
        }

        $length = Str::length($password);

        if ($length < 4 || $length > 64) {
            throw ValidationException::withMessages([
                'password' => __('droplinks.errors.password_length'),
            ]);
        }
    }
}
