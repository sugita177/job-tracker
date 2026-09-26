<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\UseCases\JobApplication\AdvanceApplicationStatusUseCase;
use App\Application\UseCases\JobApplication\CorrectApplicationStatusUseCase;
use App\Application\UseCases\JobApplication\CreateJobApplicationUseCase;
use App\Application\UseCases\JobApplication\DeleteJobApplicationUseCase;
use App\Application\UseCases\JobApplication\Dto\AdvanceStatusInput;
use App\Application\UseCases\JobApplication\Dto\CorrectStatusInput;
use App\Application\UseCases\JobApplication\Dto\CreateJobApplicationInput;
use App\Application\UseCases\JobApplication\Dto\UpdateJobApplicationInput;
use App\Application\UseCases\JobApplication\GetJobApplicationUseCase;
use App\Application\UseCases\JobApplication\ListJobApplicationsUseCase;
use App\Application\UseCases\JobApplication\UpdateJobApplicationUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobApplication\AdvanceStatusRequest;
use App\Http\Requests\JobApplication\CorrectStatusRequest;
use App\Http\Requests\JobApplication\CreateJobApplicationRequest;
use App\Http\Requests\JobApplication\UpdateJobApplicationRequest;
use App\Http\Resources\JobApplication\JobApplicationResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class JobApplicationController extends Controller
{
    /**
     * 応募一覧取得
     */
    public function index(Request $request, ListJobApplicationsUseCase $useCase): AnonymousResourceCollection
    {
        $userId = $this->currentUserId($request);
        $status = $request->filled('status') ? (string) $request->query('status') : null;

        $applications = $useCase->execute($userId, $status);

        return JobApplicationResource::collection($applications);
    }

    /**
     * 応募新規作成
     */
    public function store(CreateJobApplicationRequest $request, CreateJobApplicationUseCase $useCase): JsonResponse
    {
        $userId = $this->currentUserId($request);

        $input = new CreateJobApplicationInput(
            userId: $userId,
            companyId: (int) $request->input('company_id'),
            title: (string) $request->input('title'),
            priority: (string) $request->input('priority', 'medium'),
            status: (string) $request->input('status'),
            channelType: $request->filled('channel_type') ? (string) $request->input('channel_type') : null,
            channelDetailName: $request->filled('channel_detail_name') ? (string) $request->input('channel_detail_name') : null,
            appliedAt: $request->filled('applied_at') ? new DateTimeImmutable((string) $request->input('applied_at')) : null,
            jobUrl: $request->filled('job_url') ? (string) $request->input('job_url') : null,
            notes: $request->filled('notes') ? (string) $request->input('notes') : null,
        );

        $application = $useCase->execute($input);

        return (new JobApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 応募詳細取得
     */
    public function show(Request $request, int $id, GetJobApplicationUseCase $useCase): JobApplicationResource
    {
        $userId = $this->currentUserId($request);
        $application = $useCase->execute($id, $userId);

        if ($application === null) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return new JobApplicationResource($application);
    }

    /**
     * 応募基本情報更新
     */
    public function update(UpdateJobApplicationRequest $request, int $id, UpdateJobApplicationUseCase $useCase): JobApplicationResource
    {
        $userId = $this->currentUserId($request);

        $input = new UpdateJobApplicationInput(
            id: $id,
            userId: $userId,
            title: (string) $request->input('title'),
            priority: (string) $request->input('priority'),
            jobUrl: $request->filled('job_url') ? (string) $request->input('job_url') : null,
            notes: $request->filled('notes') ? (string) $request->input('notes') : null,
            channelType: $request->filled('channel_type') ? (string) $request->input('channel_type') : null,
            channelDetailName: $request->filled('channel_detail_name') ? (string) $request->input('channel_detail_name') : null,
            appliedAt: $request->filled('applied_at') ? new DateTimeImmutable((string) $request->input('applied_at')) : null,
        );

        $application = $useCase->execute($input);

        if ($application === null) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return new JobApplicationResource($application);
    }

    /**
     * 応募削除
     */
    public function destroy(Request $request, int $id, DeleteJobApplicationUseCase $useCase): Response
    {
        $userId = $this->currentUserId($request);
        $deleted = $useCase->execute($id, $userId);

        if (! $deleted) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return response()->noContent();
    }

    /**
     * ステータス順当進行
     */
    public function advanceStatus(AdvanceStatusRequest $request, int $id, AdvanceApplicationStatusUseCase $useCase): JobApplicationResource
    {
        $userId = $this->currentUserId($request);

        $input = new AdvanceStatusInput(
            id: $id,
            userId: $userId,
            toStatus: (string) $request->input('to_status'),
            channelType: $request->filled('channel_type') ? (string) $request->input('channel_type') : null,
            channelDetailName: $request->filled('channel_detail_name') ? (string) $request->input('channel_detail_name') : null,
            appliedAt: $request->filled('applied_at') ? new DateTimeImmutable((string) $request->input('applied_at')) : null,
        );

        $application = $useCase->execute($input);

        if ($application === null) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return new JobApplicationResource($application);
    }

    /**
     * ステータス誤入力訂正
     */
    public function correctStatus(CorrectStatusRequest $request, int $id, CorrectApplicationStatusUseCase $useCase): JobApplicationResource
    {
        $userId = $this->currentUserId($request);

        $input = new CorrectStatusInput(
            id: $id,
            userId: $userId,
            toStatus: (string) $request->input('to_status'),
            reason: (string) $request->input('reason'),
        );

        $application = $useCase->execute($input);

        if ($application === null) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return new JobApplicationResource($application);
    }

    /**
     * 認証済みユーザーのIDを取得する（未認証は 401 で即時遮断）
     */
    private function currentUserId(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        return (int) $user->id;
    }
}
