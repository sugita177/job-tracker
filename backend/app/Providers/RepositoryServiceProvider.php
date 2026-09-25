<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\CompanyRepository;
use App\Infrastructure\Persistence\Repositories\JobApplicationRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * リポジトリのインターフェースと具象クラスのバインド定義
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CompanyRepositoryInterface::class => CompanyRepository::class,
        JobApplicationRepositoryInterface::class => JobApplicationRepository::class,
    ];

    public function register(): void
    {
        // $bindings プロパティにより自動登録されるため空でOK
    }

    public function boot(): void
    {
        //
    }
}
