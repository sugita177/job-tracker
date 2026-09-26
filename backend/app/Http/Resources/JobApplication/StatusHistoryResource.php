<?php

declare(strict_types=1);

namespace App\Http\Resources\JobApplication;

use App\Domain\JobApplication\Entities\StatusHistory;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StatusHistory $resource
 */
final class StatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'from_status' => $this->resource->fromStatus->value,
            'to_status' => $this->resource->toStatus->value,
            'type' => $this->resource->type->value,
            'reason' => $this->resource->reason,
            'changed_at' => $this->resource->changedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
