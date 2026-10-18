<?php

declare(strict_types=1);

namespace App\Enums;

enum ThumbnailStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
