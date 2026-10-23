<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'base_salary' => formatNumber($this->base_salary),
            'hired_at' => $this->hired_at?->toDateString(),
            'is_active' => $this->is_active,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            // Positive: the center owes the employee; negative: the employee owes advances.
            'balance' => $this->whenLoaded('balance', fn () => formatNumber($this->balance?->balance)),
        ];
    }
}
