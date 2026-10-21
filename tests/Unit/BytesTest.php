<?php

use App\Support\Bytes;

it('converts megabytes to bytes', function () {
    expect(Bytes::megabytes(1))->toBe(1_048_576)
        ->and(Bytes::megabytes(100))->toBe(104_857_600)
        ->and(Bytes::megabytes(0.5))->toBe(524_288);
});

it('formats bytes for display', function () {
    expect(Bytes::human(0))->toBe('0 B')
        ->and(Bytes::human(512))->toBe('512 B')
        ->and(Bytes::human(1024))->toBe('1,0 KB')
        ->and(Bytes::human(1536))->toBe('1,5 KB')
        ->and(Bytes::human(1_048_576))->toBe('1,0 MB')
        ->and(Bytes::human(2_147_483_648))->toBe('2,0 GB');
});

it('clamps negative byte counts to zero', function () {
    expect(Bytes::human(-100))->toBe('0 B');
});
