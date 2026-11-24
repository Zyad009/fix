<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class RoutineServiceRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', maintenanceCenterUnique('routine_services', 'name')->withoutTrashed()->ignore($this->route('routine_service'))],
        ];
    }
}
