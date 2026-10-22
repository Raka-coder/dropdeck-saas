<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use App\Support\Position;
use Illuminate\Validation\ValidationException;

final class CreateBoard
{
    public function handle(User $user, string $title, ?string $description = null, ?string $color = null): Board
    {
        $max = (int) config('dropdeck.limits.max_boards');

        if ($user->boards()->count() >= $max) {
            throw ValidationException::withMessages([
                'title' => __('boards.validation.limit', ['max' => $max]),
            ]);
        }

        $board = $user->boards()->create([
            'title' => $title,
            'description' => $description,
            'color' => $color,
        ]);

        $titles = array_values((array) config('dropdeck.default_columns'));

        foreach ($titles as $index => $columnTitle) {
            $board->columns()->create([
                'title' => (string) $columnTitle,
                'position' => ($index + 1) * Position::GAP,
            ]);
        }

        return $board;
    }
}
