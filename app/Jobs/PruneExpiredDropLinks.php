<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DropLink;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

final class PruneExpiredDropLinks implements ShouldQueue
{
    use Queueable;

    /**
     * Delete dead links once their retention window has passed.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('dropdeck.limits.link_retention_days'));

        return DropLink::query()
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(fn (Builder $dead) => $dead
                        ->whereNotNull('revoked_at')
                        ->where('revoked_at', '<=', $cutoff))
                    ->orWhere('expires_at', '<=', $cutoff)
                    ->orWhere(fn (Builder $exhausted) => $exhausted
                        ->whereNotNull('max_downloads')
                        ->whereColumn('download_count', '>=', 'max_downloads')
                        ->where('updated_at', '<=', $cutoff));
            })
            ->delete();
    }
}
