<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     created_at: string,
     *     updated_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at?->format(DateTimeImmutable::ATOM) ?? '',
            'updated_at' => $this->updated_at?->format(DateTimeImmutable::ATOM) ?? '',
        ];
    }
}
