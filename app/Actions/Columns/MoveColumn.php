<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Models\BoardColumn;
use App\Support\Position;
use Illuminate\Support\Facades\DB;

final class MoveColumn
{
    /**
     * Move a column to the given zero-based index within its board.
     */
    public function handle(BoardColumn $column, int $index): BoardColumn
    {
        return DB::transaction(function () use ($column, $index): BoardColumn {
            $siblings = BoardColumn::query()
                ->where('board_id', $column->board_id)
                ->whereKeyNot($column->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('position')
                ->all();

            $index = max(0, min($index, count($siblings)));

            $position = $this->positionFor($siblings, $index);

            if ($position === null) {
                $this->rebalance($column->board_id);

                $siblings = BoardColumn::query()
                    ->where('board_id', $column->board_id)
                    ->whereKeyNot($column->id)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('position')
                    ->all();

                $position = $this->positionFor($siblings, $index) ?? Position::next();
            }

            $column->forceFill(['position' => $position])->save();

            return $column->refresh();
        });
    }

    /**
     * @param  array<int, int>  $siblings
     */
    private function positionFor(array $siblings, int $index): ?int
    {
        $siblings = array_values($siblings);
        $previous = $index > 0 ? $siblings[$index - 1] : null;
        $next = $index < count($siblings) ? $siblings[$index] : null;

        return Position::between($previous, $next);
    }

    private function rebalance(int $boardId): void
    {
        $ids = BoardColumn::query()
            ->where('board_id', $boardId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (array_values($ids) as $index => $id) {
            BoardColumn::query()->whereKey($id)->update([
                'position' => ($index + 1) * Position::GAP,
            ]);
        }
    }
}
