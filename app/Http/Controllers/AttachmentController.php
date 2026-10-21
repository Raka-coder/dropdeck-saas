<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\FileGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, string $attachment): StreamedResponse
    {
        $model = $this->resolveOwned($request, $attachment);

        Gate::authorize('view', $model);

        $disk = Storage::disk($model->disk);
        $stream = $disk->readStream($model->path);

        abort_if($stream === null, 404);

        return response()->stream(
            function () use ($stream): void {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => FileGuard::safeDownloadMime($model->mime_type),
                'Content-Length' => (string) $model->size_bytes,
                'Content-Disposition' => FileGuard::contentDisposition($model->original_name),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function thumbnail(Request $request, string $attachment): StreamedResponse
    {
        $model = $this->resolveOwned($request, $attachment);

        Gate::authorize('view', $model);

        $path = $model->thumbnail_path;

        abort_if($path === null, 404);

        $stream = Storage::disk($model->disk)->readStream($path);

        abort_if($stream === null, 404);

        return response()->stream(
            function () use ($stream): void {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => 'image/webp',
                'Content-Disposition' => FileGuard::contentDisposition(
                    pathinfo($model->original_name, PATHINFO_FILENAME).'.webp',
                    inline: true,
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function resolveOwned(Request $request, string $attachment): Attachment
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return $user->attachments()->whereKey($attachment)->firstOrFail();
    }
}
