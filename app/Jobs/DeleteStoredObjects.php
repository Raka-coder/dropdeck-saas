<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

final class DeleteStoredObjects implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120];

    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(public string $disk, public array $paths) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        foreach (array_unique($this->paths) as $path) {
            if ($path === '' || ! $storage->exists($path)) {
                continue;
            }

            $storage->delete($path);
        }
    }
}
