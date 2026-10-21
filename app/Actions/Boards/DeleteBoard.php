<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Models\Board;
use Illuminate\Support\Facades\DB;

final class DeleteBoard
{
    /**
     * Delete the board and everything owned by it.
     *
     * Columns, cards, labels, checklist items and attachments are removed by
     * database cascades inside a single transaction.
     */
    public function handle(Board $board): void
    {
        DB::transaction(static function () use ($board): void {
            $board->delete();
        });
    }
}
