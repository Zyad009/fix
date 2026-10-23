<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\ChangePasswordRequest;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\Center\BranchResource;
use App\Http\Resources\V1\Center\SettingsResource;
use App\Http\Resources\V1\Center\UserResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\V1\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated('email'), $request->validated('password'));

            return $this->response([
                'token' => $result['token'],
                ...$this->profile($result['user']),
            ], __('messages.login_success'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout(actor());

            return $this->response([], __('messages.logout_success'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function me(): JsonResponse
    {
        try {
            return $this->response($this->profile(actor()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->changePassword(actor(), $request->validated('current_password'), $request->validated('password'));

            return $this->response([], __('messages.password_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $branches = $user->branch_id
            ? Branch::query()->whereKey($user->branch_id)->get()
            : Branch::query()->where('is_active', true)->orderBy('id')->get();

        return [
            'user' => new UserResource($user->load('branch')),
            'tenant' => new SettingsResource($user->tenant),
            'branches' => BranchResource::collection($branches),
            'permissions' => $user->permissions(),
        ];
    }
}
