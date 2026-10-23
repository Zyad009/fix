<?php

namespace Tests\Feature\Center;

use App\Models\ExpenseCategory;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Tests\CenterHttpTestCase;

/**
 * brief §4.8 — owner dashboard and reports.
 */
class ReportTest extends CenterHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 Cairo time on 2026-10-15.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Africa/Cairo'));
    }

    public function test_today_dashboard(): void
    {
        $product = $this->product(['sale_price' => '400', 'cost_price' => '250']);
        $paid = $this->openOrder($this->vehicle(), [['product_id' => $product->id, 'qty' => 1]]);
        $this->postJson($this->url("orders/{$paid['id']}/complete"), ['payments' => [['amount' => 300, 'method' => 'cash']]], $this->as($this->owner))->assertOk();
        $this->openOrder($this->vehicle());
        $this->expense('120');

        $this->getJson($this->url('reports/today'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-15')
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonPath('data.open_orders_count', 1)
            ->assertJsonPath('data.sales', 400)
            ->assertJsonPath('data.collected', 300)
            ->assertJsonPath('data.new_debts', 100)
            ->assertJsonPath('data.expenses', 120)
            ->assertJsonPath('data.total_debt', 100);
    }

    public function test_reports_are_owner_only(): void
    {
        $this->getJson($this->url('reports/today'), $this->as($this->cashier()))->assertForbidden();
    }

    public function test_sales_excludes_opening_balances_and_cancelled_orders(): void
    {
        $kept = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 500]], ['discount' => 50]);
        $this->postJson($this->url("orders/{$kept['id']}/complete"), ['payments' => [['amount' => 450, 'method' => 'instapay']]], $this->as($this->owner))->assertOk();

        $cancelled = $this->invoice($this->vehicle(), '1000');
        $this->postJson($this->url("orders/{$cancelled}/cancel"), ['reason' => 'x'], $this->as($this->owner))->assertOk();

        $this->postJson($this->url("vehicles/{$this->vehicle()->id}/opening-balance"), ['amount' => 900], $this->as($this->owner))->assertCreated();

        $response = $this->getJson($this->url('reports/sales?from=2026-10-15&to=2026-10-15'), $this->as($this->owner))->assertOk();

        $response->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.subtotal', 500)
            ->assertJsonPath('data.discount', 50)
            ->assertJsonPath('data.total', 450)
            ->assertJsonPath('data.daily.0.total', 450);

        $instapay = collect($response->json('data.collections_by_method'))->firstWhere('method.value', 'instapay');
        $this->assertSame(450, $instapay['net']);
    }

    public function test_day_boundaries_follow_cairo_time(): void
    {
        // 23:30 Cairo on the 14th is 20:30 UTC; it belongs to the 14th, not the 15th.
        $this->travelTo(CarbonImmutable::parse('2026-10-14 23:30', 'Africa/Cairo'));
        $this->invoice($this->vehicle(), '100');

        $this->travelTo(CarbonImmutable::parse('2026-10-15 00:30', 'Africa/Cairo'));
        $this->invoice($this->vehicle(), '200');

        $this->getJson($this->url('reports/sales?from=2026-10-14&to=2026-10-14'), $this->as($this->owner))->assertJsonPath('data.total', 100);
        $this->getJson($this->url('reports/sales?from=2026-10-15&to=2026-10-15'), $this->as($this->owner))->assertJsonPath('data.total', 200);
        $this->getJson($this->url('reports/sales?from=2026-10-14&to=2026-10-15'), $this->as($this->owner))
            ->assertJsonPath('data.daily.0.total', 100)
            ->assertJsonPath('data.daily.1.total', 200);
    }

    public function test_profit_is_sales_minus_cost_expenses_and_payroll(): void
    {
        $product = $this->product(['sale_price' => '1000', 'cost_price' => '600']);
        $order = $this->openOrder($this->vehicle(), [['product_id' => $product->id, 'qty' => 2], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner))->assertOk();

        $this->expense('500');
        $employee = $this->employee();
        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'salary', 'amount' => 800, 'period' => '2026-10'], $this->as($this->owner))->assertCreated();
        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'advance', 'amount' => 300], $this->as($this->owner))->assertCreated();

        $this->getJson($this->url('reports/profit?from=2026-10-01&to=2026-10-31'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.sales', 2300)
            ->assertJsonPath('data.cost_of_goods', 1200)
            ->assertJsonPath('data.gross_profit', 1100)
            ->assertJsonPath('data.expenses', 500)
            ->assertJsonPath('data.payroll', 800)
            ->assertJsonPath('data.net_profit', -200)
            ->assertJsonPath('data.items_without_cost', 1);
    }

    public function test_cash_flow_counts_only_cash_and_refunds_once(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 1000]]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [
            'payments' => [['amount' => 600, 'method' => 'cash'], ['amount' => 400, 'method' => 'card']],
        ], $this->as($this->owner))->assertOk();

        $refunded = $this->invoice($this->vehicle(), '200');
        $this->postJson($this->url("orders/{$refunded}/payments"), ['amount' => 200, 'method' => 'cash'], $this->as($this->owner))->assertCreated();
        $this->postJson($this->url("orders/{$refunded}/cancel"), ['reason' => 'مرتجع'], $this->as($this->owner))->assertOk();

        $this->expense('100');
        $this->expense('999', 'bank');
        $employee = $this->employee();
        $this->postJson($this->url("employees/{$employee->id}/transactions"), ['type' => 'advance', 'amount' => 250], $this->as($this->owner))->assertCreated();

        $this->getJson($this->url('reports/cash-flow'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.cash_in', 800)
            ->assertJsonPath('data.cash_out.refunds', 200)
            ->assertJsonPath('data.cash_out.expenses', 100)
            ->assertJsonPath('data.cash_out.employee_advances', 250)
            ->assertJsonPath('data.cash_out.total', 550)
            ->assertJsonPath('data.net', 250);
    }

    public function test_debts_report_sorted_by_amount_with_vehicle_breakdown(): void
    {
        $small = $this->customer('صغير');
        $big = $this->customer('كبير');
        $this->invoice($this->vehicle($small), '100');
        $this->invoice($this->vehicle($big), '300');
        $this->invoice($this->vehicle($big), '400');

        $this->getJson($this->url('reports/debts'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.0.customer.name', 'كبير')
            ->assertJsonPath('data.0.total', 700)
            ->assertJsonCount(2, 'data.0.vehicles')
            ->assertJsonPath('data.1.total', 100)
            ->assertJsonPath('pagination.total', 2);

        $this->getJson($this->url('reports/debts?sort=oldest'), $this->as($this->owner))->assertJsonPath('data.0.customer.name', 'صغير');
    }

    public function test_service_due_lists_vehicles_from_their_latest_invoice(): void
    {
        $due = $this->vehicle();
        $later = $this->vehicle();
        $superseded = $this->vehicle();

        $this->serviceVisit($due, '2026-10-20');
        $this->serviceVisit($later, '2026-12-01');
        $this->serviceVisit($superseded, '2026-10-16');
        $this->serviceVisit($superseded, '2027-04-01');

        $this->getJson($this->url('reports/service-due?days=7'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle.id', $due->id)
            ->assertJsonPath('data.0.days_left', 5);
    }

    public function test_reports_export_to_excel(): void
    {
        $this->invoice($this->vehicle(), '100');

        foreach (['sales', 'debts', 'profit', 'service-due'] as $report) {
            $this->get($this->url("reports/{$report}?format=excel"), $this->as($this->owner))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    private function expense(string $amount, string $method = 'cash'): void
    {
        $this->inTenant($this->tenant);

        $this->postJson($this->url('expenses'), [
            'category_id' => ExpenseCategory::query()->firstOrFail()->id,
            'amount' => $amount,
            'method' => $method,
        ], $this->as($this->owner))->assertCreated();
    }

    private function serviceVisit(Vehicle $vehicle, string $nextServiceDate): void
    {
        $order = $this->openOrder($vehicle, [['name' => 'زيت', 'qty' => 1, 'unit_price' => 100]], ['next_service_date' => $nextServiceDate]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner))->assertOk();
        $this->travel(1)->minutes();
    }
}
