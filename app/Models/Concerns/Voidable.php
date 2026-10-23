<?php

namespace App\Models\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only financial rows: mistakes are voided with a reason, never
 * updated or deleted.
 *
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 */
trait Voidable
{
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function markVoided(string $reason): void
    {
        if ($this->isVoided()) {
            throw new BusinessRuleException(__('messages.already_voided'), 409);
        }

        $this->forceFill([
            'voided_at' => now(),
            'voided_by' => actor()?->id,
            'void_reason' => $reason,
        ])->save();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeNotVoided(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('voided_at'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
