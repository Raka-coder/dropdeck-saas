<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Actions\Vault\PurgeAttachments;
use App\Models\Card;
use Illuminate\Support\Facades\DB;

final class DeleteCard
{
    public function __construct(private readonly PurgeAttachments $purgeAttachments) {}

    /**
     * Delete the card; labels, checklist items and attachments are removed by cascades.
     */
    public function handle(Card $card): void
    {
        DB::transaction(function () use ($card): void {
            $this->purgeAttachments->handle($card->attachments()->get());

            $card->delete();
        });
    }
}
