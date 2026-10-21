<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Models\BoardColumn;

final class RenameColumn
{
    public function handle(BoardColumn $column, string $title): BoardColumn
    {
        $column->update(['title' => $title]);

        return $column->refresh();
    }
}
