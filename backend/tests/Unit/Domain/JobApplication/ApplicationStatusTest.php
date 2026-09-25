<?php

use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;

it('returns correct label for each status', function (ApplicationStatus $status, string $expectedLabel) {
    expect($status->getLabel())->toBe($expectedLabel);
})->with([
    '検討中' => [ApplicationStatus::INTERESTED, '検討中'],
    'カジュアル面談中' => [ApplicationStatus::CASUAL_INTERVIEW, 'カジュアル面談中'],
    '書類選考中' => [ApplicationStatus::DOCUMENT_SCREENING, '書類選考中'],
    '面接日程調整中' => [ApplicationStatus::INTERVIEW_ADJUSTING, '面接日程調整中'],
    '面接進行中' => [ApplicationStatus::INTERVIEW_IN_PROGRESS, '面接進行中'],
    '内定' => [ApplicationStatus::OFFERED, '内定'],
    '内定承諾' => [ApplicationStatus::ACCEPTED, '内定承諾'],
    'お見送り' => [ApplicationStatus::REJECTED, 'お見送り'],
    '辞退' => [ApplicationStatus::WITHDRAWN, '辞退'],
    '検討見送り' => [ApplicationStatus::SKIPPED, '検討見送り'],
]);

// 正常系: 許可された順遷移のマトリクステスト
it('allows valid normal transitions', function (ApplicationStatus $from, ApplicationStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
    expect($from->transitionTo($to))->toBe($to);
})->with([
    // 検討中からの遷移
    '検討中 -> カジュアル面談中' => [ApplicationStatus::INTERESTED, ApplicationStatus::CASUAL_INTERVIEW],
    '検討中 -> 書類選考中' => [ApplicationStatus::INTERESTED, ApplicationStatus::DOCUMENT_SCREENING],
    '検討中 -> 検討見送り' => [ApplicationStatus::INTERESTED, ApplicationStatus::SKIPPED],
    // カジュアル面談中からの遷移
    'カジュアル面談中 -> 書類選考中' => [ApplicationStatus::CASUAL_INTERVIEW, ApplicationStatus::DOCUMENT_SCREENING],
    'カジュアル面談中 -> 面接調整中' => [ApplicationStatus::CASUAL_INTERVIEW, ApplicationStatus::INTERVIEW_ADJUSTING],
    'カジュアル面談中 -> 検討見送り' => [ApplicationStatus::CASUAL_INTERVIEW, ApplicationStatus::SKIPPED],
    'カジュアル面談中 -> 辞退' => [ApplicationStatus::CASUAL_INTERVIEW, ApplicationStatus::WITHDRAWN],
    // 書類選考中からの遷移
    '書類選考中 -> 面接調整中' => [ApplicationStatus::DOCUMENT_SCREENING, ApplicationStatus::INTERVIEW_ADJUSTING],
    '書類選考中 -> お見送り' => [ApplicationStatus::DOCUMENT_SCREENING, ApplicationStatus::REJECTED],
    '書類選考中 -> 辞退' => [ApplicationStatus::DOCUMENT_SCREENING, ApplicationStatus::WITHDRAWN],
    // 面接日程調整中からの遷移
    '面接調整中 -> 面接進行中' => [ApplicationStatus::INTERVIEW_ADJUSTING, ApplicationStatus::INTERVIEW_IN_PROGRESS],
    '面接調整中 -> お見送り' => [ApplicationStatus::INTERVIEW_ADJUSTING, ApplicationStatus::REJECTED],
    '面接調整中 -> 辞退' => [ApplicationStatus::INTERVIEW_ADJUSTING, ApplicationStatus::WITHDRAWN],
    // 面接進行中からの遷移
    '面接進行中 -> 面接調整中(次回へ)' => [ApplicationStatus::INTERVIEW_IN_PROGRESS, ApplicationStatus::INTERVIEW_ADJUSTING],
    '面接進行中 -> 内定' => [ApplicationStatus::INTERVIEW_IN_PROGRESS, ApplicationStatus::OFFERED],
    '面接進行中 -> お見送り' => [ApplicationStatus::INTERVIEW_IN_PROGRESS, ApplicationStatus::REJECTED],
    '面接進行中 -> 辞退' => [ApplicationStatus::INTERVIEW_IN_PROGRESS, ApplicationStatus::WITHDRAWN],
    // 内定からの遷移
    '内定 -> 内定承諾' => [ApplicationStatus::OFFERED, ApplicationStatus::ACCEPTED],
    '内定 -> 辞退' => [ApplicationStatus::OFFERED, ApplicationStatus::WITHDRAWN],
]);

// 異常系: 禁止された不正遷移のテスト（例外がスローされること）
it('prevents invalid transitions and throws exception', function (ApplicationStatus $from, ApplicationStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
    $from->transitionTo($to);
})->throws(InvalidStatusTransitionException::class)->with([
    '検討中 -> 内定 (飛び級は不可)' => [ApplicationStatus::INTERESTED, ApplicationStatus::OFFERED],
    '検討中 -> 面接進行中 (選考経由なしは不可)' => [ApplicationStatus::INTERESTED, ApplicationStatus::INTERVIEW_IN_PROGRESS],
    '書類選考中 -> 内定 (面接なし内定は不可)' => [ApplicationStatus::DOCUMENT_SCREENING, ApplicationStatus::OFFERED],
    '内定承諾 -> 検討中 (完了後の再遷移は不可)' => [ApplicationStatus::ACCEPTED, ApplicationStatus::INTERESTED],
    'お見送り -> 面接調整中 (完了後の再遷移は不可)' => [ApplicationStatus::REJECTED, ApplicationStatus::INTERVIEW_ADJUSTING],
    '辞退 -> 内定 (辞退後の復活は不可)' => [ApplicationStatus::WITHDRAWN, ApplicationStatus::OFFERED],
    '検討見送り -> 書類選考中 (見送り後の順遷移は不可)' => [ApplicationStatus::SKIPPED, ApplicationStatus::DOCUMENT_SCREENING],
]);
