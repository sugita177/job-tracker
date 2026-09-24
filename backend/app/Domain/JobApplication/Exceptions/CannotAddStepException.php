<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Exceptions;

use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use DomainException;

final class CannotAddStepException extends DomainException
{
    public static function notAllowedInStatus(ApplicationStatus $status): self
    {
        return new self(sprintf('ステータス「%s」の応募には選考ステップを追加できません。', $status->getLabel()));
    }

    public static function onlyCasualInterviewAllowed(): self
    {
        return new self('カジュアル面談ステータスでは、カジュアル面談以外のステップを追加できません。');
    }
}
