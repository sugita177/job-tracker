<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Entities\StatusHistory;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\ValueObjects\HistoryType;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;
use App\Infrastructure\Persistence\Eloquent\JobApplicationModel;
use App\Infrastructure\Persistence\Eloquent\SelectionStepModel;
use App\Infrastructure\Persistence\Eloquent\StatusHistoryModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class JobApplicationRepository implements JobApplicationRepositoryInterface
{
    /**
     * IDとユーザーIDで求人応募を取得する（子エンティティ steps, statusHistories も完全復元）
     */
    public function findById(int $id, int $userId): ?JobApplication
    {
        $model = JobApplicationModel::query()
            ->with(['steps', 'statusHistories'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * ユーザーに紐づく求人応募一覧を取得する（ステータスによる絞り込み可能）
     *
     * @return list<JobApplication>
     */
    public function listByUserId(int $userId, ?ApplicationStatus $status = null): array
    {
        $query = JobApplicationModel::query()
            ->with(['steps', 'statusHistories'])
            ->where('user_id', $userId)
            ->orderBy('id', 'desc');

        if ($status !== null) {
            $query->where('current_status', $status->value);
        }

        $models = $query->get();

        /** @var list<JobApplication> */
        return array_values($models->map(fn (JobApplicationModel $model): JobApplication => $this->toDomain($model))->all());
    }

    /**
     * 求人応募（集約全体）を保存する（配下の SelectionStep や StatusHistory も一括永続化）
     */
    public function save(JobApplication $jobApplication): JobApplication
    {
        assert($jobApplication->userId > 0);
        assert($jobApplication->companyId > 0);

        return DB::transaction(function () use ($jobApplication): JobApplication {
            $model = $jobApplication->id !== null
                ? JobApplicationModel::query()->where('id', $jobApplication->id)->where('user_id', $jobApplication->userId)->firstOrFail()
                : new JobApplicationModel();

            $model->user_id = $jobApplication->userId;
            $model->company_id = $jobApplication->companyId;
            $model->title = $jobApplication->title;
            $model->priority = $jobApplication->priority->value;
            $model->channel_type = $jobApplication->channel?->type->value;
            $model->channel_detail_name = $jobApplication->channel?->detailName;
            $model->current_status = $jobApplication->currentStatus->value;
            $model->applied_at = $jobApplication->appliedAt;
            $model->job_url = $jobApplication->jobUrl;
            $model->notes = $jobApplication->notes;
            $model->save();

            $jobApplicationId = (int) $model->id;
            assert($jobApplicationId > 0);

            // 集約内に現在存在するステップの ID 一覧を収集
            $currentStepIds = array_values(array_filter(
                array_map(fn (SelectionStep $step) => $step->id, $jobApplication->steps),
                fn (?int $id) => $id !== null
            ));

            // 集約から削除されたステップを DB からも同期削除
            SelectionStepModel::query()
                ->where('job_application_id', $jobApplicationId)
                ->whereNotIn('id', $currentStepIds)
                ->delete();

            // SelectionStep の保存（既存のIDを持つものは更新、新規は作成）
            foreach ($jobApplication->steps as $step) {
                $stepModel = $step->id !== null
                    ? SelectionStepModel::query()->where('id', $step->id)->where('job_application_id', $jobApplicationId)->firstOrFail()
                    : new SelectionStepModel();

                $stepModel->job_application_id = $jobApplicationId;
                $stepModel->type = $step->type->value;
                $stepModel->scheduled_at = $step->scheduledAt;
                $stepModel->location_or_url = $step->locationOrUrl;
                $stepModel->interviewer_info = $step->interviewerInfo;
                $stepModel->prep_memo = $step->prepMemo;
                $stepModel->review_memo = $step->reviewMemo;
                $stepModel->result = $step->result->value;
                $stepModel->save();
            }

            // StatusHistory の保存（履歴は改ざん不可の追記のみ）
            foreach ($jobApplication->statusHistories as $history) {
                if ($history->id === null) {
                    $historyModel = new StatusHistoryModel();
                    $historyModel->job_application_id = $jobApplicationId;
                    $historyModel->from_status = $history->fromStatus->value;
                    $historyModel->to_status = $history->toStatus->value;
                    $historyModel->type = $history->type->value;
                    $historyModel->reason = $history->reason;
                    $historyModel->changed_at = $history->changedAt ?? new DateTimeImmutable();
                    $historyModel->save();
                }
            }

            // 完全な状態を再取得してドメインエンティティとして返す
            $refreshedModel = JobApplicationModel::query()
                ->with(['steps', 'statusHistories'])
                ->findOrFail($jobApplicationId);

            return $this->toDomain($refreshedModel);
        });
    }

    /**
     * 求人応募を削除する（配下の steps, statusHistories も CASCADE 削除）
     */
    public function delete(int $id, int $userId): void
    {
        JobApplicationModel::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Eloquent モデルからドメイン集約への完全マッピング（再構築）
     */
    private function toDomain(JobApplicationModel $model): JobApplication
    {
        // 応募媒体の復元
        $channel = null;
        if ($model->channel_type !== null) {
            $channel = new ApplicationChannel(
                type: ChannelType::from((string) $model->channel_type),
                detailName: $model->channel_detail_name,
            );
        }

        // SelectionStep コレクションの復元
        $steps = [];
        /** @var SelectionStepModel $stepModel */
        foreach ($model->steps as $stepModel) {
            $steps[] = new SelectionStep(
                type: StepType::from((string) $stepModel->type),
                scheduledAt: $stepModel->scheduled_at !== null ? DateTimeImmutable::createFromInterface($stepModel->scheduled_at) : null,
                locationOrUrl: $stepModel->location_or_url,
                interviewerInfo: $stepModel->interviewer_info,
                prepMemo: $stepModel->prep_memo,
                reviewMemo: $stepModel->review_memo,
                result: StepResult::from((string) $stepModel->result),
                id: (int) $stepModel->id,
            );
        }

        // StatusHistory コレクションの復元
        $statusHistories = [];
        /** @var StatusHistoryModel $historyModel */
        foreach ($model->statusHistories as $historyModel) {
            $statusHistories[] = new StatusHistory(
                fromStatus: ApplicationStatus::from((string) $historyModel->from_status),
                toStatus: ApplicationStatus::from((string) $historyModel->to_status),
                type: HistoryType::from((string) $historyModel->type),
                reason: $historyModel->reason,
                changedAt: DateTimeImmutable::createFromInterface($historyModel->changed_at),
                id: (int) $historyModel->id,
            );
        }

        return new JobApplication(
            userId: (int) $model->user_id,
            companyId: (int) $model->company_id,
            title: (string) $model->title,
            priority: Priority::from((string) $model->priority),
            channel: $channel,
            currentStatus: ApplicationStatus::from((string) $model->current_status),
            appliedAt: $model->applied_at !== null ? DateTimeImmutable::createFromInterface($model->applied_at) : null,
            jobUrl: $model->job_url,
            notes: $model->notes,
            id: (int) $model->id,
            steps: $steps,
            statusHistories: $statusHistories,
        );
    }
}
