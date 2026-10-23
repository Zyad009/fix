<?php

namespace Tests\Feature\Center;

use Tests\CenterHttpTestCase;

/**
 * brief §4.6 — employee ledger, salaries and advances.
 */
class EmployeePayrollTest extends CenterHttpTestCase
{
    public function test_salary_minus_advance_leaves_the_net_due(): void
    {
        $employee = $this->employee(salary: '5000');
        $headers = $this->as($this->owner);

        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'salary', 'amount' => 5000, 'period' => '2026-10'], $headers)->assertCreated();
        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'advance', 'amount' => 1000], $headers)->assertCreated();

        $this->getJson($this->url("employees/{$employee->id}/statement"), $headers)
            ->assertOk()
            ->assertJsonPath('data.balance', 4000)
            ->assertJsonCount(2, 'data.transactions')
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_bonus_deduction_and_payment_move_the_balance(): void
    {
        $employee = $this->employee();
        $headers = $this->as($this->owner);

        foreach ([['salary', 5000, '2026-10'], ['bonus', 500, null], ['deduction', 200, null], ['payment', 3000, null]] as [$type, $amount, $period]) {
            $this->postJson($this->url("employees/{$employee->id}/transactions"), array_filter(['type' => $type, 'amount' => $amount, 'period' => $period]), $headers)->assertCreated();
        }

        $this->getJson($this->url("employees/{$employee->id}"), $headers)->assertJsonPath('data.balance', 2300);
    }

    public function test_salary_cannot_be_issued_twice_for_the_same_month_unless_voided(): void
    {
        $employee = $this->employee();
        $headers = $this->as($this->owner);

        $first = $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'salary', 'amount' => 5000, 'period' => '2026-10'], $headers)
            ->assertCreated()
            ->json('data.id');

        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'salary', 'amount' => 5000, 'period' => '2026-10'], $headers)
            ->assertStatus(409);

        $this->patchJson($this->url("employee-transactions/{$first}/void"), ['reason' => 'مبلغ غلط'], $headers)->assertOk();

        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'salary', 'amount' => 5500, 'period' => '2026-10'], $headers)
            ->assertCreated();

        $this->getJson($this->url("employees/{$employee->id}/statement"), $headers)->assertJsonPath('data.balance', 5500);
    }

    public function test_payroll_issue_for_all_active_employees(): void
    {
        $ahmed = $this->employee(salary: '4000');
        $mona = $this->employee(salary: '6000');
        $inactive = $this->employee(salary: '3000');
        $inactive->update(['is_active' => false]);
        $headers = $this->as($this->owner);

        $this->postJson($this->url('payroll/issue'), [
            'period' => '2026-10',
            'items' => [['employee_id' => $mona->id, 'amount' => 6500]],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.issued', 2);

        $this->getJson($this->url('payroll/preview?period=2026-10'), $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.already_issued', true);

        $this->postJson($this->url('payroll/issue'), ['period' => '2026-10'], $headers)->assertJsonPath('data.issued', 0);

        $this->getJson($this->url("employees/{$ahmed->id}"), $headers)->assertJsonPath('data.balance', 4000);
        $this->getJson($this->url("employees/{$mona->id}"), $headers)->assertJsonPath('data.balance', 6500);
    }

    public function test_only_owner_manages_payroll(): void
    {
        $employee = $this->employee();
        $cashier = $this->cashier();

        $this->getJson($this->url('employees'), $this->as($cashier))->assertForbidden();
        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'advance', 'amount' => 100], $this->as($cashier))->assertForbidden();

        $this->getJson($this->url('employees/technicians'), $this->as($cashier))
            ->assertOk()
            ->assertJsonPath('data.0.id', $employee->id);
    }

    public function test_employee_crud(): void
    {
        $headers = $this->as($this->owner);

        $id = $this->postJson($this->url('employees'), ['name' => 'فني 1', 'job_title' => 'ميكانيكي', 'base_salary' => 4500, 'hired_at' => '2026-01-01'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.branch.id', $this->branch->id)
            ->json('data.id');

        $this->putJson($this->url("employees/{$id}"), ['base_salary' => 5000], $headers)->assertOk()->assertJsonPath('data.base_salary', 5000);
        $this->patchJson($this->url("employees/toggle/{$id}"), [], $headers)->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson($this->url("employees/{$id}/transactions"), ['type' => 'bonus', 'amount' => 100], $headers)->assertStatus(422);
    }
}
