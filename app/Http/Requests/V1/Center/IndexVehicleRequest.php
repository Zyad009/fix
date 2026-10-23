<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class IndexVehicleRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'car_make_id' => ['nullable', 'integer'],
        ];
    }
}
