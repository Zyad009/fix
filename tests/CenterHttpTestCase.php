<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\MaintenanceCenter;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\V1\Admin\MaintenanceCenterProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full-stack HTTP tests for the center API: every test starts with one
 * provisioned maintenance center ("A") whose owner, default branch and context are ready.
 */
abstract class CenterHttpTestCase extends TestCase
{
    use RefreshDatabase;

    /** The schema has SQL views (vehicle_balances, employee_balances). */
    protected bool $dropViews = true;

    protected MaintenanceCenter $maintenanceCenter;

    protected Branch $branch;

    protected User $owner;

    /** @var array<int, string> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$this->maintenanceCenter, $this->owner, $this->branch] = $this->createMaintenanceCenter('مركز أ');
        $this->inMaintenanceCenter($this->maintenanceCenter);
    }

    /**
     * Provision a maintenance center through the real provisioning service.
     *
     * @return array{MaintenanceCenter, User, Branch}
     */
    protected function createMaintenanceCenter(string $name = 'مركز', array $overrides = []): array
    {
        $maintenanceCenter = app(MaintenanceCenterProvisioningService::class)->provision([
            'name' => $name,
            'owner' => ['name' => "مالك {$name}", 'email' => fake()->unique()->safeEmail(), 'password' => 'password'],
            ...$overrides,
        ]);

        return maintenanceCenterContext()->run($maintenanceCenter, fn () => [$maintenanceCenter, User::query()->firstOrFail(), Branch::query()->firstOrFail()]);
    }

    /**
     * Act (for direct model reads/writes in the test) as the given maintenance center.
     */
    protected function inMaintenanceCenter(MaintenanceCenter $maintenanceCenter): void
    {
        maintenanceCenterContext()->set($maintenanceCenter);
        branchContext()->set(null);
    }

    protected function createBranch(string $name = 'فرع 2', ?MaintenanceCenter $maintenanceCenter = null): Branch
    {
        return maintenanceCenterContext()->run($maintenanceCenter ?? $this->maintenanceCenter, fn () => Branch::query()->create(['name' => $name]));
    }

    protected function cashier(?Branch $branch = null): User
    {
        $branch ??= $this->branch;

        return maintenanceCenterContext()->run($this->maintenanceCenterOf($branch), fn () => User::factory()->cashier($branch->id)->create());
    }

    protected function technician(?Branch $branch = null): User
    {
        $branch ??= $this->branch;

        return maintenanceCenterContext()->run($this->maintenanceCenterOf($branch), fn () => User::factory()->technician($branch->id)->create());
    }

    /**
     * A technician-cashier login linked to an employee of the branch.
     */
    protected function technicianCashier(?Branch $branch = null): User
    {
        $branch ??= $this->branch;

        return maintenanceCenterContext()->run($this->maintenanceCenterOf($branch), function () use ($branch): User {
            $user = User::factory()->technicianCashier($branch->id)->create();
            Employee::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'name' => $user->name, 'base_salary' => '0']);

            return $user;
        });
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
        $this->inMaintenanceCenter($this->maintenanceCenter);

        return $order['id'];
    }

    protected function freshOrder(int $orderId): Order
    {
        $this->inMaintenanceCenter($this->maintenanceCenter);

        return Order::query()->findOrFail($orderId);
    }

    private function maintenanceCenterOf(Branch $branch): MaintenanceCenter
    {
        return MaintenanceCenter::query()->findOrFail($branch->maintenance_center_id);
    }
}
