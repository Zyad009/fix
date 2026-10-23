<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Support\Tenancy\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the acting branch. Staff are pinned to their own branch; owners
 * may pick one with `X-Branch-Id` (or none, meaning every branch).
 * Must run after `tenant`.
 */
class BranchContextMiddleware
{
    public const HEADER = 'X-Branch-Id';

    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = actor();
        $requestedId = $request->header(self::HEADER);

        if ($user->branch_id !== null) {
            if (filled($requestedId) && (int) $requestedId !== $user->branch_id) {
                throw new BusinessRuleException(__('messages.branch_forbidden'), 403, errorCode: 'branch_forbidden');
            }

            $this->context->set($user->branch);

            return $next($request);
        }

        $branch = null;

        if (filled($requestedId)) {
            $branch = Branch::query()->whereKey((int) $requestedId)->where('is_active', true)->first()
                ?? throw new BusinessRuleException(__('messages.branch_forbidden'), 403, errorCode: 'branch_forbidden');
        }

        $this->context->set($branch);

        return $next($request);
    }
}
