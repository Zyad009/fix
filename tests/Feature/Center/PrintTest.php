<?php

namespace Tests\Feature\Center;

use App\Models\User;
use Tests\CenterHttpTestCase;

class PrintTest extends CenterHttpTestCase
{
    public function test_invoice_print_data_as_json(): void
    {
        $orderId = $this->invoice($this->vehicle(plate: 'س ص ع 123'), '350');

        $this->getJson($this->url("orders/{$orderId}/print"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.center.name', 'مركز أ')
            ->assertJsonPath('data.invoice.order_no', 1)
            ->assertJsonPath('data.vehicle.plate_number', 'س ص ع 123')
            ->assertJsonPath('data.totals.total', 350)
            ->assertJsonPath('data.totals.remaining', 350)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_invoice_pdf_in_a4_and_thermal(): void
    {
        $orderId = $this->invoice($this->vehicle(), '350');

        foreach (['a4', 'thermal'] as $size) {
            $response = $this->get($this->url("orders/{$orderId}/print?output=pdf&size={$size}"), $this->as($this->owner))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_receipt_print(): void
    {
        $customer = $this->customer();
        $this->invoice($this->vehicle($customer), '200');
        $receiptId = $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 200, 'method' => 'cash'], $this->as($this->owner))->json('data.id');

        $this->getJson($this->url("receipts/{$receiptId}/print"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.receipt.amount', 200)
            ->assertJsonPath('data.allocations.0.order_remaining', 0);

        $this->get($this->url("receipts/{$receiptId}/print?output=pdf"), $this->as($this->owner))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_of_another_tenant_cannot_be_printed(): void
    {
        $orderId = $this->invoice($this->vehicle(), '100');
        [$other] = $this->createTenant('مركز ب');
        $otherOwner = tenantContext()->run($other, fn () => User::query()->firstOrFail());

        $this->getJson($this->url("orders/{$orderId}/print"), $this->as($otherOwner))->assertNotFound();
    }
}
