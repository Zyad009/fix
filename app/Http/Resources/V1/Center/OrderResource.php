<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'status' => $this->status->response(),
            'is_opening_balance' => $this->is_opening_balance,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? new VehicleResource($this->vehicle) : null),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? ['id' => $this->technician->id, 'name' => $this->technician->name] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'odometer' => $this->odometer,
            'next_service_odometer' => $this->next_service_odometer,
            'next_service_date' => $this->next_service_date?->toDateString(),
            'subtotal' => formatNumber($this->subtotal),
            'discount' => formatNumber($this->discount),
            'total' => formatNumber($this->total),
            'paid' => formatNumber($this->paid),
            'remaining' => $this->remaining()->toFloat(),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->timestamp,
            'completed_at' => $this->completed_at?->timestamp,
            'cancelled_at' => $this->cancelled_at?->timestamp,
            'days_left' => $this->when($this->hasAttribute('days_left'), fn () => $this->getAttribute('days_left')),
        ];
    }
}
