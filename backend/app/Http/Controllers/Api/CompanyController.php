<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Company\CreateCompanyUseCase;
use App\Application\UseCases\Company\DeleteCompanyUseCase;
use App\Application\UseCases\Company\Dto\CreateCompanyInput;
use App\Application\UseCases\Company\Dto\UpdateCompanyInput;
use App\Application\UseCases\Company\GetCompanyUseCase;
use App\Application\UseCases\Company\ListCompaniesUseCase;
use App\Application\UseCases\Company\UpdateCompanyUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CompanyController extends Controller
{
    /**
     * 登録企業一覧の取得
     */
    public function index(Request $request, ListCompaniesUseCase $useCase): AnonymousResourceCollection
    {
        $userId = $this->currentUserId($request);;
        $companies = $useCase->execute($userId);

        return CompanyResource::collection($companies);
    }

    /**
     * 企業の新規登録
     */
    public function store(CreateCompanyRequest $request, CreateCompanyUseCase $useCase): JsonResponse
    {
        $userId = $this->currentUserId($request);

        $input = new CreateCompanyInput(
            userId: $userId,
            name: (string) $request->input('name'),
            url: $request->filled('url') ? (string) $request->input('url') : null,
            notes: $request->filled('notes') ? (string) $request->input('notes') : null,
        );

        $company = $useCase->execute($input);

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 企業詳細の取得
     */
    public function show(Request $request, int $id, GetCompanyUseCase $useCase): CompanyResource
    {
        $userId = $this->currentUserId($request);;
        $company = $useCase->execute($id, $userId);

        if ($company === null) {
            abort(404, '指定された企業が見つかりません。');
        }

        return new CompanyResource($company);
    }

    /**
     * 企業情報の更新
     */
    public function update(UpdateCompanyRequest $request, int $id, UpdateCompanyUseCase $useCase): CompanyResource
    {
        $userId = $this->currentUserId($request);;

        $input = new UpdateCompanyInput(
            id: $id,
            userId: $userId,
            name: (string) $request->input('name'),
            url: $request->filled('url') ? (string) $request->input('url') : null,
            notes: $request->filled('notes') ? (string) $request->input('notes') : null,
        );

        $company = $useCase->execute($input);

        if ($company === null) {
            abort(404, '指定された企業が見つかりません。');
        }

        return new CompanyResource($company);
    }

    /**
     * 企業の削除
     */
    public function destroy(Request $request, int $id, DeleteCompanyUseCase $useCase): Response
    {
        $userId = $this->currentUserId($request);;
        $deleted = $useCase->execute($id, $userId);

        if (! $deleted) {
            abort(404, '指定された企業が見つかりません。');
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
