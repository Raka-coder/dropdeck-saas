<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Actions\Vault\PurgeAttachments;
use App\Models\Attachment;
use App\Models\Board;
use Illuminate\Support\Facades\DB;

final class DeleteBoard
{
    public function __construct(private readonly PurgeAttachments $purgeAttachments) {}

    /**
     * Delete the board and everything owned by it.
     *
     * Columns, cards, labels, checklist items and attachments are removed by
     * database cascades inside a single transaction.
     */
    public function handle(Board $board): void
    {
        DB::transaction(function () use ($board): void {
            $attachments = Attachment::query()
                ->whereIn('card_id', $board->cards()->select('id'))
                ->get();

            $this->purgeAttachments->handle($attachments);

            $board->delete();
        });
    }
}
