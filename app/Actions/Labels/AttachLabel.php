<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Card;
use App\Models\Label;
use Illuminate\Validation\ValidationException;

final class AttachLabel
{
    public function handle(Card $card, Label $label): void
    {
        if ($label->board_id !== $card->board_id) {
            throw ValidationException::withMessages([
                'label' => __('cards.errors.cross_board'),
            ]);
        }

        $card->labels()->syncWithoutDetaching([$label->id]);
    }
}
