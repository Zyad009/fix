<?php

namespace App\Http\Resources\V1\Center;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->response(),
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'reason' => $this->reason,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
