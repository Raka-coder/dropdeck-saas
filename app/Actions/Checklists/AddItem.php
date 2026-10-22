<?php

declare(strict_types=1);

namespace App\Actions\Checklists;

use App\Models\Card;
use App\Models\ChecklistItem;
use App\Support\Position;
use Illuminate\Validation\ValidationException;

final class AddItem
{
    public function handle(Card $card, string $content): ChecklistItem
    {
        $limit = (int) config('dropdeck.limits.max_checklist');

        if ($card->checklistItems()->count() >= $limit) {
            throw ValidationException::withMessages([
                'content' => __('cards.validation.checklist_limit', ['max' => $limit]),
            ]);
        }

        $max = $card->checklistItems()->max('position');

        return $card->checklistItems()->create([
            'content' => $content,
            'position' => Position::next($max === null ? null : (int) $max),
        ]);
    }
}
