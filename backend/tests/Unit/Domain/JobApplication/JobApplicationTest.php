<?php

declare(strict_types=1);

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Exceptions\CannotAddStepException;
use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\HistoryType;
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
            ->and($jobApplication->steps)->toBeEmpty();
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

        $casualStep = new SelectionStep(type: StepType::CASUAL_INTERVIEW, scheduledAt: new DateTimeImmutable);
        $firstRoundStep = new SelectionStep(type: StepType::FIRST_ROUND, scheduledAt: new DateTimeImmutable);

        // 1. 検討中（INTERESTED）ではステップ追加不可
        expect(fn () => $jobApplication->addSelectionStep($casualStep))
            ->toThrow(CannotAddStepException::class);

        // 2. カジュアル面談ステータスでは、面談ステップのみ許可
        $jobApplication->advanceStatus(ApplicationStatus::CASUAL_INTERVIEW);
        $jobApplication->addSelectionStep($casualStep);
        expect($jobApplication->steps)->toHaveCount(1);

        // カジュアル面談ステータスで 1次面接の追加は拒絶
        expect(fn () => $jobApplication->addSelectionStep($firstRoundStep))
            ->toThrow(CannotAddStepException::class);
    });

    test('advanceStatus を呼ぶと、通常遷移の StatusHistory が自動記録される', function () {
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

        $changedAt = new DateTimeImmutable('2026-03-05 14:00:00');
        $jobApplication->advanceStatus(ApplicationStatus::INTERVIEW_ADJUSTING, $changedAt);

        $histories = $jobApplication->statusHistories;
        expect($histories)->toHaveCount(1);

        $firstHistory = $histories[0];
        expect($firstHistory->fromStatus)->toBe(ApplicationStatus::DOCUMENT_SCREENING)
            ->and($firstHistory->toStatus)->toBe(ApplicationStatus::INTERVIEW_ADJUSTING)
            ->and($firstHistory->type)->toBe(HistoryType::TRANSITION)
            ->and($firstHistory->reason)->toBeNull()
            ->and($firstHistory->changedAt)->toBe($changedAt);
    });

    test('correctStatus を呼ぶと、訂正理由付きの StatusHistory が記録される', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            currentStatus: ApplicationStatus::REJECTED,
        );

        $changedAt = new DateTimeImmutable('2026-03-06 18:00:00');
        $jobApplication->correctStatus(
            ApplicationStatus::INTERVIEW_IN_PROGRESS,
            '先方の誤送信による差し戻し連絡を受信',
            $changedAt,
        );

        $histories = $jobApplication->statusHistories;
        expect($histories)->toHaveCount(1);

        $firstHistory = $histories[0];
        expect($firstHistory->fromStatus)->toBe(ApplicationStatus::REJECTED)
            ->and($firstHistory->toStatus)->toBe(ApplicationStatus::INTERVIEW_IN_PROGRESS)
            ->and($firstHistory->type)->toBe(HistoryType::CORRECTION)
            ->and($firstHistory->reason)->toBe('先方の誤送信による差し戻し連絡を受信')
            ->and($firstHistory->changedAt)->toBe($changedAt);
    });

    test('correctStatus で不正な理由を指定して失敗した場合、ステータスや履歴は変更前の状態を維持する', function () {
        $jobApplication = new JobApplication(
            userId: 1,
            companyId: 10,
            title: 'バックエンドエンジニア',
            currentStatus: ApplicationStatus::REJECTED,
        );

        // 空の理由で訂正を試みる（例外が発生する）
        try {
            $jobApplication->correctStatus(ApplicationStatus::OFFERED, '   ');
        } catch (InvalidArgumentException) {
            // 例外をキャッチ
        }

        // 例外発生後も、ステータスは REJECTED のままであり、履歴も追加されていないことを検証
        expect($jobApplication->currentStatus)->toBe(ApplicationStatus::REJECTED)
            ->and($jobApplication->statusHistories)->toBeEmpty();
    });

    test('createInterested で検討中エンティティを安全に生成できる', function () {
        $channel = ApplicationChannel::media('媒体名');
        $application = JobApplication::createInterested(
            userId: 1,
            companyId: 10,
            title: 'フルスタックエンジニア',
            priority: Priority::HIGH,
            channel: $channel,
            jobUrl: 'https://example.com/job/1',
            notes: '気になっているポジション',
        );

        expect($application->currentStatus)->toBe(ApplicationStatus::INTERESTED)
            ->and($application->channel)->toBe($channel)
            ->and($application->appliedAt)->toBeNull()
            ->and($application->statusHistories)->toBeEmpty();
    });

    test('createInterested でタイトルが空文字の場合は InvalidArgumentException がスローされる', function () {
        expect(fn () => JobApplication::createInterested(
            userId: 1,
            companyId: 10,
            title: '   ',
        ))->toThrow(InvalidArgumentException::class, '求人タイトルは必須です。');
    });

    test('createApplied で応募済エンティティが生成され、初期履歴が自動記録される', function () {
        $channel = ApplicationChannel::direct();
        $appliedAt = new DateTimeImmutable('2026-10-01 10:00:00');

        $application = JobApplication::createApplied(
            userId: 1,
            companyId: 10,
            title: 'リードエンジニア',
            priority: Priority::HIGH,
            channel: $channel,
            appliedAt: $appliedAt,
        );

        expect($application->currentStatus)->toBe(ApplicationStatus::DOCUMENT_SCREENING)
            ->and($application->channel)->toBe($channel)
            ->and($application->appliedAt)->toBe($appliedAt)
            ->and($application->statusHistories)->toHaveCount(1);

        $history = $application->statusHistories[0];
        expect($history->fromStatus)->toBe(ApplicationStatus::INTERESTED)
            ->and($history->toStatus)->toBe(ApplicationStatus::DOCUMENT_SCREENING)
            ->and($history->type)->toBe(HistoryType::TRANSITION)
            ->and($history->changedAt)->toBe($appliedAt);
    });

    test('update で求人の基本情報および媒体を正しく更新できる', function () {
        $application = JobApplication::createInterested(
            userId: 1,
            companyId: 10,
            title: '変更前タイトル',
        );

        $newChannel = ApplicationChannel::agent('エージェントA');
        $application->update(
            title: '変更後タイトル',
            priority: Priority::LOW,
            jobUrl: 'https://example.com/updated',
            notes: '更新後メモ',
            channel: $newChannel,
        );

        expect($application->title)->toBe('変更後タイトル')
            ->and($application->priority)->toBe(Priority::LOW)
            ->and($application->jobUrl)->toBe('https://example.com/updated')
            ->and($application->notes)->toBe('更新後メモ')
            ->and($application->channel)->toBe($newChannel);
    });

    test('update で検討中ステータスの求人に応募日を設定しようとすると InvalidArgumentException がスローされる', function () {
        $application = JobApplication::createInterested(
            userId: 1,
            companyId: 10,
            title: '検討中求人',
        );

        $appliedAt = new DateTimeImmutable('2026-10-05');

        expect(fn () => $application->update(
            title: '検討中求人',
            priority: Priority::MEDIUM,
            appliedAt: $appliedAt,
        ))->toThrow(InvalidArgumentException::class, '検討中ステータスの求人に応募日を設定することはできません。');
    });
});
