<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company;

use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;

final readonly class GetCompanyUseCase
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(int $id, int $userId): ?Company
    {
        return $this->companyRepository->findById($id, $userId);
    }
}
