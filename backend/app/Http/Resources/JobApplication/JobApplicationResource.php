<?php

declare(strict_types=1);

namespace App\Http\Resources\JobApplication;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\JobApplication\Entities\JobApplication;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read JobApplication $resource
 */
final class JobApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 別集約である企業情報を解決してレスポンスに含める
        $companyRepo = app(CompanyRepositoryInterface::class);
        $company = $companyRepo->findById($this->resource->companyId, $this->resource->userId);

        $channel = null;
        if ($this->resource->channel !== null) {
            $channel = [
                'type' => $this->resource->channel->type->value,
                'detail_name' => $this->resource->channel->detailName,
            ];
        }

        return [
            'id' => $this->resource->id,
            'company' => [
                'id' => $this->resource->companyId,
                'name' => $company->name ?? '',
            ],
            'title' => $this->resource->title,
            'priority' => $this->resource->priority->value,
            'current_status' => $this->resource->currentStatus->value,
            'channel' => $channel,
            'applied_at' => $this->resource->appliedAt?->format(DateTimeImmutable::ATOM),
            'job_url' => $this->resource->jobUrl,
            'notes' => $this->resource->notes,
            'selection_steps' => SelectionStepResource::collection($this->resource->steps),
            'status_histories' => StatusHistoryResource::collection($this->resource->statusHistories),
        ];
    }
}
