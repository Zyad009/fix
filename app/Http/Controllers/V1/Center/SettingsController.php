<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\SettingsRequest;
use App\Http\Requests\V1\ImageRequest;
use App\Http\Resources\V1\Center\SettingsResource;
use App\Services\V1\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SettingsController extends Controller implements HasMiddleware
{
    public function __construct(protected SettingsService $settingsService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.manage', except: ['show']),
        ];
    }

    public function show(): JsonResponse
    {
        try {
            return $this->response(new SettingsResource($this->settingsService->show()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        try {
            return $this->response(new SettingsResource($this->settingsService->update($request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function logo(ImageRequest $request): JsonResponse
    {
        try {
            return $this->response(new SettingsResource($this->settingsService->updateLogo($request->file('image'))), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
