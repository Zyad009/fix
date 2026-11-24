<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\InspectionStatus;
use App\Http\Requests\AbstractFormRequest;
use App\Models\Inspection;
use Illuminate\Validation\Rule;

class InspectionRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*.item' => ['required', 'string', 'distinct', Rule::in(array_keys(Inspection::checklistSections()))],
            'items.*.status' => ['required', Rule::enum(InspectionStatus::class)],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'diagnostic_result' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
