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
use App\Domain\JobApplication\ValueObjects\StepResult;
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
            changedAt: $changedAt ?? new DateTimeImmutable,
        );
    }

    /**
     * 誤操作・連絡ミス等の例外対応としてステータスを訂正する（理由必須）
     */
    public function correctStatus(
        ApplicationStatus $correctedStatus,
        string $reason,
        ?DateTimeImmutable $changedAt = null,
    ): void {
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
            changedAt: $changedAt ?? new DateTimeImmutable,
        );

        // 例外が出なかった場合のみ、集約の状態を変更して配列に追加する
        $this->currentStatus = $correctedStatus;
        $this->statusHistories[] = $history;
    }

    /**
     * 選考・面談ステップを追加する（不変条件の保護）
     */
    public function addSelectionStep(SelectionStep $step): void
    {
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

    /**
     * 検討中（INTERESTED）として新規作成する
     */
    public static function createInterested(
        int $userId,
        int $companyId,
        string $title,
        Priority $priority = Priority::MEDIUM,
        ?ApplicationChannel $channel = null,
        ?string $jobUrl = null,
        ?string $notes = null,
    ): self {
        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            throw new InvalidArgumentException('求人タイトルは必須です。');
        }

        return new self(
            userId: $userId,
            companyId: $companyId,
            title: $trimmedTitle,
            priority: $priority,
            channel: $channel,
            currentStatus: ApplicationStatus::INTERESTED,
            appliedAt: null,
            jobUrl: $jobUrl,
            notes: $notes,
        );
    }

    /**
     * 応募済み（DOCUMENT_SCREENING）として新規作成する
     */
    public static function createApplied(
        int $userId,
        int $companyId,
        string $title,
        Priority $priority,
        ApplicationChannel $channel,
        DateTimeImmutable $appliedAt,
        ?string $jobUrl = null,
        ?string $notes = null,
    ): self {
        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            throw new InvalidArgumentException('求人タイトルは必須です。');
        }

        // 初期応募履歴を自動生成
        $initialHistory = new StatusHistory(
            fromStatus: ApplicationStatus::INTERESTED,
            toStatus: ApplicationStatus::DOCUMENT_SCREENING,
            type: HistoryType::TRANSITION,
            reason: null,
            changedAt: $appliedAt,
        );

        return new self(
            userId: $userId,
            companyId: $companyId,
            title: $trimmedTitle,
            priority: $priority,
            channel: $channel,
            currentStatus: ApplicationStatus::DOCUMENT_SCREENING,
            appliedAt: $appliedAt,
            jobUrl: $jobUrl,
            notes: $notes,
            statusHistories: [$initialHistory],
        );
    }

    /**
     * 求人情報を更新する
     *
     * @param ApplicationChannel|null $channel 媒体はいつでも変更可能
     * @param DateTimeImmutable|null $appliedAt 応募日は検討中以外（応募済）の場合のみ変更可能
     */
    public function update(
        string $title,
        Priority $priority,
        ?string $jobUrl = null,
        ?string $notes = null,
        ?ApplicationChannel $channel = null,
        ?DateTimeImmutable $appliedAt = null,
    ): void {
        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            throw new InvalidArgumentException('求人タイトルは必須です。');
        }

        $this->title = $trimmedTitle;
        $this->priority = $priority;
        $this->jobUrl = $jobUrl;
        $this->notes = $notes;

        // 媒体は検討中でも選考中でも更新可能
        if ($channel !== null) {
            $this->channel = $channel;
        }

        // 応募日の更新制御
        if ($appliedAt !== null) {
            if ($this->currentStatus === ApplicationStatus::INTERESTED) {
                throw new InvalidArgumentException('検討中ステータスの求人に応募日を設定することはできません。');
            }
            $this->appliedAt = $appliedAt;
        }
    }

    /**
     * 選考ステップを見つける
     */
    public function findSelectionStep(int $stepId): ?SelectionStep
    {
        foreach ($this->steps as $step) {
            if ($step->id === $stepId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * 選考ステップの日程を再設定する
     */
    public function rescheduleSelectionStep(
        int $stepId,
        DateTimeImmutable $newScheduleAt,
        ?string $newLocationOrUrl = null,
    ): SelectionStep {
        $step = $this->findSelectionStep($stepId);
        if ($step === null) {
            throw new InvalidArgumentException('指定された選考ステップが見つかりません。');
        }

        $step->reschedule($newScheduleAt, $newLocationOrUrl);

        return $step;
    }

    /**
     * 選考ステップの振り返りメモと結果を記録する
     */
    public function recordStepReview(
        int $stepId,
        string $reviewMemo,
        StepResult $result,
    ): SelectionStep {
        $step = $this->findSelectionStep($stepId);
        if ($step === null) {
            throw new InvalidArgumentException('指定された選考ステップが見つかりません。');
        }

        $step->recordReview($reviewMemo, $result);

        return $step;
    }

    /**
     * 選考ステップの事前準備情報を更新する
     */
    public function updateStepPreparation(
        int $stepId,
        ?string $locationOrUrl = null,
        ?string $interviewerInfo = null,
        ?string $prepMemo = null,
        ?StepType $type = null,
    ): SelectionStep {
        $step = $this->findSelectionStep($stepId);
        if ($step === null) {
            throw new InvalidArgumentException('指定された選考ステップが見つかりません。');
        }

        if ($type !== null && $this->currentStatus === ApplicationStatus::CASUAL_INTERVIEW && $type !== StepType::CASUAL_INTERVIEW) {
            throw CannotAddStepException::onlyCasualInterviewAllowed();
        }

        $step->updatePreparation($locationOrUrl, $interviewerInfo, $prepMemo, $type);

        return $step;
    }

    /**
     * 選考ステップを削除する
     */
    public function removeSelectionStep(int $stepId): void
    {
        $filtered = array_filter($this->steps, fn (SelectionStep $s) => $s->id !== $stepId);

        if (count($filtered) === count($this->steps)) {
            throw new InvalidArgumentException('指定された選考ステップが見つかりません。');
        }

        $this->steps = array_values($filtered);
    }
}
