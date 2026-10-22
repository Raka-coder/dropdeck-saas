<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class ReconcileStorageUsage implements ShouldQueue
{
    use Queueable;

    /**
     * Recompute every user's used storage from their attachment rows.
     */
    public function handle(): int
    {
        /** @var array<int, int> $totals */
        $totals = Attachment::query()
            ->toBase()
            ->selectRaw('user_id, SUM(size_bytes) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $corrected = 0;

        User::query()
            ->select(['id', 'storage_used_bytes'])
            ->chunkById(200, function ($users) use ($totals, &$corrected): void {
                foreach ($users as $user) {
                    $expected = $totals[$user->id] ?? 0;

                    if ((int) $user->storage_used_bytes === $expected) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['storage_used_bytes' => $expected]);

                    $corrected++;
                }
            });

        return $corrected;
    }
}
