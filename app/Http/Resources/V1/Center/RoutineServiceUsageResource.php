<?php

namespace App\Http\Resources\V1\Center;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One invoice line that used a routine service.
 *
 * @mixin OrderItem
 */
class RoutineServiceUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            'id' => $this->id,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status->response(),
            'branch' => $order->branch?->name,
            'customer' => $order->customer?->name,
            'plate_number' => $order->vehicle?->plate_number,
            'qty' => formatNumber($this->qty),
            'unit_price' => formatNumber($this->unit_price),
            'used_at' => $this->created_at?->timestamp,
        ];
    }
}
