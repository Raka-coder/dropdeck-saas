<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Models\BoardColumn;
use Illuminate\Support\Facades\DB;

final class DeleteColumn
{
    /**
     * Delete the column; its cards are removed by the database cascade.
     */
    public function handle(BoardColumn $column): void
    {
        DB::transaction(static function () use ($column): void {
            $column->delete();
        });
    }
}
