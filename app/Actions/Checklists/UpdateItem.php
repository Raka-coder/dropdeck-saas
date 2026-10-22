<?php

declare(strict_types=1);

namespace App\Actions\Checklists;

use App\Models\ChecklistItem;

final class UpdateItem
{
    public function handle(ChecklistItem $item, string $content): ChecklistItem
    {
        $item->update(['content' => $content]);

        return $item->refresh();
    }
}
