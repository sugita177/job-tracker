<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

enum HistoryType: string
{
    case TRANSITION = 'TRANSITION'; // 通常の業務フロー遷移
    case CORRECTION = 'CORRECTION'; // 誤操作等の訂正

    public function getLabel(): string
    {
        return match ($this) {
            self::TRANSITION => 'フロー遷移',
            self::CORRECTION => '訂正',
        };
    }
}
