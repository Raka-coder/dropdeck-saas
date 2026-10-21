<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Position helper for fractional ordering with a fixed gap.
 *
 * Rows are spaced by GAP so a drag can be persisted by updating a single row.
 * When two neighbours are too close, the caller renumbers the list (rebalance).
 */
final class Position
{
    public const GAP = 1024;

    /**
     * Position after the current maximum.
     */
    public static function next(?int $max = null): int
    {
        return ($max ?? 0) + self::GAP;
    }

    /**
     * Position strictly between two neighbours.
     *
     * Returns null when there is no integer room left and the list must be rebalanced.
     */
    public static function between(?int $previous, ?int $next): ?int
    {
        if ($previous === null && $next === null) {
            return self::GAP;
        }

        if ($previous === null) {
            return $next <= 1 ? null : intdiv($next, 2);
        }

        if ($next === null) {
            return $previous + self::GAP;
        }

        if ($next - $previous <= 1) {
            return null;
        }

        return intdiv($previous + $next, 2);
    }

    /**
     * Evenly spaced positions for a list of the given length.
     *
     * @return list<int>
     */
    public static function sequence(int $count): array
    {
        $positions = [];

        for ($index = 0; $index < $count; $index++) {
            $positions[] = ($index + 1) * self::GAP;
        }

        return $positions;
    }
}
