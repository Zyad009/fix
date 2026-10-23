<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A subscribed service center.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $logo_path
 * @property string $plan
 * @property Carbon|null $subscription_ends_at
 * @property string $cashier_max_discount
 * @property string $timezone
 * @property bool $is_active
 */
#[Fillable(['name', 'phone', 'address', 'logo_path', 'plan', 'subscription_ends_at', 'cashier_max_discount', 'timezone', 'is_active'])]
class Tenant extends Model
{
    /** @var array<string, mixed> Mirrors the column defaults so fresh models are complete. */
    protected $attributes = [
        'plan' => 'trial',
        'cashier_max_discount' => '0.00',
        'timezone' => 'Africa/Cairo',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'datetime',
            'cashier_max_discount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? publicFileUrl($this->logo_path) : null;
    }
}
