<?php

declare(strict_types=1);

namespace App\Actions\Checklists;

use App\Models\ChecklistItem;

final class RemoveItem
{
    public function handle(ChecklistItem $item): void
    {
        $item->delete();
    }
}
