<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\Admin\AdminResource;
use App\Services\V1\Admin\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AdminAuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated('email'), $request->validated('password'));

            return $this->response([
                'token' => $result['token'],
                'admin' => new AdminResource($result['admin']),
            ], __('messages.login_success'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->response([], __('messages.logout_success'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function me(Request $request): JsonResponse
    {
        return $this->response(new AdminResource($request->user()));
    }
}
