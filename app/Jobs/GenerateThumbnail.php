<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ThumbnailStatus;
use App\Models\Attachment;
use App\Support\FileGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

final class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $attachmentId) {}

    public function handle(): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if ($attachment === null) {
            return;
        }

        $isPdf = FileGuard::isPdf($attachment->mime_type)
            && (bool) config('dropdeck.vault.pdf_thumbnail_enabled');

        if (! FileGuard::isImage($attachment->mime_type) && ! $isPdf) {
            $attachment->forceFill(['thumbnail_status' => ThumbnailStatus::None])->save();

            return;
        }

        $attachment->forceFill(['thumbnail_status' => ThumbnailStatus::Pending])->save();

        $temporaryPath = null;
        $storage = Storage::disk($attachment->disk);

        try {
            $stream = $storage->readStream($attachment->path);

            if ($stream === null) {
                throw new RuntimeException('Objek lampiran tidak ditemukan di storage.');
            }

            $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'dropdeck-thumb-');
            $target = fopen($temporaryPath, 'wb');

            if ($target === false) {
                throw new RuntimeException('Gagal membuat berkas sementara.');
            }

            stream_copy_to_stream($stream, $target);
            fclose($target);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->assertSourceSize($temporaryPath, $isPdf);

            $driver = extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class;
            $manager = new ImageManager($driver);
            $image = $manager->decodePath($temporaryPath);

            $image->scaleDown(width: (int) config('dropdeck.vault.thumbnail.max_dimension'));

            $encoded = $image->encode(new WebpEncoder(
                quality: (int) config('dropdeck.vault.thumbnail.quality'),
                strip: true,
            ));

            $thumbnailPath = dirname($attachment->path)
                .'/thumbs/'
                .pathinfo($attachment->path, PATHINFO_FILENAME)
                .'.webp';

            $storage->put($thumbnailPath, $encoded->toString(), ['visibility' => 'private']);

            $attachment->forceFill([
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_status' => ThumbnailStatus::Ready,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $attachment->forceFill(['thumbnail_status' => ThumbnailStatus::Failed])->save();
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function assertSourceSize(string $path, bool $isPdf): void
    {
        if ($isPdf) {
            return;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
        }

        $pixels = (int) $info[0] * (int) $info[1];

        if ($pixels > (int) config('dropdeck.vault.thumbnail.max_source_pixels')) {
            throw new RuntimeException('Dimensi gambar melebihi batas pemrosesan.');
        }
    }
}
