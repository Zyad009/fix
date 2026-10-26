<?php

namespace Tests\Feature\Center;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Tests\CenterHttpTestCase;

/**
 * Medium workshops: one person repairs, sells and collects. The login is
 * created from the employee page and their orders default to them.
 */
class TechnicianCashierTest extends CenterHttpTestCase
{
    public function test_employee_page_creates_a_linked_login_in_the_same_branch(): void
    {
        $headers = $this->as($this->owner, $this->branch);

        $response = $this->postJson($this->url('employees'), [
            'name' => 'حسن', 'job_title' => 'ميكانيكي', 'base_salary' => 6000,
            'account' => ['email' => 'hassan@dawar.test', 'password' => 'secret123', 'role' => 'technician_cashier'],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'hassan@dawar.test')
            ->assertJsonPath('data.account.role.value', 'technician_cashier');

        $this->inTenant($this->tenant);
        $user = User::query()->where('email', 'hassan@dawar.test')->firstOrFail();
        $this->assertSame(UserRole::TechnicianCashier, $user->role);
        $this->assertSame($this->branch->id, $user->branch_id);
        $this->assertSame('حسن', $user->name);
        $this->assertSame($user->id, Employee::query()->findOrFail($response->json('data.id'))->user_id);

        $this->postJson($this->url('auth/login'), ['email' => 'hassan@dawar.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.user.role.value', 'technician_cashier');
    }

    public function test_employee_login_cannot_be_an_owner_and_email_is_unique(): void
    {
        $headers = $this->as($this->owner, $this->branch);
        $payload = ['name' => 'س', 'base_salary' => 1000];

        $this->postJson($this->url('employees'), [...$payload, 'account' => ['email' => 'x@dawar.test', 'password' => 'secret123', 'role' => 'owner']], $headers)
            ->assertStatus(422);

        $this->postJson($this->url('employees'), [...$payload, 'account' => ['email' => $this->owner->email, 'password' => 'secret123', 'role' => 'cashier']], $headers)
            ->assertStatus(422);

        $this->inTenant($this->tenant);
        $this->assertSame(0, Employee::query()->count());
    }

    public function test_updating_the_employee_adds_or_updates_the_login(): void
    {
        $employee = $this->employee();
        $headers = $this->as($this->owner, $this->branch);

        $this->putJson($this->url("employees/{$employee->id}"), ['account' => ['email' => 'w@dawar.test', 'password' => 'secret123', 'role' => 'technician']], $headers)
            ->assertOk()
            ->assertJsonPath('data.account.role.value', 'technician');

        $this->putJson($this->url("employees/{$employee->id}"), ['name' => 'اسم جديد', 'account' => ['role' => 'technician_cashier']], $headers)
            ->assertOk()
            ->assertJsonPath('data.account.role.value', 'technician_cashier')
            ->assertJsonPath('data.account.email', 'w@dawar.test');

        $this->inTenant($this->tenant);
        $this->assertSame('اسم جديد', User::query()->where('email', 'w@dawar.test')->value('name'));
    }

    public function test_stopping_the_employee_stops_and_signs_out_the_login(): void
    {
        $worker = $this->technicianCashier();
        $employee = Employee::query()->where('user_id', $worker->id)->firstOrFail();
        $workerHeaders = $this->as($worker);

        $this->patchJson($this->url("employees/toggle/{$employee->id}"), [], $this->as($this->owner, $this->branch))
            ->assertOk()
            ->assertJsonPath('data.account.is_active', false);

        $this->assertFalse($worker->refresh()->is_active);
        $this->assertSame(0, $worker->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url('auth/me'), $workerHeaders)->assertUnauthorized();
    }

    public function test_has_cashier_powers_but_nothing_of_the_owner(): void
    {
        $worker = $this->technicianCashier();
        $headers = $this->as($worker);
        $this->tenant->update(['cashier_max_discount' => 50]);

        $order = $this->openOrder($this->vehicle(), [['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]], ['discount' => 50], $worker);

        $this->putJson($this->url("orders/{$order['id']}"), ['discount' => 60], $headers)->assertStatus(422);
        $this->postJson($this->url("orders/{$order['id']}/complete"), ['payments' => [['amount' => 100, 'method' => 'cash']]], $headers)->assertOk();
        $this->postJson($this->url("orders/{$order['id']}/payments"), ['amount' => 50, 'method' => 'cash'], $headers)->assertCreated();
        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $headers)->assertForbidden();

        $this->inTenant($this->tenant);
        $this->postJson($this->url('customers'), ['name' => 'عميل', 'phone' => '01099999999'], $headers)->assertCreated();
        $this->getJson($this->url('expenses'), $headers)->assertOk();

        $this->getJson($this->url('reports/today'), $headers)->assertForbidden();
        $this->postJson($this->url('products'), ['name' => 'x', 'kind' => 'part', 'sale_price' => 1], $headers)->assertForbidden();
        $this->getJson($this->url('employees'), $headers)->assertForbidden();
        $this->getJson($this->url('users'), $headers)->assertForbidden();
    }

    public function test_orders_default_to_their_employee_as_technician(): void
    {
        $worker = $this->technicianCashier();
        $employee = Employee::query()->where('user_id', $worker->id)->firstOrFail();
        $other = $this->employee();

        $order = $this->openOrder($this->vehicle(), [], as: $worker);
        $this->assertSame($employee->id, $order['technician']['id']);

        $order = $this->openOrder($this->vehicle(), [], ['technician_id' => $other->id], $worker);
        $this->assertSame($other->id, $order['technician']['id']);

        $order = $this->openOrder($this->vehicle(), [], ['technician_id' => null], $worker);
        $this->assertNull($order['technician']);

        $order = $this->openOrder($this->vehicle(), [], as: $this->cashier());
        $this->assertNull($order['technician']);
    }
}
