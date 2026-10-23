<?php

namespace App\Http\Resources\V1\Center;

use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentReceipt */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'branch_id' => $this->branch_id,
            'amount' => formatNumber($this->amount),
            'method' => $this->method->response(),
            'received_at' => $this->received_at?->timestamp,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? ['id' => $this->vehicle->id, 'plate_number' => $this->vehicle->plate_number] : null),
            'received_by' => $this->whenLoaded('receiver', fn () => $this->receiver?->name),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'is_voided' => $this->isVoided(),
            'voided_at' => $this->voided_at?->timestamp,
            'void_reason' => $this->void_reason,
        ];
    }
}
