<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexRoutineServiceRequest;
use App\Http\Requests\V1\Center\RoutineServiceRequest;
use App\Http\Requests\V1\ListRequest;
use App\Http\Resources\V1\Center\RoutineServiceResource;
use App\Http\Resources\V1\Center\RoutineServiceUsageResource;
use App\Models\RoutineService;
use App\Services\V1\RoutineServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoutineServiceController extends Controller implements HasMiddleware
{
    protected string $resource = RoutineServiceResource::class;

    public function __construct(protected RoutineServiceService $routineServiceService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:catalog.view', only: ['index', 'show']),
            new Middleware('permission:orders.view_closed', only: ['history']),
            new Middleware('permission:catalog.manage', only: ['store', 'update', 'destroy', 'toggle']),
        ];
    }

    public function index(IndexRoutineServiceRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->routineServiceService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(RoutineServiceRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->routineServiceService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(RoutineService $routineService): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->routineServiceService->load($routineService)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(RoutineServiceRequest $request, RoutineService $routineService): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->routineServiceService->update($routineService, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(RoutineService $routineService): JsonResponse
    {
        try {
            $this->routineServiceService->destroy($routineService);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->routineServiceService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Usage history: the invoice lines that used the service.
     */
    public function history(ListRequest $request, RoutineService $routineService): JsonResponse
    {
        try {
            return $this->paginate($this->routineServiceService->history($routineService, $request->validated()), RoutineServiceUsageResource::class);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
