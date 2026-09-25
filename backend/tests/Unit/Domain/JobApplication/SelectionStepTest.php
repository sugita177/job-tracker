<?php

use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;

// 初期生成とデフォルト値の検証
it('can be instantiated with initial scheduled state', function () {
    $scheduledAt = new DateTimeImmutable('2026-10-15 19:00:00');

    $step = new SelectionStep(
        type: StepType::FIRST_ROUND,
        scheduledAt: $scheduledAt,
        locationOrUrl: 'https://meet.google.com/xxx-yyyy-zzz',
        interviewerInfo: 'エンジニアマネージャー 田中様',
        prepMemo: '自社SaaSのDDD導入背景について逆質問する',
    );

    expect($step->id)->toBeNull(); // 新規作成時は ID 未採番
    expect($step->type)->toBe(StepType::FIRST_ROUND);
    expect($step->scheduledAt)->toBe($scheduledAt);
    expect($step->locationOrUrl)->toBe('https://meet.google.com/xxx-yyyy-zzz');
    expect($step->interviewerInfo)->toBe('エンジニアマネージャー 田中様');
    expect($step->prepMemo)->toBe('自社SaaSのDDD導入背景について逆質問する');
    expect($step->reviewMemo)->toBeNull(); // 初期は未記入
    expect($step->result)->toBe(StepResult::PENDING); // 初期は結果待ち
});

// 振り返りと結果の記録 (recordReview) の振る舞い
it('can record review memo and result', function () {
    $step = new SelectionStep(
        type: StepType::FIRST_ROUND,
        scheduledAt: new DateTimeImmutable('2026-10-15 19:00:00'),
    );

    $step->recordReview(
        reviewMemo: '  技術選定の経緯で盛り上がった。手応えあり。  ',
        result: StepResult::PASSED,
    );

    // 空白がトリムされて更新されること
    expect($step->reviewMemo)->toBe('技術選定の経緯で盛り上がった。手応えあり。');
    expect($step->result)->toBe(StepResult::PASSED);
});

// 日程変更 (reschedule) の振る舞い
it('can be rescheduled with new datetime and location', function () {
    $originalTime = new DateTimeImmutable('2026-10-15 19:00:00');
    $step = new SelectionStep(
        type: StepType::FIRST_ROUND,
        scheduledAt: $originalTime,
        locationOrUrl: 'https://meet.google.com/old-url',
    );

    $newTime = new DateTimeImmutable('2026-10-18 20:00:00');
    $step->reschedule(
        newScheduleAt: $newTime,
        newLocationOrUrl: 'https://meet.google.com/new-url',
    );

    expect($step->scheduledAt)->toBe($newTime);
    expect($step->locationOrUrl)->toBe('https://meet.google.com/new-url');
});
