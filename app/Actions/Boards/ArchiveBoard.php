<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Models\Board;

final class ArchiveBoard
{
    public function handle(Board $board): Board
    {
        $board->forceFill(['archived_at' => now()])->save();

        return $board->refresh();
    }
}
