<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

use DateTimeImmutable;

final readonly class AddSelectionStepInput
{
    public function __construct(
        public int $jobApplicationId,
        public int $userId,
        public string $type,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?string $locationOrUrl = null,
        public ?string $interviewerInfo = null,
        public ?string $prepMemo = null,
    ) {}
}
