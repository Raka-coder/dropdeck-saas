<?php

declare(strict_types=1);

namespace App\Actions\Columns;

use App\Actions\Vault\PurgeAttachments;
use App\Models\BoardColumn;
use App\Models\Card;
use Illuminate\Support\Facades\DB;

final class DeleteColumn
{
    public function __construct(private readonly PurgeAttachments $purgeAttachments) {}

    /**
     * Delete the column; its cards and their attachments are removed too.
     */
    public function handle(BoardColumn $column): void
    {
        DB::transaction(function () use ($column): void {
            $attachments = Card::query()
                ->where('column_id', $column->id)
                ->with('attachments')
                ->get()
                ->flatMap(fn (Card $card) => $card->attachments);

            $this->purgeAttachments->handle($attachments);

            $column->delete();
        });
    }
}
