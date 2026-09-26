<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\AdvanceStatusInput;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\Exceptions\IncompleteApplicationException;
use DateTimeImmutable;

final readonly class AdvanceApplicationStatusUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(AdvanceStatusInput $input): ?JobApplication
    {
        $application = $this->jobApplicationRepository->findById($input->id, $input->userId);
        if ($application === null) {
            return null;
        }

        $nextStatus = ApplicationStatus::from($input->toStatus);

        // 検討中 → 書類選考中（正式応募）への遷移の場合、媒体と応募日を適用
        if ($nextStatus === ApplicationStatus::DOCUMENT_SCREENING && $application->appliedAt === null) {
            $channel = $input->channelType !== null
                ? new ApplicationChannel(ChannelType::from($input->channelType), $input->channelDetailName)
                : $application->channel;

            if ($channel === null) {
                throw IncompleteApplicationException::missingChannelOrAppliedAt();
            }

            $application->apply($channel, $input->appliedAt ?? new DateTimeImmutable());
        } else {
            $application->advanceStatus($nextStatus, $input->appliedAt);
        }

        return $this->jobApplicationRepository->save($application);
    }
}
