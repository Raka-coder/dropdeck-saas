<?php

declare(strict_types=1);

namespace App\Actions\Checklists;

use App\Models\Card;
use App\Models\ChecklistItem;
use App\Support\Position;

final class AddItem
{
    public function handle(Card $card, string $content): ChecklistItem
    {
        $max = $card->checklistItems()->max('position');

        return $card->checklistItems()->create([
            'content' => $content,
            'position' => Position::next($max === null ? null : (int) $max),
        ]);
    }
}
