<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\BranchRequest;
use App\Http\Resources\V1\Center\BranchResource;
use App\Models\MaintenanceCenter;
use App\Services\V1\Admin\BranchService;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    protected string $resource = BranchResource::class;

    public function __construct(protected BranchService $branchService) {}

    public function index(MaintenanceCenter $maintenanceCenter): JsonResponse
    {
        try {
            return $this->response($this->resource::collection($this->branchService->list($maintenanceCenter)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(BranchRequest $request, MaintenanceCenter $maintenanceCenter): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->store($maintenanceCenter, $request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(BranchRequest $request, MaintenanceCenter $maintenanceCenter, int $branch): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->update($maintenanceCenter, $branch, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(MaintenanceCenter $maintenanceCenter, int $branch): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->toggle($maintenanceCenter, $branch)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
