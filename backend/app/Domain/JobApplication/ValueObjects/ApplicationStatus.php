<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

use App\Domain\JobApplication\Exceptions\InvalidStatusTransitionException;

enum ApplicationStatus: string
{
    case INTERESTED = 'INTERESTED';
    case CASUAL_INTERVIEW = 'CASUAL_INTERVIEW';
    case DOCUMENT_SCREENING = 'DOCUMENT_SCREENING';
    case INTERVIEW_ADJUSTING = 'INTERVIEW_ADJUSTING';
    case INTERVIEW_IN_PROGRESS = 'INTERVIEW_IN_PROGRESS';
    case OFFERED = 'OFFERED';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
    case SKIPPED = 'SKIPPED';

        public function getLabel(): string
    {
        return match ($this) {
            self::INTERESTED => '検討中',
            self::CASUAL_INTERVIEW => 'カジュアル面談中',
            self::DOCUMENT_SCREENING => '書類選考中',
            self::INTERVIEW_ADJUSTING => '面接日程調整中',
            self::INTERVIEW_IN_PROGRESS => '面接進行中',
            self::OFFERED => '内定',
            self::ACCEPTED => '内定承諾',
            self::REJECTED => 'お見送り',
            self::WITHDRAWN => '辞退',
            self::SKIPPED => '検討見送り',
        };
    }


    /**
     * 次のステータスへ通常遷移可能かを判定する
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::INTERESTED => in_array($next, [
                self::CASUAL_INTERVIEW,
                self::DOCUMENT_SCREENING,
                self::SKIPPED,
            ], true),

            self::CASUAL_INTERVIEW => in_array($next, [
                self::DOCUMENT_SCREENING,
                self::INTERVIEW_ADJUSTING,
                self::SKIPPED,
                self::WITHDRAWN,
            ], true),

            self::DOCUMENT_SCREENING => in_array($next, [
                self::INTERVIEW_ADJUSTING,
                self::REJECTED,
                self::WITHDRAWN,
            ], true),

            self::INTERVIEW_ADJUSTING => in_array($next, [
                self::INTERVIEW_IN_PROGRESS,
                self::REJECTED,
                self::WITHDRAWN,
            ], true),

            self::INTERVIEW_IN_PROGRESS => in_array($next, [
                self::INTERVIEW_ADJUSTING,
                self::OFFERED,
                self::REJECTED,
                self::WITHDRAWN,
            ], true),

            self::OFFERED => in_array($next, [
                self::ACCEPTED,
                self::WITHDRAWN,
            ], true),

            // 完了ステータス（内定承諾、お見送り、辞退、検討見送り）からはどこへも順遷移不可
            self::ACCEPTED, self::REJECTED, self::WITHDRAWN, self::SKIPPED => false,
        };
    }


    /**
     * 次のステータスへ遷移する（不正な遷移の場合はドメイン例外をスロー）
     *
     * @throws InvalidStatusTransitionException
     */
    public function transitionTo(self $next): self {
        if (! $this->canTransitionTo($next)) {
            throw new InvalidStatusTransitionException(
                sprintf('現在のステータス [%s] から [%s] への遷移は許可されていません。', $this->value, $next->value)
            );
        }

        return $next;
    }
}
    
