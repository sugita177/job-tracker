<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company;

use App\Application\UseCases\Company\Dto\UpdateCompanyInput;
use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;

final readonly class UpdateCompanyUseCase
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(UpdateCompanyInput $input): ?Company
    {
        $company = $this->companyRepository->findById($input->id, $input->userId);
        if ($company === null) {
            return null;
        }

        $company->update(
            name: $input->name,
            url: $input->url,
            memo: $input->notes,
        );

        return $this->companyRepository->save($company);
    }
}
