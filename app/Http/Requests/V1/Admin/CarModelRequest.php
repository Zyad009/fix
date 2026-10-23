<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class CarModelRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $makeId = $this->route('car_make')?->id ?? $this->route('car_model')?->car_make_id;

        return [
            'name_ar' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('car_models', 'name_ar')->where('car_make_id', $makeId)->ignore($this->route('car_model')),
            ],
            'name_en' => ['nullable', 'string', 'max:100'],
        ];
    }
}
