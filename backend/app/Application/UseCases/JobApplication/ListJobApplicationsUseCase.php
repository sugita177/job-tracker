<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;

final readonly class ListJobApplicationsUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    /**
     * @return list<JobApplication>
     */
    public function execute(int $userId, ?string $status = null): array
    {
        $appStatus = $status !== null ? ApplicationStatus::from($status) : null;

        return $this->jobApplicationRepository->listByUserId($userId, $appStatus);
    }
}
