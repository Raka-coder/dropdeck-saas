<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Support\Position;
use Illuminate\Validation\ValidationException;

final class CreateCard
{
    public function handle(Board $board, BoardColumn $column, string $title, ?string $description = null): Card
    {
        if ($column->board_id !== $board->id) {
            throw ValidationException::withMessages([
                'column' => __('columns.errors.wrong_board'),
            ]);
        }

        $max = $column->cards()->max('position');

        return $board->cards()->create([
            'user_id' => $board->user_id,
            'column_id' => $column->id,
            'title' => $title,
            'description' => $description,
            'position' => Position::next($max === null ? null : (int) $max),
        ]);
    }
}
