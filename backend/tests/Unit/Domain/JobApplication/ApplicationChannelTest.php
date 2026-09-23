<?php

use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ChannelType;

// インスタンス生成と空白トリムの検証
it('creates an application channel and trims detail name', function () {
    $channel = new ApplicationChannel(ChannelType::MEDIA, '  転職サービス名  ');

    expect($channel->type)->toBe(ChannelType::MEDIA);
    expect($channel->detailName)->toBe('転職サービス名');
});

it('normalizes empty detail name to null', function () {
    $channel = new ApplicationChannel(ChannelType::DIRECT, '   ');

    expect($channel->type)->toBe(ChannelType::DIRECT);
    expect($channel->detailName)->toBeNull();
});

// DDD値オブジェクトの等価性 (equals) の検証
it('identifies equality based on properties', function () {
    $channel1 = new ApplicationChannel(ChannelType::AGENT, 'A社');
    $channel2 = new ApplicationChannel(ChannelType::AGENT, 'A社');
    $channel3 = new ApplicationChannel(ChannelType::AGENT, 'B社');
    $channel4 = new ApplicationChannel(ChannelType::DIRECT, 'A社');

    expect($channel1->equals($channel2))->toBeTrue();
    expect($channel1->equals($channel3))->toBeFalse();
    expect($channel1->equals($channel4))->toBeFalse();
});

// 表示名 (getDisplayName) の検証
it('formats display name correctly', function () {
    $withDetail = new ApplicationChannel(ChannelType::MEDIA, 'サービス名');
    $withoutDetail = new ApplicationChannel(ChannelType::DIRECT, null);

    expect($withDetail->getDisplayName())->toBe('転職サイト (サービス名)');
    expect($withoutDetail->getDisplayName())->toBe('直接応募');
});
