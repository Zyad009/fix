<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexUserRequest;
use App\Http\Requests\V1\Center\UserRequest;
use App\Http\Resources\V1\Center\UserResource;
use App\Models\User;
use App\Services\V1\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    protected string $resource = UserResource::class;

    public function __construct(protected UserService $userService) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:users.manage')];
    }

    public function index(IndexUserRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->userService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(UserRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->userService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(User $user): JsonResponse
    {
        try {
            return $this->response(new $this->resource($user->load('branch:id,name')));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->userService->update($user, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->userService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
