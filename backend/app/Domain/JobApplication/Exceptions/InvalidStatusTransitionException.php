<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Exceptions;

use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public static function notAllowed(ApplicationStatus $currentStatus, ApplicationStatus $targetStatus): self
    {
        return new self(
            message: "ステータス {$currentStatus->getLabel()} から {$targetStatus->getLabel()} への遷移は許可されていません。"
        );
    }
}
