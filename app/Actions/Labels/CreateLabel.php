<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Validation\ValidationException;

final class CreateLabel
{
    public function handle(Board $board, string $name, string $color): Label
    {
        $max = (int) config('dropdeck.limits.max_labels');

        if ($board->labels()->count() >= $max) {
            throw ValidationException::withMessages([
                'name' => __('cards.validation.label_limit', ['max' => $max]),
            ]);
        }

        return $board->labels()->create([
            'name' => $name,
            'color' => $color,
        ]);
    }
}
