<?php

declare(strict_types=1);

namespace App\Actions\Checklists;

use App\Models\ChecklistItem;

final class ToggleItem
{
    public function handle(ChecklistItem $item): ChecklistItem
    {
        $item->forceFill(['is_done' => ! $item->is_done])->save();

        return $item->refresh();
    }
}
