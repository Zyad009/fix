<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\Tenancy\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes queries to the selected branch (when one is selected) and stamps
 * `branch_id` on create. Use `withoutGlobalScope('branch')` for deliberate
 * cross-branch reads such as a customer's debts.
 *
 * @property int $branch_id
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            $branchId = app(BranchContext::class)->id();

            if ($branchId !== null) {
                $query->where($query->qualifyColumn('branch_id'), $branchId);
            }
        });

        static::creating(function (self $model): void {
            $model->branch_id ??= app(BranchContext::class)->requireId();
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
