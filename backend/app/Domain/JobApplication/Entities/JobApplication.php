<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Entities;

use App\Domain\JobApplication\Exceptions\CannotAddStepException;
use App\Domain\JobApplication\Exceptions\IncompleteApplicationException;
use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepType;
use DateTimeImmutable;
use InvalidArgumentException;

final class JobApplication
{
    /**
     * @var list<SelectionStep>
     */
    private array $steps = [];

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
    ) {
    }

    /**
     * @return list<SelectionStep>
     */
    public function getSteps(): array
    {
        return $this->steps;
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
    public function advanceStatus(ApplicationStatus $nextStatus): void
    {
        if (! $this->currentStatus->canTransitionTo($nextStatus)) {
            throw InvalidStatusTransitionException::notAllowed($this->currentStatus, $nextStatus);
        }

        // 書類選考に進む際は、応募媒体と応募日が必須
        if ($nextStatus === ApplicationStatus::DOCUMENT_SCREENING && ($this->channel === null || $this->appliedAt === null)) {
            throw IncompleteApplicationException::missingChannelOrAppliedAt();
        }

        $this->currentStatus = $nextStatus;
    }

    /**
     * 誤操作・連絡ミス等の例外対応としてステータスを訂正する（理由必須）
     */
    public function correctStatus(ApplicationStatus $correctedStatus, string $reason): void{
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('ステータス訂正の理由は必須です。');
        }

        $this->currentStatus = $correctedStatus;
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
