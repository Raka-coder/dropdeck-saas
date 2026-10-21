<?php

declare(strict_types=1);

namespace App\Actions\DropLinks;

use App\Models\DropLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class RevokeDropLink
{
    public function handle(User $user, DropLink $dropLink): void
    {
        if ($dropLink->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($dropLink->revoked_at !== null) {
            return;
        }

        $dropLink->forceFill(['revoked_at' => now()])->save();
    }
}
