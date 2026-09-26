<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;

final readonly class DeleteSelectionStepUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(int $jobApplicationId, int $stepId, int $userId): bool
    {
        $application = $this->jobApplicationRepository->findById($jobApplicationId, $userId);
        if ($application === null) {
            return false;
        }

        $step = $application->findSelectionStep($stepId);
        if ($step === null) {
            return false;
        }

        $application->removeSelectionStep($stepId);

        $this->jobApplicationRepository->save($application);

        return true;
    }
}
