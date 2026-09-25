<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

enum StepType: string
{
    case CASUAL_INTERVIEW = 'CASUAL_INTERVIEW';
    case FIRST_ROUND = 'FIRST_ROUND';
    case SECOND_ROUND = 'SECOND_ROUND';
    case FINAL_ROUND = 'FINAL_ROUND';
    case CODING_TEST = 'CODING_TEST';
    case OTHER = 'OTHER';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASUAL_INTERVIEW => 'カジュアル面談',
            self::FIRST_ROUND => '1次面接',
            self::SECOND_ROUND => '2次面接',
            self::FINAL_ROUND => '最終面接',
            self::CODING_TEST => 'コーディング試験',
            self::OTHER => 'その他',
        };
    }

    public function isCasualInterview(): bool
    {
        return $this === self::CASUAL_INTERVIEW;
    }
}
