<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->response(),
            'amount' => formatNumber($this->amount),
            'method' => $this->method->response(),
            'paid_at' => $this->paid_at?->timestamp,
            'receipt' => $this->whenLoaded('receipt', fn () => $this->receipt ? ['id' => $this->receipt->id, 'receipt_no' => $this->receipt->receipt_no] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_no' => $this->order->order_no,
                'total' => formatNumber($this->order->total),
                'paid' => formatNumber($this->order->paid),
                'remaining' => $this->order->remaining()->toFloat(),
            ] : null),
            'is_voided' => $this->isVoided(),
            'void_reason' => $this->void_reason,
        ];
    }
}
