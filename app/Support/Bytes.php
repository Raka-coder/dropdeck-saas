<?php

declare(strict_types=1);

namespace App\Support;

final class Bytes
{
    public static function megabytes(int|float $megabytes): int
    {
        return (int) round($megabytes * 1024 * 1024);
    }

    public static function human(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        if ($unit === 0) {
            return (int) $value.' '.$units[$unit];
        }

        return number_format($value, $precision, ',', '.').' '.$units[$unit];
    }
}
