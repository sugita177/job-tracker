<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

use DateTimeImmutable;

final readonly class AdvanceStatusInput
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $toStatus,
        public ?string $channelType = null,
        public ?string $channelDetailName = null,
        public ?DateTimeImmutable $appliedAt = null,
    ) {}
}
