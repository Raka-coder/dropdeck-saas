<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class DeleteLabel
{
    public function handle(Board $board, Label $label): void
    {
        if ($label->board_id !== $board->id) {
            throw (new ModelNotFoundException)->setModel(Label::class, [$label->id]);
        }

        DB::transaction(function () use ($label): void {
            $label->cards()->detach();
            $label->delete();
        });
    }
}
