<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
            'plate_normalized' => $this->plate_normalized,
            'vin' => $this->vin,
            'make' => $this->whenLoaded('carMake', fn () => $this->carMake ? ['id' => $this->carMake->id, 'name_ar' => $this->carMake->name_ar, 'name_en' => $this->carMake->name_en, 'logo_url' => $this->carMake->logo_url] : null),
            'model' => $this->whenLoaded('carModel', fn () => $this->carModel ? ['id' => $this->carModel->id, 'name_ar' => $this->carModel->name_ar, 'name_en' => $this->carModel->name_en] : null),
            'year' => $this->year,
            'color' => $this->color,
            'last_odometer' => $this->last_odometer,
            'last_visit_at' => $this->last_visit_at?->timestamp,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
            'next_service_odometer' => $this->when($this->hasAttribute('next_service_odometer'), fn () => $this->getAttribute('next_service_odometer')),
            'next_service_date' => $this->when($this->hasAttribute('next_service_date'), fn () => $this->getAttribute('next_service_date')),
            'balance' => $this->when($this->hasAttribute('balance'), fn () => formatNumber($this->getAttribute('balance'))),
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
