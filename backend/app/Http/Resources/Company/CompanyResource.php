<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use App\Domain\Company\Entities\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Company $resource
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array{id: int|null, name: string, url: string|null, notes: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'url' => $this->resource->url,
            'notes' => $this->resource->memo,
        ];
    }
}
