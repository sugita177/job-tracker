<?php

declare(strict_types=1);

namespace App\Http\Resources\JobApplication;

use App\Domain\JobApplication\Entities\SelectionStep;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SelectionStep $resource
 */
final class SelectionStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'scheduled_at' => $this->resource->scheduledAt?->format(DateTimeImmutable::ATOM),
            'location_or_url' => $this->resource->locationOrUrl,
            'interviewer_info' => $this->resource->interviewerInfo,
            'prep_memo' => $this->resource->prepMemo,
            'review_memo' => $this->resource->reviewMemo,
            'result' => $this->resource->result->value,
        ];
    }
}
