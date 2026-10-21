<?php

declare(strict_types=1);

namespace App\Actions\Vault;

use App\Enums\ThumbnailStatus;
use App\Exceptions\QuotaExceededException;
use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use App\Support\Bytes;
use App\Support\FileGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreAttachment
{
    public function handle(User $user, Card $card, UploadedFile $file): Attachment
    {
        if ($card->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $size = (int) $file->getSize();
        $maxFileBytes = Bytes::megabytes((int) config('dropdeck.limits.max_file_mb'));

        if ($size <= 0) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.empty'),
            ]);
        }

        if ($size > $maxFileBytes) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.too_large', ['max' => Bytes::human($maxFileBytes)]),
            ]);
        }

        $maxFiles = (int) config('dropdeck.limits.max_files_per_card');

        if ($card->attachments()->count() >= $maxFiles) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.too_many', ['max' => $maxFiles]),
            ]);
        }

        $originalName = FileGuard::sanitizeName($file->getClientOriginalName());
        $extension = FileGuard::extension($originalName);

        if (FileGuard::isForbiddenExtension($extension)) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.forbidden_type', ['name' => $originalName]),
            ]);
        }

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.unreadable'),
            ]);
        }

        $mime = FileGuard::detectMime($realPath);

        if (FileGuard::isForbiddenMime($mime)) {
            throw ValidationException::withMessages([
                'files' => __('attachments.errors.forbidden_type', ['name' => $originalName]),
            ]);
        }

        $wantsThumbnail = FileGuard::isImage($mime)
            || (FileGuard::isPdf($mime) && (bool) config('dropdeck.vault.pdf_thumbnail_enabled'));

        $diskName = (string) config('dropdeck.vault.disk', 'vault');
        $disk = Storage::disk($diskName);
        $path = sprintf(
            'users/%d/cards/%d/%s%s',
            $user->id,
            $card->id,
            (string) Str::uuid(),
            $extension === null ? '' : '.'.$extension,
        );
        $checksum = hash_file('sha256', $realPath);
        $stored = false;

        try {
            $attachment = DB::transaction(function () use (
                $user, $card, $size, $disk, $diskName, $path, $originalName, $extension,
                $mime, $checksum, $realPath, $wantsThumbnail, &$stored,
            ): Attachment {
                $reserved = User::query()
                    ->whereKey($user->id)
                    ->whereRaw('storage_used_bytes + ? <= storage_quota_bytes', [$size])
                    ->increment('storage_used_bytes', $size);

                if ($reserved === 0) {
                    throw QuotaExceededException::make();
                }

                $stream = fopen($realPath, 'r');

                if ($stream === false) {
                    throw ValidationException::withMessages([
                        'files' => __('attachments.errors.unreadable'),
                    ]);
                }

                try {
                    $disk->put($path, $stream, ['visibility' => 'private']);
                    $stored = true;
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                return Attachment::create([
                    'user_id' => $user->id,
                    'card_id' => $card->id,
                    'disk' => $diskName,
                    'path' => $path,
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'checksum_sha256' => $checksum === false ? null : $checksum,
                    'thumbnail_status' => $wantsThumbnail ? ThumbnailStatus::Pending : ThumbnailStatus::None,
                ]);
            });
        } catch (Throwable $exception) {
            if ($stored) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Objek yatim akan dibersihkan oleh ReconcileStorageUsage.
                }
            }

            throw $exception;
        }

        if ($wantsThumbnail) {
            GenerateThumbnail::dispatch($attachment->id);
        }

        return $attachment;
    }
}
