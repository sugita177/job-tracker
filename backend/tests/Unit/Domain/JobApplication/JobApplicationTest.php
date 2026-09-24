<?php

declare(strict_types=1);

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Exceptions\CannotAddStepException;
use App\Domain\JobApplication\Exceptions\IncompleteApplicationException;
use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepType;

describe('JobApplication 集約ルート', function () {
    test('初期状態「検討中」として作成できる（応募日・媒体はnull許容）', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            priority: Priority::HIGH,
        );

        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::INTERESTED)
            ->and($jobApplication->channel)->toBeNull()
            ->and($jobApplication->appliedAt)->toBeNull()
            ->and($jobApplication->getSteps())->toBeEmpty();
    });

    test('正式応募（apply）を実行すると、応募媒体・応募日が記録され書類選考中になる', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
        );

        $channel = ApplicationChannel::direct();
        $appliedAt = new DateTimeImmutable('2026-03-01 10:00:00');

        $jobApplication->apply($channel, $appliedAt);

        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::DOCUMENT_SCREENING)
            ->and($jobApplication->channel)->toBe($channel)
            ->and($jobApplication->appliedAt)->toBe($appliedAt);
    });

    test('順遷移マトリクスに従って advanceStatus でステータスを進められる', function () {
        $channel = ApplicationChannel::agent('エージェント');
        $appliedAt = new DateTimeImmutable('2026-03-01 10:00:00');

        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            channel: $channel,
            currentStatus: ApplicationStatus::DOCUMENT_SCREENING,
            appliedAt: $appliedAt,
        );

        $jobApplication->advanceStatus(ApplicationStatus::INTERVIEW_ADJUSTING);
        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::INTERVIEW_ADJUSTING);

        $jobApplication->advanceStatus(ApplicationStatus::INTERVIEW_IN_PROGRESS);
        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::INTERVIEW_IN_PROGRESS);
    });

    test('不正な順遷移を試みると InvalidStatusTransitionException がスローされる', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            currentStatus: ApplicationStatus::INTERESTED,
        );

        // 検討中からいきなり内定は不可
        $jobApplication->advanceStatus(ApplicationStatus::OFFERED);
    })->throws(InvalidStatusTransitionException::class);

    test('correctStatus で任意のステータスに訂正できるが、理由は必須（空文字は例外）', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            currentStatus: ApplicationStatus::REJECTED,
        );

        // 理由付きで誤操作訂正（お見送り → 面接進行中へ戻す）
        $jobApplication->correctStatus(ApplicationStatus::INTERVIEW_IN_PROGRESS, '先方からの連絡ミスによる差し戻し');
        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::INTERVIEW_IN_PROGRESS);

        // 空の理由は禁止
        expect(fn () => $jobApplication->correctStatus(ApplicationStatus::OFFERED, '   '))
            ->toThrow(InvalidArgumentException::class);
    });

    test('選考ステップ追加の不変条件が保護される', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            currentStatus: ApplicationStatus::INTERESTED,
        );

        $casualStep = new SelectionStep(type: StepType::CASUAL_INTERVIEW, scheduledAt: new DateTimeImmutable());
        $firstRoundStep = new SelectionStep(type: StepType::FIRST_ROUND, scheduledAt: new DateTimeImmutable());

        // 1. 検討中（INTERESTED）ではステップ追加不可
        expect(fn () => $jobApplication->addSelectionStep($casualStep))
            ->toThrow(CannotAddStepException::class);

        // 2. カジュアル面談ステータスでは、面談ステップのみ許可
        $jobApplication->advanceStatus(ApplicationStatus::CASUAL_INTERVIEW);
        $jobApplication->addSelectionStep($casualStep);
        expect($jobApplication->getSteps())->toHaveCount(1);

        // カジュアル面談ステータスで 1次面接の追加は拒絶
        expect(fn () => $jobApplication->addSelectionStep($firstRoundStep))
            ->toThrow(CannotAddStepException::class);
    });
});
