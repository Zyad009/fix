<?php

namespace App\Http\Resources\V1\Center;

use App\Models\EmployeeTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeTransaction */
class EmployeeTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'type' => $this->type->response(),
            'amount' => formatNumber($this->amount),
            'signed_amount' => $this->type->isCredit() ? formatNumber($this->amount) : -formatNumber($this->amount),
            'period' => $this->period?->format('Y-m'),
            'occurred_at' => $this->occurred_at?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'is_voided' => $this->isVoided(),
            'void_reason' => $this->void_reason,
        ];
    }
}
