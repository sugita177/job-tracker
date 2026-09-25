<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

enum Priority: string
{
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';

    /**
     * 表示用の日本語ラベルを取得する
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::HIGH => '高 ★★★',
            self::MEDIUM => '中 ★★☆',
            self::LOW => '低 ★☆☆',
        };
    }

    /**
     * ソート用の数値を取得する
     */
    public function getWeight(): int
    {
        return match ($this) {
            self::HIGH => 3,
            self::MEDIUM => 2,
            self::LOW => 1,
        };
    }
}
