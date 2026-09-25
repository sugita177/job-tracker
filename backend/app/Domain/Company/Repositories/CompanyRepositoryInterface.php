<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Domain\Company\Entities\Company;

interface CompanyRepositoryInterface
{
    /**
     * IDとユーザーIDで企業を取得する（他ユーザーの企業は取得不可）
     */
    public function findById(int $id, int $userId): ?Company;

    /**
     * ユーザーに紐づく企業一覧を取得する
     *
     * @return list<Company>
     */
    public function listByUserId(int $userId): array;

    /**
     * 企業を保存（新規作成または更新）し、永続化されたエンティティを返す
     */
    public function save(Company $company): Company;

    /**
     * 企業を削除する
     */
    public function delete(int $id, int $userId): void;
}
