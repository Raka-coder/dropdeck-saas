<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DropLink;
use App\Models\User;

class DropLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DropLink $dropLink): bool
    {
        return $user->id === $dropLink->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DropLink $dropLink): bool
    {
        return $user->id === $dropLink->user_id;
    }

    public function delete(User $user, DropLink $dropLink): bool
    {
        return $user->id === $dropLink->user_id;
    }

    public function restore(User $user, DropLink $dropLink): bool
    {
        return $user->id === $dropLink->user_id;
    }

    public function forceDelete(User $user, DropLink $dropLink): bool
    {
        return $user->id === $dropLink->user_id;
    }
}
