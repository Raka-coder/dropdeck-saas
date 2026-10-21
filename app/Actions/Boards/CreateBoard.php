<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;

final class CreateBoard
{
    public function handle(User $user, string $title, ?string $description = null, ?string $color = null): Board
    {
        return $user->boards()->create([
            'title' => $title,
            'description' => $description,
            'color' => $color,
        ]);
    }
}
