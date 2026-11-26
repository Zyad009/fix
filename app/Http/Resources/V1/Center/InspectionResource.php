<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inspection */
class InspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'summary' => $this->summary(),
            'sections' => array_map(fn (array $section): array => [
                ...$section,
                'items' => array_map(fn (array $item): array => [...$item, 'status' => $item['status']?->response()], $section['items']),
            ], $this->report()),
            'diagnostic_result' => $this->diagnostic_result,
            'notes' => $this->notes,
            'inspected_by' => $this->whenLoaded('inspector', fn () => $this->inspector?->name),
            'whatsapp' => $this->when($this->relationLoaded('order') && $this->order->relationLoaded('latestInspectionWhatsapp'), fn () => ($message = $this->order->latestInspectionWhatsapp) ? [
                'id' => $message->id,
                'status' => $message->status->response(),
                'recipient' => $message->recipient,
                'error' => $message->failureMessage(),
                'sent_at' => $message->sent_at?->timestamp,
                'created_at' => $message->created_at?->timestamp,
            ] : null),
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
