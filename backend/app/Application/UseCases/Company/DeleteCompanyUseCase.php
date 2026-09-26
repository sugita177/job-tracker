<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;

final readonly class DeleteCompanyUseCase
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(int $id, int $userId): bool
    {
        $company = $this->companyRepository->findById($id, $userId);
        if ($company === null) {
            return false;
        }

        $this->companyRepository->delete($id, $userId);

        return true;
    }
}
