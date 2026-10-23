<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Tests\CenterHttpTestCase;

class AdminTenantTest extends CenterHttpTestCase
{
    public function test_creating_a_tenant_provisions_owner_branch_and_default_categories(): void
    {
        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'مركز النور',
            'phone' => '01000000000',
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'owner' => ['name' => 'أحمد', 'email' => 'owner@noor.test', 'password' => 'secret123'],
        ], $this->asAdmin());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'مركز النور')
            ->assertJsonPath('data.branches.0.is_default', true);

        $tenant = Tenant::query()->findOrFail($response->json('data.id'));
        $this->inTenant($tenant);

        $owner = User::query()->firstOrFail();
        $this->assertSame('owner@noor.test', $owner->email);
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertNull($owner->branch_id);
        $this->assertSame(1, Branch::query()->count());
        $this->assertEqualsCanonicalizing(Category::DEFAULTS, Category::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(ExpenseCategory::DEFAULTS, ExpenseCategory::query()->pluck('name')->all());

        $this->postJson('/api/v1/center/auth/login', ['email' => 'owner@noor.test', 'password' => 'secret123'])->assertOk();
    }

    public function test_owner_email_must_be_unique_across_tenants(): void
    {
        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'مركز',
            'owner' => ['name' => 'x', 'email' => $this->owner->email, 'password' => 'secret123'],
        ], $this->asAdmin())->assertStatus(422);
    }

    public function test_listing_and_searching_tenants(): void
    {
        $this->createTenant('مركز الأمل');

        $this->getJson('/api/v1/admin/tenants?q=الأمل', $this->asAdmin())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مركز الأمل')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_suspending_a_tenant_revokes_its_sessions(): void
    {
        $headers = $this->as($this->owner);
        $this->getJson($this->url('settings'), $headers)->assertOk();

        $this->patchJson("/api/v1/admin/tenants/toggle/{$this->tenant->id}", [], $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url('settings'), $headers)->assertUnauthorized();

        $this->postJson('/api/v1/center/auth/login', ['email' => $this->owner->email, 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_updating_subscription(): void
    {
        $this->patchJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription", [
            'plan' => 'pro',
            'subscription_ends_at' => '2030-01-01',
        ], $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.is_subscription_expired', false);
    }

    public function test_admin_manages_branches(): void
    {
        $admin = $this->asAdmin();

        $branchId = $this->postJson("/api/v1/admin/tenants/{$this->tenant->id}/branches", ['name' => 'فرع المعادي'], $admin)
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/admin/tenants/{$this->tenant->id}/branches", ['name' => 'فرع المعادي'], $admin)
            ->assertStatus(422);

        $this->putJson("/api/v1/admin/tenants/{$this->tenant->id}/branches/{$branchId}", ['name' => 'فرع مدينة نصر'], $admin)
            ->assertOk()
            ->assertJsonPath('data.name', 'فرع مدينة نصر');

        $this->patchJson("/api/v1/admin/tenants/{$this->tenant->id}/branches/{$branchId}/toggle", [], $admin)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/v1/admin/tenants/{$this->tenant->id}/branches/{$this->branch->id}/toggle", [], $admin)
            ->assertStatus(422);

        $this->getJson("/api/v1/admin/tenants/{$this->tenant->id}/branches", $admin)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_branch_of_another_tenant_cannot_be_edited_through_this_tenant(): void
    {
        [$other, , $otherBranch] = $this->createTenant('مركز ب');

        $this->putJson("/api/v1/admin/tenants/{$this->tenant->id}/branches/{$otherBranch->id}", ['name' => 'x'], $this->asAdmin())
            ->assertNotFound();
    }
}
