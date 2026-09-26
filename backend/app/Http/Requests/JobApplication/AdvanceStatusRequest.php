<?php

declare(strict_types=1);

namespace App\Http\Requests\JobApplication;

use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdvanceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'to_status' => [
                'required',
                'string',
                Rule::in(array_map(fn (ApplicationStatus $s) => $s->value, ApplicationStatus::cases())),
            ],
            'channel_type' => [
                'nullable',
                'string',
                Rule::in(array_map(fn (ChannelType $c) => $c->value, ChannelType::cases())),
            ],
            'channel_detail_name' => ['nullable', 'string', 'max:255'],
            'applied_at' => ['nullable', 'date'],
        ];
    }
}
