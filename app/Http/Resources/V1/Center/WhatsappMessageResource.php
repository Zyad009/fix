<?php

namespace App\Http\Resources\V1\Center;

use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsappMessage */
class WhatsappMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->response(),
            'status' => $this->status->response(),
            'recipient' => $this->recipient,
            'order' => $this->whenLoaded('order', fn () => $this->order ? ['id' => $this->order->id, 'order_no' => $this->order->order_no] : null),
            'receipt' => $this->whenLoaded('receipt', fn () => $this->receipt ? ['id' => $this->receipt->id, 'receipt_no' => $this->receipt->receipt_no] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'error' => $this->error,
            'sent_at' => $this->sent_at?->timestamp,
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
