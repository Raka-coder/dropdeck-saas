<?php

declare(strict_types=1);

namespace App\Enums;

enum DropLinkStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Revoked = 'revoked';
}
