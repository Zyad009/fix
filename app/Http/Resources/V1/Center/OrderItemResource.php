<?php

namespace App\Http\Resources\V1\Center;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'is_free_item' => $this->product_id === null,
            'name' => $this->name,
            'kind' => $this->kind->response(),
            'qty' => formatNumber($this->qty),
            'unit_price' => formatNumber($this->unit_price),
            'unit_cost' => $this->when(actor()?->hasPermission('costs.view'), fn () => $this->unit_cost === null ? null : formatNumber($this->unit_cost)),
            'line_total' => Money::of($this->unit_price)->multipliedBy($this->qty)->toFloat(),
        ];
    }
}
