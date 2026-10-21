<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('attachments.errors.quota_exceeded'));
    }
}
