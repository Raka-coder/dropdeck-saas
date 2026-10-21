<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\Card;

final class UpdateCard
{
    public function handle(Card $card, string $title, ?string $description = null): Card
    {
        $card->update([
            'title' => $title,
            'description' => $description,
        ]);

        return $card->refresh();
    }
}
