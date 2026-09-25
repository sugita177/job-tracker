<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_application_id
 * @property string $from_status
 * @property string $to_status
 * @property string $type
 * @property string|null $reason
 * @property \DateTimeImmutable|\Carbon\CarbonImmutable $changed_at
 */
final class StatusHistoryModel extends Model
{
    protected $table = 'status_histories';

    protected $fillable = [
        'job_application_id',
        'from_status',
        'to_status',
        'type',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<JobApplicationModel, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplicationModel::class, 'job_application_id');
    }
}
