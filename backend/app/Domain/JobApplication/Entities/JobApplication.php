<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Entities;

use App\Domain\JobApplication\Exceptions\CannotAddStepException;
use App\Domain\JobApplication\Exceptions\IncompleteApplicationException;
use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\HistoryType;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepType;
use DateTimeImmutable;
use InvalidArgumentException;

final class JobApplication
{
    public function __construct(
        public readonly int $userId,
        public readonly int $companyId,
        public private(set) string $title,
        public private(set) Priority $priority = Priority::MEDIUM,
        public private(set) ?ApplicationChannel $channel = null,
        public private(set) ApplicationStatus $currentStatus = ApplicationStatus::INTERESTED,
        public private(set) ?DateTimeImmutable $appliedAt = null,
        public private(set) ?string $jobUrl = null,
        public private(set) ?string $notes = null,
        public readonly ?int $id = null,
        /** @var list<SelectionStep> $steps */
        public private(set) array $steps = [],
        /** @var list<StatusHistory> $statusHistories */
        public private(set) array $statusHistories = [],
    ) {
        $this->steps = $steps;
        $this->statusHistories = $statusHistories;
    }

    /**
     * 正式応募を実行する
     */
    public function apply(ApplicationChannel $channel, DateTimeImmutable $appliedAt): void
    {
        $this->channel = $channel;
        $this->appliedAt = $appliedAt;
        $this->advanceStatus(ApplicationStatus::DOCUMENT_SCREENING);
    }

    /**
     * 通常の業務フローに従ってステータスを順遷移させる
     */
    public function advanceStatus(ApplicationStatus $nextStatus, ?DateTimeImmutable $changedAt = null): void
    {
        if (! $this->currentStatus->canTransitionTo($nextStatus)) {
            throw InvalidStatusTransitionException::notAllowed($this->currentStatus, $nextStatus);
        }

        // 書類選考に進む際は、応募媒体と応募日が必須
        if ($nextStatus === ApplicationStatus::DOCUMENT_SCREENING && ($this->channel === null || $this->appliedAt === null)) {
            throw IncompleteApplicationException::missingChannelOrAppliedAt();
        }

        $fromStatus = $this->currentStatus;
        $this->currentStatus = $nextStatus;

        $this->statusHistories[] = new StatusHistory(
            fromStatus: $fromStatus,
            toStatus: $nextStatus,
            type: HistoryType::TRANSITION,
            reason: null,
            changedAt: $changedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * 誤操作・連絡ミス等の例外対応としてステータスを訂正する（理由必須）
     */
    public function correctStatus(
        ApplicationStatus $correctedStatus,
        string $reason,
        ?DateTimeImmutable $changedAt = null,
    ): void{
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('ステータス訂正の理由は必須です。');
        }

        $fromStatus = $this->currentStatus;

        // StatusHistory を生成（訂正で理由が空文字ならここで例外が飛び、処理が中断する）
        $history = new StatusHistory(
            fromStatus: $fromStatus,
            toStatus: $correctedStatus,
            type: HistoryType::CORRECTION,
            reason: $trimmedReason,
            changedAt: $changedAt ?? new DateTimeImmutable(),
        );

        // 例外が出なかった場合のみ、集約の状態を変更して配列に追加する
        $this->currentStatus = $correctedStatus;
        $this->statusHistories[] = $history;
    }

    /**
     * 選考・面談ステップを追加する（不変条件の保護）
     */
    public function addSelectionStep(SelectionStep $step): void{
        // 検討中および完了ステータスでは追加不可
        if (in_array($this->currentStatus, [
            ApplicationStatus::INTERESTED,
            ApplicationStatus::ACCEPTED,
            ApplicationStatus::REJECTED,
            ApplicationStatus::WITHDRAWN,
            ApplicationStatus::SKIPPED,
        ], true)) {
            throw CannotAddStepException::notAllowedInStatus($this->currentStatus);
        }

        // カジュアル面談ステータス時はカジュアル面談のみ追加可能
        if ($this->currentStatus === ApplicationStatus::CASUAL_INTERVIEW && $step->type !== StepType::CASUAL_INTERVIEW) {
            throw CannotAddStepException::onlyCasualInterviewAllowed();
        }

        $this->steps[] = $step;
    }
}
