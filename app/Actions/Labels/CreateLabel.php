<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Board;
use App\Models\Label;

final class CreateLabel
{
    public function handle(Board $board, string $name, string $color): Label
    {
        return $board->labels()->create([
            'name' => $name,
            'color' => $color,
        ]);
    }
}
