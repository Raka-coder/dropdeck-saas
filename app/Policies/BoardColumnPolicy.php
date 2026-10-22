<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BoardColumn;
use App\Models\User;

final class BoardColumnPolicy
{
    public function view(User $user, BoardColumn $column): bool
    {
        return $column->board->user_id === $user->id;
    }

    public function update(User $user, BoardColumn $column): bool
    {
        return $column->board->user_id === $user->id;
    }

    public function delete(User $user, BoardColumn $column): bool
    {
        return $column->board->user_id === $user->id;
    }
}
