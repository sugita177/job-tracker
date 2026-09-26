<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

use DateTimeImmutable;

final readonly class UpdateJobApplicationInput
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $title,
        public string $priority,
        public ?string $jobUrl = null,
        public ?string $notes = null,
        public ?string $channelType = null,
        public ?string $channelDetailName = null,
        public ?DateTimeImmutable $appliedAt = null,
    ) {}
}
