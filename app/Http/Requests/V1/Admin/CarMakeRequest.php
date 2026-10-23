<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class CarMakeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('car_makes', 'name_ar')->ignore($this->route('car_make'))],
            'name_en' => ['nullable', 'string', 'max:100'],
        ];
    }
}
