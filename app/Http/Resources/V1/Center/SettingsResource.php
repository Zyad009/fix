<?php

namespace App\Http\Resources\V1\Center;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class SettingsResource extends JsonResource
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
        ];
    }
}
