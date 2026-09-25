<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company;

use App\Application\UseCases\Company\Dto\CreateCompanyInput;
use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;

final readonly class CreateCompanyUseCase
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(CreateCompanyInput $input): Company
    {
        $company = new Company(
            userId: $input->userId,
            name: $input->name,
            url: $input->url,
            memo: $input->notes,
        );

        return $this->companyRepository->save($company);
    }
}
