<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CompanyModel extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'memo',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<JobApplicationModel, $this>
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplicationModel::class, 'company_id');
    }
}
