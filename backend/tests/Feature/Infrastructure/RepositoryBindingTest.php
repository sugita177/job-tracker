<?php

declare(strict_types=1);

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\CompanyRepository;
use App\Infrastructure\Persistence\Repositories\JobApplicationRepository;

test('リポジトリインターフェースが具象クラスに正しくDIバインドされていること', function () {
    expect(app(CompanyRepositoryInterface::class))->toBeInstanceOf(CompanyRepository::class)
        ->and(app(JobApplicationRepositoryInterface::class))->toBeInstanceOf(JobApplicationRepository::class);
});
