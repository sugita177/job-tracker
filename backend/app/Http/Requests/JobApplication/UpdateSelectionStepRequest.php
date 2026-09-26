<?php

declare(strict_types=1);

namespace App\Http\Requests\JobApplication;

use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSelectionStepRequest extends FormRequest
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
            'type' => [
                'nullable',
                'string',
                Rule::in(array_map(fn (StepType $t) => $t->value, StepType::cases())),
            ],
            'scheduled_at' => ['nullable', 'date'],
            'location_or_url' => ['nullable', 'string', 'max:2048'],
            'interviewer_info' => ['nullable', 'string', 'max:255'],
            'prep_memo' => ['nullable', 'string', 'max:5000'],
            'review_memo' => ['nullable', 'string', 'max:5000'],
            'result' => [
                'nullable',
                'string',
                Rule::in(array_map(fn (StepResult $r) => $r->value, StepResult::cases())),
            ],
        ];
    }
}
