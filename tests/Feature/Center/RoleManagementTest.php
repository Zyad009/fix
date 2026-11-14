<?php

namespace Tests\Feature\Center;

use App\Models\Role;
use App\Models\User;
use Tests\CenterHttpTestCase;

/**
 * Each center shapes its own roles; the owner role stays fixed so a center
 * can never lock itself out.
 */
class RoleManagementTest extends CenterHttpTestCase
{
    public function test_a_new_center_starts_with_the_default_roles_and_a_grouped_catalog(): void
    {
        $headers = $this->as($this->owner);

        $this->getJson($this->url('roles'), $headers)
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.key', 'owner')
            ->assertJsonPath('data.0.is_owner', true)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.permissions', config('rbac.permissions'));

        $catalog = $this->getJson($this->url('roles/permissions'), $headers)->assertOk()->json('data');
        $keys = collect($catalog)->pluck('permissions')->flatten(1)->pluck('key');

        $this->assertSame('الإعدادات والحسابات', $catalog[0]['label']);
        $this->assertContains('orders.edit_completed', $keys);
        $this->assertNotContains('roles.manage', $keys);

        $this->getJson($this->url('options'), $headers)->assertOk()->assertJsonPath('data.roles.1', ['label' => 'كاشير', 'value' => 'cashier']);
    }

    public function test_a_custom_role_grants_exactly_its_permissions_and_edits_apply_at_once(): void
    {
        $owner = $this->as($this->owner);

        $role = $this->postJson($this->url('roles'), ['name' => 'محاسب', 'permissions' => ['reports.view', 'expenses.view']], $owner)
            ->assertCreated()
            ->assertJsonPath('data.is_default', false)
            ->json('data');

        $this->assertSame('role_'.$role['id'], $role['key']);

        $this->postJson($this->url('users'), [
            'name' => 'محاسب', 'email' => 'acc@dawar.test', 'password' => 'secret123', 'role' => $role['key'], 'branch_id' => $this->branch->id,
        ], $owner)->assertCreated()->assertJsonPath('data.role', ['label' => 'محاسب', 'value' => $role['key']]);

        $user = User::query()->where('email', 'acc@dawar.test')->firstOrFail();
        $accountant = $this->as($user);

        $this->getJson($this->url('auth/me'), $accountant)->assertOk()->assertJsonPath('data.permissions', ['reports.view', 'expenses.view']);
        $this->getJson($this->url('reports/today'), $accountant)->assertOk();
        $this->getJson($this->url('customers'), $accountant)->assertForbidden();

        $this->putJson($this->url("roles/{$role['id']}"), ['permissions' => ['customers.view']], $this->as($this->owner))->assertOk();

        // Same token, next request: the role is read fresh.
        $accountant = $this->as($user);
        $this->getJson($this->url('customers'), $accountant)->assertOk();
        $this->getJson($this->url('reports/today'), $accountant)->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'role', 'auditable_id' => $role['id'], 'event' => 'updated']);
    }

    public function test_the_owner_role_is_locked_and_a_role_in_use_cannot_be_deleted(): void
    {
        $owner = $this->as($this->owner);
        $ownerRoleId = Role::idOf('owner');
        $cashierRoleId = Role::idOf('cashier');
        $this->cashier();

        $this->putJson($this->url("roles/{$ownerRoleId}"), ['name' => 'مدير'], $owner)->assertStatus(422);
        $this->deleteJson($this->url("roles/{$ownerRoleId}"), [], $owner)->assertStatus(422);
        $this->deleteJson($this->url("roles/{$cashierRoleId}"), [], $owner)->assertStatus(409);

        $this->putJson($this->url("roles/{$cashierRoleId}"), ['name' => 'استقبال'], $owner)->assertOk()->assertJsonPath('data.key', 'cashier');
        $this->deleteJson($this->url('roles/'.Role::idOf('technician')), [], $owner)->assertOk();
        $this->assertDatabaseMissing('roles', ['maintenance_center_id' => $this->maintenanceCenter->id, 'key' => 'technician']);
    }

    public function test_role_input_is_validated(): void
    {
        $owner = $this->as($this->owner);

        $this->postJson($this->url('roles'), ['name' => 'x', 'permissions' => ['orders.fly']], $owner)->assertStatus(422);
        $this->postJson($this->url('roles'), ['name' => 'x', 'permissions' => ['roles.manage']], $owner)->assertStatus(422);
        $this->postJson($this->url('roles'), ['name' => 'كاشير', 'permissions' => []], $owner)->assertStatus(422);
        $this->postJson($this->url('roles'), ['name' => 'x'], $owner)->assertStatus(422);
    }

    public function test_staff_never_reach_roles_or_owner_accounts_even_with_users_manage(): void
    {
        $this->getJson($this->url('roles'), $this->as($this->cashier()))->assertForbidden();

        $managerRole = Role::query()->create(['key' => 'manager', 'name' => 'مدير', 'permissions' => ['users.manage']]);
        $manager = User::factory()->create(['role_id' => $managerRole->id, 'branch_id' => $this->branch->id]);
        $headers = $this->as($manager);

        $this->getJson($this->url('roles'), $headers)->assertForbidden();
        $this->putJson($this->url("users/{$this->owner->id}"), ['password' => 'hijacked1'], $headers)->assertForbidden();
        $this->patchJson($this->url("users/toggle/{$this->owner->id}"), [], $headers)->assertForbidden();
        $this->postJson($this->url('users'), ['name' => 'x', 'email' => 'x@dawar.test', 'password' => 'secret123', 'role' => 'owner'], $headers)->assertForbidden();

        $this->postJson($this->url('users'), [
            'name' => 'كاشير', 'email' => 'c@dawar.test', 'password' => 'secret123', 'role' => 'cashier', 'branch_id' => $this->branch->id,
        ], $headers)->assertCreated();
    }

    public function test_roles_are_isolated_between_maintenance_centers(): void
    {
        [$other, $otherOwner] = $this->createMaintenanceCenter('مركز ب');
        $otherRole = $this->postJson($this->url('roles'), ['name' => 'خاص', 'permissions' => ['reports.view']], $this->as($otherOwner))->json('data');

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $owner = $this->as($this->owner);

        $this->getJson($this->url("roles/{$otherRole['id']}"), $owner)->assertNotFound();
        $this->putJson($this->url("roles/{$otherRole['id']}"), ['name' => 'x'], $owner)->assertNotFound();
        $this->deleteJson($this->url("roles/{$otherRole['id']}"), [], $owner)->assertNotFound();
        $this->getJson($this->url('roles'), $owner)->assertJsonCount(4, 'data');

        $this->postJson($this->url('users'), [
            'name' => 'x', 'email' => 'x@dawar.test', 'password' => 'secret123', 'role' => $otherRole['key'], 'branch_id' => $this->branch->id,
        ], $owner)->assertStatus(422);

        $this->assertSame(5, Role::query()->withoutGlobalScope('maintenance_center')->where('maintenance_center_id', $other->id)->count());
    }

    public function test_technician_cashier_edits_a_closed_invoice_but_cannot_cancel_it(): void
    {
        $worker = $this->technicianCashier();
        $order = $this->openOrder($this->vehicle(), [['name' => 'زيت', 'qty' => 1, 'unit_price' => 100]], as: $worker);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($worker))->assertOk();
        $itemId = $order['items'][0]['id'];

        $this->putJson($this->url("orders/{$order['id']}/items/{$itemId}"), ['qty' => 2], $this->as($worker))
            ->assertOk()
            ->assertJsonPath('data.order.total', 200);

        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $this->as($worker))->assertForbidden();
    }
}
