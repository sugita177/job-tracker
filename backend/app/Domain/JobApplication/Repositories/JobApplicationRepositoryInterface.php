<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\Repositories;

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;

interface JobApplicationRepositoryInterface
{
    /**
     * IDとユーザーIDで求人応募を取得する（子エンティティ steps, statusHistories も完全復元）
     */
    public function findById(int $id, int $userId): ?JobApplication;

    /**
     * ユーザーに紐づく求人応募一覧を取得する（ステータスによる絞り込み可能）
     *
     * @return list<JobApplication>
     */
    public function listByUserId(int $userId, ?ApplicationStatus $status = null): array;

    /**
     * 求人応募（集約全体）を保存する（配下の SelectionStep や StatusHistory も一括永続化）
     */
    public function save(JobApplication $jobApplication): JobApplication;

    /**
     * 求人応募を削除する（配下の steps, statusHistories も CASCADE 削除）
     */
    public function delete(int $id, int $userId): void;
}
