<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Label;
use App\Models\User;

final class LabelPolicy
{
    public function view(User $user, Label $label): bool
    {
        return $label->board->user_id === $user->id;
    }

    public function update(User $user, Label $label): bool
    {
        return $label->board->user_id === $user->id;
    }

    public function delete(User $user, Label $label): bool
    {
        return $label->board->user_id === $user->id;
    }
}
