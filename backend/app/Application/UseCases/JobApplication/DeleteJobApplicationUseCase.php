<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;

final readonly class DeleteJobApplicationUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(int $id, int $userId): bool
    {
        $application = $this->jobApplicationRepository->findById($id, $userId);
        if ($application === null) {
            return false;
        }

        $this->jobApplicationRepository->delete($id, $userId);

        return true;
    }
}
