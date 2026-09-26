<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;

final readonly class GetJobApplicationUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(int $id, int $userId): ?JobApplication
    {
        return $this->jobApplicationRepository->findById($id, $userId);
    }
}
