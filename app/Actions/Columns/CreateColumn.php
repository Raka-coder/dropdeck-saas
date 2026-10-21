<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Support\Position;

final class CreateColumn
{
    public function handle(Board $board, string $title): BoardColumn
    {
        $max = $board->columns()->max('position');

        $position = Position::next($max === null ? null : (int) $max);

        return $board->columns()->create([
            'title' => $title,
            'position' => $position,
        ]);
    }
}
