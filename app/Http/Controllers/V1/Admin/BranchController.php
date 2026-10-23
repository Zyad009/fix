<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\BranchRequest;
use App\Http\Resources\V1\Center\BranchResource;
use App\Models\Tenant;
use App\Services\V1\Admin\BranchService;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    protected string $resource = BranchResource::class;

    public function __construct(protected BranchService $branchService) {}

    public function index(Tenant $tenant): JsonResponse
    {
        try {
            return $this->response($this->resource::collection($this->branchService->list($tenant)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(BranchRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->store($tenant, $request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(BranchRequest $request, Tenant $tenant, int $branch): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->update($tenant, $branch, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(Tenant $tenant, int $branch): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->branchService->toggle($tenant, $branch)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
