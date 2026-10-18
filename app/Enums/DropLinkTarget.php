<?php

declare(strict_types=1);

namespace App\Enums;

enum DropLinkTarget: string
{
    case File = 'file';
    case Card = 'card';
}
