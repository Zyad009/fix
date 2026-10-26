<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'kind' => $this->kind->response(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'sale_price' => formatNumber($this->sale_price),
            'cost_price' => $this->when(actor()?->hasPermission('costs.view'), fn () => $this->cost_price === null ? null : formatNumber($this->cost_price)),
            'image_url' => $this->imageUrl(),
            'track_stock' => $this->track_stock,
            // Selected branch's stock, or the total of all branches when none is selected.
            'stock' => $this->when($this->track_stock && $this->relationLoaded('stocks'), fn () => Money::sum($this->stocks->pluck('qty'))->toFloat()),
            'is_active' => $this->is_active,
        ];
    }
}
