<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\IndexMaintenanceCenterRequest;
use App\Http\Requests\V1\Admin\MaintenanceCenterStoreRequest;
use App\Http\Requests\V1\Admin\MaintenanceCenterUpdateRequest;
use App\Http\Requests\V1\Admin\SubscriptionRequest;
use App\Http\Resources\V1\Admin\MaintenanceCenterResource;
use App\Models\MaintenanceCenter;
use App\Services\V1\Admin\MaintenanceCenterProvisioningService;
use App\Services\V1\Admin\MaintenanceCenterService;
use Illuminate\Http\JsonResponse;

class MaintenanceCenterController extends Controller
{
    protected string $resource = MaintenanceCenterResource::class;

    public function __construct(
        protected MaintenanceCenterService $maintenanceCenterService,
        protected MaintenanceCenterProvisioningService $provisioningService,
    ) {}

    public function index(IndexMaintenanceCenterRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->maintenanceCenterService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(MaintenanceCenterStoreRequest $request): JsonResponse
    {
        try {
            $maintenanceCenter = $this->provisioningService->provision($request->validated());

            return $this->response(new $this->resource($this->maintenanceCenterService->show($maintenanceCenter)), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(MaintenanceCenter $maintenanceCenter): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->maintenanceCenterService->show($maintenanceCenter)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(MaintenanceCenterUpdateRequest $request, MaintenanceCenter $maintenanceCenter): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->maintenanceCenterService->update($maintenanceCenter, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function subscription(SubscriptionRequest $request, MaintenanceCenter $maintenanceCenter): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->maintenanceCenterService->updateSubscription($maintenanceCenter, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->maintenanceCenterService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
