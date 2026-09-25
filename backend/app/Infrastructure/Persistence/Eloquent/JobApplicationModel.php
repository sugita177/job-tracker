<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JobApplicationModel extends Model
{
    protected $table = 'job_applications';

    protected $fillable = [
        'user_id',
        'company_id',
        'title',
        'priority',
        'channel_type',
        'channel_detail_name',
        'current_status',
        'applied_at',
        'job_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<CompanyModel, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    /**
     * @return HasMany<SelectionStepModel, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(SelectionStepModel::class, 'job_application_id')->orderBy('scheduled_at', 'asc');
    }

    /**
     * @return HasMany<StatusHistoryModel, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistoryModel::class, 'job_application_id')->orderBy('changed_at', 'asc');
    }
}
