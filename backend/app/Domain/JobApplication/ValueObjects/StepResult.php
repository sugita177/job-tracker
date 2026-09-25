<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

enum StepResult: string
{
    case PENDING = 'PENDING';
    case PASSED = 'PASSED';
    case FAILED = 'FAILED';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '結果待ち',
            self::PASSED => '通過',
            self::FAILED => 'お見送り',
            self::NOT_APPLICABLE => '判定なし',
        };
    }
}
