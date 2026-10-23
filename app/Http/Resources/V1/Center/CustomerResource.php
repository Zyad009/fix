<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'vehicles_count' => $this->whenCounted('vehicles'),
            'balance' => $this->when($this->hasAttribute('balance'), fn () => formatNumber((string) $this->getAttribute('balance'))),
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'debts' => $this->when($this->hasAttribute('debts'), fn () => $this->getAttribute('debts')),
            'is_deleted' => $this->deleted_at !== null,
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
