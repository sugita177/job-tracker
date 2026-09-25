<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\CompanyModel;

final class CompanyRepository implements CompanyRepositoryInterface
{
    /**
     * IDとユーザーIDで企業を取得する（他ユーザーの企業は取得不可）
     */
    public function findById(int $id, int $userId): ?Company
    {
        $model = CompanyModel::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * ユーザーに紐づく企業一覧を取得する
     *
     * @return list<Company>
     */
    public function listByUserId(int $userId): array
    {
        $models = CompanyModel::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'asc')
            ->get();

        /** @var list<Company> */
        return array_values($models->map(fn (CompanyModel $model): Company => $this->toDomain($model))->all());
    }

    /**
     * 企業を保存（新規作成または更新）し、永続化されたエンティティを返す
     */
    public function save(Company $company): Company
    {
        assert($company->userId > 0);
        
        $model = $company->id !== null
            ? CompanyModel::query()->where('id', $company->id)->where('user_id', $company->userId)->firstOrFail()
            : new CompanyModel();

        $model->user_id = $company->userId;
        $model->name = $company->name;
        $model->url = $company->url;
        $model->memo = $company->memo;
        $model->save();

        return $this->toDomain($model);
    }

    /**
     * 企業を削除する（他ユーザーの企業は削除不可）
     */
    public function delete(int $id, int $userId): void
    {
        CompanyModel::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Eloquent モデルからドメインエンティティへのマッピング
     */
    private function toDomain(CompanyModel $model): Company
    {
        return new Company(
            userId: (int) $model->user_id,
            name: (string) $model->name,
            url: $model->url,
            memo: $model->memo,
            id: (int) $model->id,
        );
    }
}
