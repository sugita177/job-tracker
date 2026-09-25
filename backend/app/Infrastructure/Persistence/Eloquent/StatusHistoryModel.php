<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
