<?php

namespace App\Support\Tenancy;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;

/**
 * The branch the current request acts in.
 *
 * Staff are pinned to their own branch. Owners pick one with the
 * `X-Branch-Id` header; without it they read across all branches, and a
 * write that needs a branch falls back to the only active branch (if there
 * is exactly one) or is refused.
 */
class BranchContext
{
    private ?Branch $branch = null;

    public function set(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function check(): bool
    {
        return $this->branch !== null;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function id(): ?int
    {
        return $this->branch?->id;
    }

    /**
     * The branch a write must be recorded against.
     */
    public function require(): Branch
    {
        if ($branch = $this->resolve()) {
            return $branch;
        }

        throw new BusinessRuleException(__('messages.branch_required'), 422, errorCode: 'branch_required');
    }

    /**
     * The selected branch, or the only active one; null when it is ambiguous.
     */
    public function resolve(): ?Branch
    {
        if ($this->branch) {
            return $this->branch;
        }

        $activeBranches = Branch::query()->where('is_active', true)->limit(2)->get();

        return $activeBranches->count() === 1 ? $activeBranches->first() : null;
    }

    public function requireId(): int
    {
        return $this->require()->id;
    }
}
