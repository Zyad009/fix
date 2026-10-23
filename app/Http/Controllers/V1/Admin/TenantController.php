<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\IndexTenantRequest;
use App\Http\Requests\V1\Admin\SubscriptionRequest;
use App\Http\Requests\V1\Admin\TenantStoreRequest;
use App\Http\Requests\V1\Admin\TenantUpdateRequest;
use App\Http\Resources\V1\Admin\TenantResource;
use App\Models\Tenant;
use App\Services\V1\Admin\TenantProvisioningService;
use App\Services\V1\Admin\TenantService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    protected string $resource = TenantResource::class;

    public function __construct(
        protected TenantService $tenantService,
        protected TenantProvisioningService $provisioningService,
    ) {}

    public function index(IndexTenantRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->tenantService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(TenantStoreRequest $request): JsonResponse
    {
        try {
            $tenant = $this->provisioningService->provision($request->validated());

            return $this->response(new $this->resource($this->tenantService->show($tenant)), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Tenant $tenant): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->tenantService->show($tenant)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->tenantService->update($tenant, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function subscription(SubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->tenantService->updateSubscription($tenant, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->tenantService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
