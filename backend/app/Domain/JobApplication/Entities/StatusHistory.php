<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Entities;

use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\HistoryType;
use DateTimeImmutable;
use InvalidArgumentException;

final class StatusHistory
{
    public readonly ?string $reason;

    public function __construct(
        public readonly ApplicationStatus $fromStatus,
        public readonly ApplicationStatus $toStatus,
        public readonly HistoryType $type,
        ?string $reason = null,
        public readonly ?DateTimeImmutable $changedAt = null,
        public readonly ?int $id = null,
    ) {
        $trimmedReason = trim($reason ?? '');
        $normalizedReason = $trimmedReason !== '' ? $trimmedReason : null;
        if ($type === HistoryType::CORRECTION && $normalizedReason === null) {
            throw new InvalidArgumentException('ステータス訂正時は理由の入力が必須です。');
        }
        $this->reason = $normalizedReason;
    }
}