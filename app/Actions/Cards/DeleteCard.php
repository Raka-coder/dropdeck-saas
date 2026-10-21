<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\Card;
use Illuminate\Support\Facades\DB;

final class DeleteCard
{
    /**
     * Delete the card; labels, checklist items and attachments are removed by cascades.
     */
    public function handle(Card $card): void
    {
        DB::transaction(static function () use ($card): void {
            $card->delete();
        });
    }
}
