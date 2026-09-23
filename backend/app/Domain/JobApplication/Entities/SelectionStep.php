<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Entities;

use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;
use DateTimeImmutable;

final class SelectionStep
{
    /**
     * @param ?int $id 永続化前の新規エンティティは null、DB採番後は ID
     */
    public function __construct(
        public StepType $type,
        public private(set) ?DateTimeImmutable $scheduledAt,
        public private(set) ?string $locationOrUrl = null,
        public private(set) ?string $interviewerInfo = null,
        public private(set) ?string $prepMemo = null,
        public private(set) ?string $reviewMemo = null,
        public private(set) StepResult $result = StepResult::PENDING,
        public readonly ?int $id = null,
    ) {
    }

    /**
     * 面接の振り返りメモと結果を記録する
     */
    public function recordReview(string $reviewMemo, StepResult $result): void
    {
        $trimmed = trim($reviewMemo);
        $this->reviewMemo = $trimmed === '' ? null : $trimmed;
        $this->result = $result;
    }

    /**
     * 面接日程および場所/URLを再設定（リスケジュール）する
     */
    public function reschedule(DateTimeImmutable $newScheduleAt, ?string $newLocationOrUrl = null): void{
        $this->scheduledAt = $newScheduleAt;
        if ($newLocationOrUrl !== null) {
            $this->locationOrUrl = trim($newLocationOrUrl);
        }
    }
}
