<?php

declare(strict_types=1);

namespace App\Actions\Labels;

use App\Models\Card;
use App\Models\Label;

final class DetachLabel
{
    public function handle(Card $card, Label $label): void
    {
        $card->labels()->detach($label->id);
    }
}
