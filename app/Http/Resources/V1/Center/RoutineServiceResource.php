<?php

namespace App\Http\Resources\V1\Center;

use App\Models\RoutineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin RoutineService */
class RoutineServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'usage_count' => (int) ($this->usage_count ?? 0),
            'suggested_price' => $this->suggested_price === null ? null : formatNumber($this->suggested_price),
            'last_used_at' => $this->last_used_at === null ? null : Carbon::parse($this->last_used_at)->timestamp,
            'prices' => $this->when($this->prices !== null, fn () => $this->prices),
        ];
    }
}
