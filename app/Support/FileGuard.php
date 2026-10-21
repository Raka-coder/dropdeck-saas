<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class FileGuard
{
    /**
     * Sanitasi nama tampilan: buang karakter kontrol dan batasi panjangnya.
     */
    public static function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return 'file';
        }

        return Str::limit($name, 255, '');
    }

    public static function extension(?string $name): ?string
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $extension = (string) preg_replace('/[^a-z0-9]/', '', $extension);

        if ($extension === '') {
            return null;
        }

        return substr($extension, 0, 16);
    }

    public static function isForbiddenExtension(?string $extension): bool
    {
        if ($extension === null) {
            return false;
        }

        /** @var array<int, string> $forbidden */
        $forbidden = config('dropdeck.vault.forbidden_extensions', []);

        return in_array($extension, $forbidden, true);
    }

    /**
     * Deteksi MIME dari konten file, bukan dari header yang dikirim klien.
     */
    public static function detectMime(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || $mime === '') {
            return 'application/octet-stream';
        }

        return strtolower($mime);
    }

    public static function isForbiddenMime(string $mime): bool
    {
        /** @var array<int, string> $forbidden */
        $forbidden = config('dropdeck.vault.forbidden_mime_types', []);

        return in_array($mime, $forbidden, true);
    }

    /**
     * Hanya MIME dalam daftar aman yang dikirim apa adanya ke browser.
     */
    public static function safeDownloadMime(string $mime): string
    {
        /** @var array<int, string> $safe */
        $safe = config('dropdeck.vault.safe_mime_types', []);

        return in_array($mime, $safe, true) ? $mime : 'application/octet-stream';
    }

    public static function isImage(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public static function isPdf(string $mime): bool
    {
        return $mime === 'application/pdf';
    }

    /**
     * Header Content-Disposition aman untuk nama file non-ASCII.
     */
    public static function contentDisposition(string $name, bool $inline = false): string
    {
        $fallback = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $fallback = $fallback === '' ? 'file' : $fallback;

        return sprintf(
            "%s; filename=\"%s\"; filename*=UTF-8''%s",
            $inline ? 'inline' : 'attachment',
            $fallback,
            rawurlencode($name),
        );
    }
}
