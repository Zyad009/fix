<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\CarMakeRequest;
use App\Http\Requests\V1\Admin\CarModelRequest;
use App\Http\Resources\V1\CarMakeResource;
use App\Http\Resources\V1\CarModelResource;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Services\V1\CarCatalogService;
use Illuminate\Http\JsonResponse;

class CarMakeController extends Controller
{
    public function __construct(protected CarCatalogService $catalogService) {}

    public function index(): JsonResponse
    {
        try {
            return $this->response(CarMakeResource::collection($this->catalogService->list(activeOnly: false)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(CarMakeRequest $request): JsonResponse
    {
        try {
            return $this->response(new CarMakeResource($this->catalogService->storeMake($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(CarMakeRequest $request, CarMake $carMake): JsonResponse
    {
        try {
            return $this->response(new CarMakeResource($this->catalogService->updateMake($carMake, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new CarMakeResource($this->catalogService->toggleMake($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function storeModel(CarModelRequest $request, CarMake $carMake): JsonResponse
    {
        try {
            return $this->response(new CarModelResource($this->catalogService->storeModel($carMake, $request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function updateModel(CarModelRequest $request, CarModel $carModel): JsonResponse
    {
        try {
            return $this->response(new CarModelResource($this->catalogService->updateModel($carModel, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggleModel(int $id): JsonResponse
    {
        try {
            return $this->response(new CarModelResource($this->catalogService->toggleModel($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
