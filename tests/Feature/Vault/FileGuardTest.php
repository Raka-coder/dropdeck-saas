<?php

use App\Support\FileGuard;

it('sanitizes display names', function () {
    expect(FileGuard::sanitizeName('../../etc/passwd'))->toBe('passwd')
        ->and(FileGuard::sanitizeName('laporan "akhir".pdf'))->toBe('laporan "akhir".pdf')
        ->and(FileGuard::sanitizeName("nama\x00kontrol.txt"))->toBe('namakontrol.txt')
        ->and(FileGuard::sanitizeName('   '))->toBe('file');
});

it('normalizes extensions', function () {
    expect(FileGuard::extension('Laporan.PDF'))->toBe('pdf')
        ->and(FileGuard::extension('arsip.tar.gz'))->toBe('gz')
        ->and(FileGuard::extension('tanpa-ekstensi'))->toBeNull()
        ->and(FileGuard::extension('aneh.p.h.p'))->toBe('p');
});

it('rejects forbidden extensions from config', function () {
    expect(FileGuard::isForbiddenExtension('php'))->toBeTrue()
        ->and(FileGuard::isForbiddenExtension('exe'))->toBeTrue()
        ->and(FileGuard::isForbiddenExtension('jpg'))->toBeFalse()
        ->and(FileGuard::isForbiddenExtension(null))->toBeFalse();
});

it('detects mime from file content', function () {
    $path = (string) tempnam(sys_get_temp_dir(), 'dropdeck-mime-');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");

    try {
        expect(FileGuard::detectMime($path))->toBe('application/pdf');
    } finally {
        unlink($path);
    }
});

it('rejects forbidden mime types from config', function () {
    expect(FileGuard::isForbiddenMime('text/html'))->toBeTrue()
        ->and(FileGuard::isForbiddenMime('image/svg+xml'))->toBeTrue()
        ->and(FileGuard::isForbiddenMime('application/pdf'))->toBeFalse();
});

it('only forwards safe mime types to the browser', function () {
    expect(FileGuard::safeDownloadMime('application/pdf'))->toBe('application/pdf')
        ->and(FileGuard::safeDownloadMime('text/html'))->toBe('application/octet-stream')
        ->and(FileGuard::safeDownloadMime('application/x-dosexec'))->toBe('application/octet-stream');
});

it('classifies images and pdfs', function () {
    expect(FileGuard::isImage('image/png'))->toBeTrue()
        ->and(FileGuard::isImage('image/svg+xml'))->toBeFalse()
        ->and(FileGuard::isPdf('application/pdf'))->toBeTrue()
        ->and(FileGuard::isPdf('text/plain'))->toBeFalse();
});

it('builds an rfc 5987 content disposition header', function () {
    $header = FileGuard::contentDisposition('laporan akhir "v2".pdf');

    expect($header)
        ->toStartWith('attachment; filename="laporan_akhir__v2_.pdf"; filename*=UTF-8\'\'')
        ->toContain(rawurlencode('laporan akhir "v2".pdf'));

    expect(FileGuard::contentDisposition('foto.png', inline: true))
        ->toStartWith('inline; filename="foto.png"');
});
