<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\AddSelectionStepInput;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\StepType;

final readonly class AddSelectionStepUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(AddSelectionStepInput $input): ?SelectionStep
    {
        $application = $this->jobApplicationRepository->findById($input->jobApplicationId, $input->userId);
        if ($application === null) {
            return null;
        }

        $step = new SelectionStep(
            type: StepType::from($input->type),
            scheduledAt: $input->scheduledAt,
            locationOrUrl: $input->locationOrUrl,
            interviewerInfo: $input->interviewerInfo,
            prepMemo: $input->prepMemo,
        );

        // 集約ルートに追加（不変条件の保護: 検討中ステータスなら例外がスローされる）
        $application->addSelectionStep($step);

        $savedApplication = $this->jobApplicationRepository->save($application);

        // 保存後に DB で採番された最新のステップ（末尾）を返す
        $savedSteps = $savedApplication->steps;

        return end($savedSteps) ?: null;
    }
}
