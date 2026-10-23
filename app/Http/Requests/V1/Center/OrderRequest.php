<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class OrderRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'vehicle_id' => [$isCreate ? 'required' : 'prohibited', 'integer', tenantExists('vehicles', withoutTrashed: true)],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'technician_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'next_service_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$isCreate ? 'sometimes' : 'prohibited', 'array', 'max:100'],
            ...OrderItemRequest::lineRules('items.*.'),
        ];
    }
}
