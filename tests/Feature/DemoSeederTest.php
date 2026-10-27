<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\CarMake;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * The accounts listed in README.md must exist and log in.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected bool $dropViews = true;

    public function test_seeds_the_admin_a_full_demo_center_and_an_empty_one(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, Admin::query()->count());
        $this->assertSame(0, CarMake::query()->whereNull('logo_url')->count());

        $demo = Tenant::query()->where('name', 'مركز الدوار التجريبي')->sole();
        $empty = Tenant::query()->where('name', 'مركز النور')->sole();

        tenantContext()->run($demo, function (): void {
            $this->assertSame(1, Branch::query()->count());
            $this->assertSame(4, User::query()->count());
            $this->assertGreaterThan(0, Customer::query()->count());
            $this->assertGreaterThan(0, Order::query()->withoutGlobalScope('branch')->count());
        });

        tenantContext()->run($empty, function (): void {
            $this->assertSame(1, Branch::query()->count());
            $this->assertSame(['owner2@dawar.test'], User::query()->pluck('email')->all());
            $this->assertSame(0, Customer::query()->count());
            $this->assertSame(0, Product::query()->count());
            $this->assertSame(0, Order::query()->withoutGlobalScope('branch')->count());
            $this->assertSame(0, Employee::query()->withoutGlobalScope('branch')->count());
        });

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@dawar.test', 'password' => DemoSeeder::PASSWORD])->assertOk();

        foreach (['owner@dawar.test', 'cashier@dawar.test', 'tech@dawar.test', 'worker@dawar.test', 'owner2@dawar.test'] as $email) {
            $this->postJson('/api/v1/center/auth/login', ['email' => $email, 'password' => DemoSeeder::PASSWORD])->assertOk();
        }
    }
}
