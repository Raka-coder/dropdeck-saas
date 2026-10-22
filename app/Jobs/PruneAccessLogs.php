<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DropLinkAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PruneAccessLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Delete access log rows older than the configured retention window.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('dropdeck.limits.log_retention_days'));

        return DropLinkAccess::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
