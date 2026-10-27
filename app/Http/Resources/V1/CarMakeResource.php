<?php

namespace App\Http\Resources\V1;

use App\Models\CarMake;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CarMake */
class CarMakeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'logo_url' => $this->logo_url,
            'is_active' => $this->is_active,
            'models' => CarModelResource::collection($this->whenLoaded('models')),
        ];
    }
}
