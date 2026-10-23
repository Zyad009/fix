<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Center\BranchResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo_url' => $this->logoUrl(),
            'plan' => $this->plan,
            'subscription_ends_at' => $this->subscription_ends_at?->timestamp,
            'is_subscription_expired' => $this->isSubscriptionExpired(),
            'cashier_max_discount' => formatNumber($this->cashier_max_discount),
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'branches_count' => $this->whenCounted('branches'),
            'users_count' => $this->whenCounted('users'),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
