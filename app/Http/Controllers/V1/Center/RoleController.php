<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\RoleRequest;
use App\Http\Resources\V1\Center\RoleResource;
use App\Models\Role;
use App\Services\V1\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The owner shapes the center's roles. `roles.manage` is never grantable, so
 * this stays the owner's screen.
 */
class RoleController extends Controller implements HasMiddleware
{
    protected string $resource = RoleResource::class;

    public function __construct(protected RoleService $roleService) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:roles.manage')];
    }

    public function index(): JsonResponse
    {
        try {
            return $this->response($this->resource::collection($this->roleService->list()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function permissions(): JsonResponse
    {
        try {
            return $this->response($this->roleService->catalog());
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(RoleRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->roleService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Role $role): JsonResponse
    {
        try {
            return $this->response(new $this->resource($role->loadCount('users')));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->roleService->update($role, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->destroy($role);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
