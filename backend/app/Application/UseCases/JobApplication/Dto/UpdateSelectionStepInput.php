<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

use DateTimeImmutable;

final readonly class UpdateSelectionStepInput
{
    public function __construct(
        public int $jobApplicationId,
        public int $stepId,
        public int $userId,
        public ?string $type = null,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?string $locationOrUrl = null,
        public ?string $interviewerInfo = null,
        public ?string $prepMemo = null,
        public ?string $reviewMemo = null,
        public ?string $result = null,
    ) {}
}
