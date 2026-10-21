<?php

declare(strict_types=1);

namespace App\Actions\Vault;

use App\Jobs\DeleteStoredObjects;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DeleteAttachment
{
    public function handle(User $user, Attachment $attachment): void
    {
        if ($attachment->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $size = $attachment->size_bytes;
        $ownerId = $attachment->user_id;
        $diskName = $attachment->disk;
        $paths = array_values(array_filter([
            $attachment->path,
            $attachment->thumbnail_path,
        ]));

        DB::transaction(function () use ($attachment, $size, $ownerId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($ownerId);
            $owner->storage_used_bytes = max(0, $owner->storage_used_bytes - $size);
            $owner->save();

            $attachment->delete();
        });

        if ($paths !== []) {
            DeleteStoredObjects::dispatch($diskName, $paths);
        }
    }
}
