<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\UpdateSelectionStepInput;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;

final readonly class UpdateSelectionStepUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
    ) {}

    public function execute(UpdateSelectionStepInput $input): ?SelectionStep
    {
        $application = $this->jobApplicationRepository->findById($input->jobApplicationId, $input->userId);
        if ($application === null) {
            return null;
        }

        $step = $application->findSelectionStep($input->stepId);
        if ($step === null) {
            return null;
        }

        // 1. 日程の更新（指定された場合）
        if ($input->scheduledAt !== null) {
            $application->rescheduleSelectionStep($input->stepId, $input->scheduledAt, $input->locationOrUrl);
        }

        // 2. 事前準備情報の更新
        $stepType = $input->type !== null ? StepType::from($input->type) : null;
        $application->updateStepPreparation(
            stepId: $input->stepId,
            locationOrUrl: $input->locationOrUrl,
            interviewerInfo: $input->interviewerInfo,
            prepMemo: $input->prepMemo,
            type: $stepType,
        );

        // 3. 結果と振り返りメモの更新（結果が指定された場合）
        if ($input->result !== null) {
            $application->recordStepReview(
                stepId: $input->stepId,
                reviewMemo: $input->reviewMemo ?? '',
                result: StepResult::from($input->result),
            );
        }

        $savedApplication = $this->jobApplicationRepository->save($application);

        return $savedApplication->findSelectionStep($input->stepId);
    }
}
