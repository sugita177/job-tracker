<?php

declare(strict_types=1);

namespace App\Http\Requests\JobApplication;

use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CorrectStatusRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}
