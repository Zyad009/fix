<?php

namespace Tests\Feature\Center;

use App\Models\ExpenseCategory;
use Tests\CenterHttpTestCase;

class ExpenseTest extends CenterHttpTestCase
{
    public function test_cashier_records_expenses_and_lists_them_with_filters(): void
    {
        $rent = ExpenseCategory::query()->where('name', 'إيجار')->firstOrFail();
        $power = ExpenseCategory::query()->where('name', 'كهرباء')->firstOrFail();
        $cashier = $this->cashier();

        $this->postJson($this->url('expenses'), ['category_id' => $rent->id, 'amount' => 8000, 'spent_at' => '2026-10-01'], $this->as($cashier))
            ->assertCreated()
            ->assertJsonPath('data.method.value', 'cash');
        $this->postJson($this->url('expenses'), ['category_id' => $power->id, 'amount' => 650.5, 'method' => 'instapay', 'spent_at' => '2026-10-05'], $this->as($cashier))
            ->assertCreated();

        $this->getJson($this->url('expenses?from=2026-10-02&to=2026-10-31'), $this->as($cashier))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 650.5);

        $this->getJson($this->url("expenses?category_id={$rent->id}"), $this->as($cashier))->assertJsonCount(1, 'data');

        $this->getJson($this->url('expenses/summary?from=2026-10-01&to=2026-10-31'), $this->as($cashier))
            ->assertOk()
            ->assertJsonPath('data.total', 8650.5)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.by_category.0.name', 'إيجار');
    }

    public function test_only_owner_voids_and_voided_expenses_drop_out(): void
    {
        $category = ExpenseCategory::query()->firstOrFail();
        $id = $this->postJson($this->url('expenses'), ['category_id' => $category->id, 'amount' => 100], $this->as($this->owner))->json('data.id');

        $this->patchJson($this->url("expenses/{$id}/void"), ['reason' => 'x'], $this->as($this->cashier()))->assertForbidden();
        $this->patchJson($this->url("expenses/{$id}/void"), ['reason' => 'مكرر'], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.is_voided', true);

        $this->getJson($this->url('expenses'), $this->as($this->owner))->assertJsonCount(0, 'data');
        $this->getJson($this->url('expenses?include_voided=1'), $this->as($this->owner))->assertJsonCount(1, 'data');
    }

    public function test_technician_has_no_access_to_expenses(): void
    {
        $this->getJson($this->url('expenses'), $this->as($this->technician()))->assertForbidden();
    }

    public function test_expense_categories(): void
    {
        $headers = $this->as($this->owner);

        $id = $this->postJson($this->url('expense-categories'), ['name' => 'صيانة معدات'], $headers)->assertCreated()->json('data.id');
        $this->postJson($this->url('expense-categories'), ['name' => 'صيانة معدات'], $headers)->assertStatus(422);

        $this->postJson($this->url('expenses'), ['category_id' => $id, 'amount' => 50], $headers)->assertCreated();
        $this->deleteJson($this->url("expense-categories/{$id}"), [], $headers)->assertStatus(409);
    }
}
