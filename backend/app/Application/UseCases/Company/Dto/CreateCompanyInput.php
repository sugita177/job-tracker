<?php

declare(strict_types=1);

namespace App\Application\UseCases\Company\Dto;

final readonly class CreateCompanyInput
{
    public function __construct(
        public int $userId,
        public string $name,
        public ?string $url = null,
        public ?string $notes = null,
    ) {}
}
