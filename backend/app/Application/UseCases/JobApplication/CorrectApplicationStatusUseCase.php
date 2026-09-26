<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\CorrectStatusInput;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;

final readonly class CorrectApplicationStatusUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(CorrectStatusInput $input): ?JobApplication
    {
        $application = $this->jobApplicationRepository->findById($input->id, $input->userId);
        if ($application === null) {
            return null;
        }

        $correctedStatus = ApplicationStatus::from($input->toStatus);
        $application->correctStatus($correctedStatus, $input->reason);

        return $this->jobApplicationRepository->save($application);
    }
}
