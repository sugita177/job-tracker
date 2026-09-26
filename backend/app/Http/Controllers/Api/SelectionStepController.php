<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\UseCases\JobApplication\AddSelectionStepUseCase;
use App\Application\UseCases\JobApplication\DeleteSelectionStepUseCase;
use App\Application\UseCases\JobApplication\Dto\AddSelectionStepInput;
use App\Application\UseCases\JobApplication\Dto\UpdateSelectionStepInput;
use App\Application\UseCases\JobApplication\UpdateSelectionStepUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobApplication\AddSelectionStepRequest;
use App\Http\Requests\JobApplication\UpdateSelectionStepRequest;
use App\Http\Resources\JobApplication\SelectionStepResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SelectionStepController extends Controller
{
    /**
     * 選考ステップ追加
     */
    public function store(
        AddSelectionStepRequest $request,
        int $jobApplicationId,
        AddSelectionStepUseCase $useCase,
    ): JsonResponse {
        $userId = $this->currentUserId($request);

        $input = new AddSelectionStepInput(
            jobApplicationId: $jobApplicationId,
            userId: $userId,
            type: (string) $request->input('type'),
            scheduledAt: $request->filled('scheduled_at') ? new DateTimeImmutable((string) $request->input('scheduled_at')) : null,
            locationOrUrl: $request->filled('location_or_url') ? (string) $request->input('location_or_url') : null,
            interviewerInfo: $request->filled('interviewer_info') ? (string) $request->input('interviewer_info') : null,
            prepMemo: $request->filled('prep_memo') ? (string) $request->input('prep_memo') : null,
        );

        $step = $useCase->execute($input);

        if ($step === null) {
            abort(404, '指定された応募情報が見つかりません。');
        }

        return (new SelectionStepResource($step))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 選考ステップ更新
     */
    public function update(
        UpdateSelectionStepRequest $request,
        int $jobApplicationId,
        int $stepId,
        UpdateSelectionStepUseCase $useCase,
    ): SelectionStepResource {
        $userId = $this->currentUserId($request);

        $input = new UpdateSelectionStepInput(
            jobApplicationId: $jobApplicationId,
            stepId: $stepId,
            userId: $userId,
            type: $request->filled('type') ? (string) $request->input('type') : null,
            scheduledAt: $request->filled('scheduled_at') ? new DateTimeImmutable((string) $request->input('scheduled_at')) : null,
            locationOrUrl: $request->filled('location_or_url') ? (string) $request->input('location_or_url') : null,
            interviewerInfo: $request->filled('interviewer_info') ? (string) $request->input('interviewer_info') : null,
            prepMemo: $request->filled('prep_memo') ? (string) $request->input('prep_memo') : null,
            reviewMemo: $request->filled('review_memo') ? (string) $request->input('review_memo') : null,
            result: $request->filled('result') ? (string) $request->input('result') : null,
        );

        $step = $useCase->execute($input);

        if ($step === null) {
            abort(404, '指定された選考ステップが見つかりません。');
        }

        return new SelectionStepResource($step);
    }

    /**
     * 選考ステップ削除
     */
    public function destroy(
        Request $request,
        int $jobApplicationId,
        int $stepId,
        DeleteSelectionStepUseCase $useCase,
    ): Response {
        $userId = $this->currentUserId($request);

        $deleted = $useCase->execute($jobApplicationId, $stepId, $userId);

        if (! $deleted) {
            abort(404, '指定された選考ステップが見つかりません。');
        }

        return response()->noContent();
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
