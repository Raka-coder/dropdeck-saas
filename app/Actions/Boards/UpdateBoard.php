<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Models\Board;

final class UpdateBoard
{
    public function handle(Board $board, string $title, ?string $description = null, ?string $color = null): Board
    {
        $board->update([
            'title' => $title,
            'description' => $description,
            'color' => $color,
        ]);

        return $board->refresh();
    }
}
