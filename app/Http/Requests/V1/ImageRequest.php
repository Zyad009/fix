<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\AbstractFormRequest;

class ImageRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
