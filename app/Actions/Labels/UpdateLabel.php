<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UpdateLabel
{
    public function handle(Board $board, Label $label, string $name, string $color): Label
    {
        if ($label->board_id !== $board->id) {
            throw (new ModelNotFoundException)->setModel(Label::class, [$label->id]);
        }

        $label->update([
            'name' => $name,
            'color' => $color,
        ]);

        return $label->refresh();
    }
}
