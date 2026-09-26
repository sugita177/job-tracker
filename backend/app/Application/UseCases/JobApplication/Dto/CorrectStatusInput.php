<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication\Dto;

final readonly class CorrectStatusInput
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $toStatus,
        public string $reason,
    ) {}
}
