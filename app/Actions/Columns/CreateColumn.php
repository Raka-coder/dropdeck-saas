<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Support\Position;
use Illuminate\Validation\ValidationException;

final class CreateColumn
{
    public function handle(Board $board, string $title): BoardColumn
    {
        $limit = (int) config('dropdeck.limits.max_columns');

        if ($board->columns()->count() >= $limit) {
            throw ValidationException::withMessages([
                'title' => __('columns.validation.limit', ['max' => $limit]),
            ]);
        }

        $max = $board->columns()->max('position');

        $position = Position::next($max === null ? null : (int) $max);

        return $board->columns()->create([
            'title' => $title,
            'position' => $position,
        ]);
    }
}
