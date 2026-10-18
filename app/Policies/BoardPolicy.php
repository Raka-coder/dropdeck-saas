<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function restore(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function forceDelete(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }
}
