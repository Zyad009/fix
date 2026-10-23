<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\V1\Admin\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full-stack HTTP tests for the center API: every test starts with one
 * provisioned tenant ("A") whose owner, default branch and context are ready.
 */
abstract class CenterHttpTestCase extends TestCase
{
    use RefreshDatabase;

    /** The schema has SQL views (vehicle_balances, employee_balances). */
    protected bool $dropViews = true;

    protected Tenant $tenant;

    protected Branch $branch;

    protected User $owner;

    /** @var array<int, string> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner, $this->branch] = $this->createTenant('مركز أ');
        $this->inTenant($this->tenant);
    }

    /**
     * Provision a tenant through the real provisioning service.
     *
     * @return array{Tenant, User, Branch}
     */
    protected function createTenant(string $name = 'مركز', array $overrides = []): array
    {
        $tenant = app(TenantProvisioningService::class)->provision([
            'name' => $name,
            'owner' => ['name' => "مالك {$name}", 'email' => fake()->unique()->safeEmail(), 'password' => 'password'],
            ...$overrides,
        ]);

        return tenantContext()->run($tenant, fn () => [$tenant, User::query()->firstOrFail(), Branch::query()->firstOrFail()]);
    }

    /**
     * Act (for direct model reads/writes in the test) as the given tenant.
     */
    protected function inTenant(Tenant $tenant): void
    {
        tenantContext()->set($tenant);
        branchContext()->set(null);
    }

    protected function createBranch(string $name = 'فرع 2', ?Tenant $tenant = null): Branch
    {
        return tenantContext()->run($tenant ?? $this->tenant, fn () => Branch::query()->create(['name' => $name]));
    }

    protected function cashier(?Branch $branch = null): User
    {
        $branch ??= $this->branch;

        return tenantContext()->run($this->tenantOf($branch), fn () => User::factory()->cashier($branch->id)->create());
    }

    protected function technician(?Branch $branch = null): User
    {
        $branch ??= $this->branch;

        return tenantContext()->run($this->tenantOf($branch), fn () => User::factory()->technician($branch->id)->create());
    }

    /**
     * Request headers authenticating as a center user (optionally selecting a branch).
     *
     * @return array<string, string>
     */
    protected function as(User $user, ?Branch $branch = null): array
    {
        $this->app['auth']->forgetGuards();

        $this->tokens[$user->id] ??= $user->createToken('center', ['center'])->plainTextToken;

        return array_filter([
            'Authorization' => 'Bearer '.$this->tokens[$user->id],
            'X-Branch-Id' => $branch?->id ? (string) $branch->id : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function asAdmin(?Admin $admin = null): array
    {
        $this->app['auth']->forgetGuards();

        $admin ??= Admin::factory()->create();

        return ['Authorization' => 'Bearer '.$admin->createToken('admin', ['admin'])->plainTextToken];
    }

    protected function url(string $path): string
    {
        return '/api/v1/center/'.ltrim($path, '/');
    }

    protected function customer(string $name = 'عميل', ?string $phone = null): Customer
    {
        return Customer::query()->create(['name' => $name, 'phone' => $phone ?? '010'.fake()->unique()->numerify('########')]);
    }

    protected function vehicle(?Customer $customer = null, ?string $plate = null): Vehicle
    {
        return Vehicle::query()->create([
            'customer_id' => ($customer ?? $this->customer())->id,
            'plate_number' => $plate ?? 'ق ر '.fake()->unique()->numerify('####'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function product(array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => 'زيت 5W30',
            'kind' => 'part',
            'sale_price' => '100',
            'cost_price' => '60',
            'track_stock' => true,
            ...$attributes,
        ]);
    }

    protected function employee(?Branch $branch = null, string $salary = '5000'): Employee
    {
        return Employee::query()->create([
            'branch_id' => ($branch ?? $this->branch)->id,
            'name' => fake()->name(),
            'base_salary' => $salary,
        ]);
    }

    /**
     * Open an order through the API and return its JSON.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function openOrder(Vehicle $vehicle, array $items = [], array $extra = [], ?User $as = null): array
    {
        return $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $items, ...$extra], $this->as($as ?? $this->owner, $this->branch))
            ->assertCreated()
            ->json('data.order');
    }

    /**
     * A completed invoice of a single free line worth `$amount`.
     */
    protected function invoice(Vehicle $vehicle, string $amount, ?User $as = null): int
    {
        $order = $this->openOrder($vehicle, [['name' => 'خدمة', 'qty' => 1, 'unit_price' => $amount]], as: $as);

        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($as ?? $this->owner, $this->branch))->assertOk();
        $this->inTenant($this->tenant);

        return $order['id'];
    }

    protected function freshOrder(int $orderId): Order
    {
        $this->inTenant($this->tenant);

        return Order::query()->findOrFail($orderId);
    }

    private function tenantOf(Branch $branch): Tenant
    {
        return Tenant::query()->findOrFail($branch->tenant_id);
    }
}
