<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SelectionStepModel extends Model
{
    protected $table = 'selection_steps';

    protected $fillable = [
        'job_application_id',
        'type',
        'scheduled_at',
        'location_or_url',
        'interviewer_info',
        'prep_memo',
        'review_memo',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
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
