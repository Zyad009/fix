<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class StockRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qty' => ['required', 'decimal:0,2', 'min:-99999999', 'max:99999999'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
