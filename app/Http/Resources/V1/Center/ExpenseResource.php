<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'amount' => formatNumber($this->amount),
            'method' => $this->method->response(),
            'spent_at' => $this->spent_at?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'is_voided' => $this->isVoided(),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->voidedBy?->name),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
