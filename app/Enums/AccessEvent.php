<?php

declare(strict_types=1);

namespace App\Enums;

enum AccessEvent: string
{
    case View = 'view';
    case PasswordOk = 'password_ok';
    case PasswordFailed = 'password_failed';
    case Download = 'download';
    case Blocked = 'blocked';
}
