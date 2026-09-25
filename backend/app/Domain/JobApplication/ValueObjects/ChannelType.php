<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

enum ChannelType: string
{
    case DIRECT = 'DIRECT';
    case AGENT = 'AGENT';
    case MEDIA = 'MEDIA';
    case REFERRAL = 'REFERRAL';
    case OTHER = 'OTHER';

    /**
     * 表示用の日本語ラベルを取得する
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DIRECT => '直接応募',
            self::AGENT => 'エージェント',
            self::MEDIA => '転職サイト',
            self::REFERRAL => 'リファラル',
            self::OTHER => 'その他',
        };
    }
}
