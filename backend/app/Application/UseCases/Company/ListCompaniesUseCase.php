<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company;

use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;

final readonly class ListCompaniesUseCase
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    /**
     * @return list<Company>
     */
    public function execute(int $userId): array
    {
        return $this->companyRepository->listByUserId($userId);
    }
}
