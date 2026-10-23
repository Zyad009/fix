<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Support\Period;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Record a sensitive change (invoice edits, cancellations, voids, stock).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(Model $model, AuditEvent $event, ?array $old = null, ?array $new = null, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => actor()?->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->when($filters['auditable_type'] ?? null, fn ($query, $type) => $query->where('auditable_type', $type))
            ->when($filters['auditable_id'] ?? null, fn ($query, $id) => $query->where('auditable_id', $id))
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn ($query) => $query->where('created_at', '>=', Period::fromFilters($filters)->start))
            ->when($filters['to'] ?? null, fn ($query) => $query->where('created_at', '<=', Period::fromFilters($filters)->end))
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.med'));
    }
}
