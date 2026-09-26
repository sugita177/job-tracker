<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

use DateTimeImmutable;

final readonly class CreateJobApplicationInput
{
    public function __construct(
        public int $userId,
        public int $companyId,
        public string $title,
        public string $priority,
        public string $status,
        public ?string $channelType = null,
        public ?string $channelDetailName = null,
        public ?DateTimeImmutable $appliedAt = null,
        public ?string $jobUrl = null,
        public ?string $notes = null,
    ) {}
}
