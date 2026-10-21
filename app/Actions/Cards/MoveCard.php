<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\BoardColumn;
use App\Models\Card;
use App\Support\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoveCard
{
    /**
     * Move a card into a column at the given zero-based index.
     *
     * Writes a single row when there is room between the neighbours, otherwise
     * renumbers the destination column (gap of Position::GAP) in the same transaction.
     */
    public function handle(Card $card, BoardColumn $toColumn, int $index): Card
    {
        if ($card->board_id !== $toColumn->board_id) {
            throw ValidationException::withMessages([
                'column' => __('cards.errors.cross_board'),
            ]);
        }

        return DB::transaction(function () use ($card, $toColumn, $index): Card {
            $card->forceFill(['column_id' => $toColumn->id])->save();

            $siblings = $this->siblingPositions($toColumn->id, $card->id);
            $index = max(0, min($index, count($siblings)));

            $position = $this->positionFor($siblings, $index);

            if ($position === null) {
                $this->rebalance($toColumn->id);

                $siblings = $this->siblingPositions($toColumn->id, $card->id);
                $position = $this->positionFor($siblings, $index) ?? Position::next();
            }

            $card->forceFill(['position' => $position])->save();

            return $card->refresh();
        });
    }

    /**
     * @return array<int, int>
     */
    private function siblingPositions(int $columnId, int $excludedCardId): array
    {
        return Card::query()
            ->where('column_id', $columnId)
            ->whereKeyNot($excludedCardId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('position')
            ->all();
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

    private function rebalance(int $columnId): void
    {
        $ids = Card::query()
            ->where('column_id', $columnId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (array_values($ids) as $index => $id) {
            Card::query()->whereKey($id)->update([
                'position' => ($index + 1) * Position::GAP,
            ]);
        }
    }
}
