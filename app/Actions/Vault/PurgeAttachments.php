<?php

declare(strict_types=1);

namespace App\Actions\Vault;

use App\Jobs\DeleteStoredObjects;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Remove attachments that are about to be deleted by a database cascade.
 *
 * Cascades delete rows but know nothing about object storage or the quota
 * counter, so every action that removes a card, column or board must call
 * this first.
 */
final class PurgeAttachments
{
    /**
     * @param  Collection<int, Attachment>  $attachments
     */
    public function handle(Collection $attachments): void
    {
        if ($attachments->isEmpty()) {
            return;
        }

        /** @var array<int, array{disk: string, paths: array<int, string>}> $objects */
        $objects = [];

        foreach ($attachments as $attachment) {
            $objects[$attachment->disk] ??= ['disk' => $attachment->disk, 'paths' => []];

            foreach ([$attachment->path, $attachment->thumbnail_path] as $path) {
                if (is_string($path) && $path !== '') {
                    $objects[$attachment->disk]['paths'][] = $path;
                }
            }
        }

        $sizes = $attachments->groupBy('user_id')
            ->map(static fn (Collection $group): int => (int) $group->sum('size_bytes'));

        DB::transaction(function () use ($attachments, $sizes): void {
            foreach ($sizes as $userId => $size) {
                $owner = User::query()->lockForUpdate()->find((int) $userId);

                if ($owner instanceof User) {
                    $owner->storage_used_bytes = max(0, $owner->storage_used_bytes - $size);
                    $owner->save();
                }
            }

            Attachment::query()
                ->whereKey($attachments->pluck('id')->all())
                ->delete();
        });

        foreach ($objects as $batch) {
            if ($batch['paths'] !== []) {
                DeleteStoredObjects::dispatch($batch['disk'], array_values(array_unique($batch['paths'])));
            }
        }
    }
}
