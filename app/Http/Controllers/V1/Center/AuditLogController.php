<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexAuditLogRequest;
use App\Http\Resources\V1\Center\AuditLogResource;
use App\Services\V1\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuditLogController extends Controller implements HasMiddleware
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:audit.view')];
    }

    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->auditLogService->list($request->validated()), AuditLogResource::class);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
