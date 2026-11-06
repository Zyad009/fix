<?php

namespace App\Http\Resources\V1\Center;

use App\Models\MaintenanceCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceCenter */
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
            'whatsapp_connected' => filled($this->whatsapp_device_id),
            'whatsapp_invoice_enabled' => $this->whatsapp_invoice_enabled,
            'whatsapp_payment_enabled' => $this->whatsapp_payment_enabled,
            'whatsapp_service_reminder_enabled' => $this->whatsapp_service_reminder_enabled,
            'whatsapp_debt_reminder_enabled' => $this->whatsapp_debt_reminder_enabled,
            'debt_reminder_days' => $this->debt_reminder_days,
        ];
    }
}
