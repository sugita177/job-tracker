<?php

declare(strict_types=1);

namespace App\Http\Requests\JobApplication;

use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\ValueObjects\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateJobApplicationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'priority' => [
                'required',
                'string',
                Rule::in(array_map(fn (Priority $p) => $p->value, Priority::cases())),
            ],
            'job_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
