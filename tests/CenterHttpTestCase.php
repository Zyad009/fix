<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\V1\Admin\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full-stack HTTP tests for the center API: every test starts with one
 * provisioned tenant ("A") whose owner, default branch and context are ready.
 */
abstract class CenterHttpTestCase extends TestCase
{
    use RefreshDatabase;

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

    private function tenantOf(Branch $branch): Tenant
    {
        return Tenant::query()->findOrFail($branch->tenant_id);
    }
}
