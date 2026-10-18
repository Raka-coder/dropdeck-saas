<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }

    public function restore(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }

    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }
}
