<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Exceptions;

use DomainException;

final class IncompleteApplicationException extends DomainException
{
    public static function missingChannelOrAppliedAt(): self
    {
        return new self('応募媒体および応募日の設定が必須です。');
    }
}
