<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\UpdateJobApplicationInput;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\ValueObjects\Priority;

final readonly class UpdateJobApplicationUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(UpdateJobApplicationInput $input): ?JobApplication
    {
        $application = $this->jobApplicationRepository->findById($input->id, $input->userId);
        if ($application === null) {
            return null;
        }

        $channel = $input->channelType !== null
            ? new ApplicationChannel(ChannelType::from($input->channelType), $input->channelDetailName)
            : null;

        $application->update(
            title: $input->title,
            priority: Priority::from($input->priority),
            jobUrl: $input->jobUrl,
            notes: $input->notes,
            channel: $channel,
            appliedAt: $input->appliedAt,
        );

        return $this->jobApplicationRepository->save($application);
    }
}
