<?php

namespace App\Http\Resources\V1;

use App\Models\CarModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CarModel */
class CarModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'car_make_id' => $this->car_make_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => $this->is_active,
        ];
    }
}
