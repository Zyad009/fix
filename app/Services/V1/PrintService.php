<?php

namespace App\Services\V1;

use App\Enums\ProductKind;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Tenant;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Printable invoices and receipts. The same data feeds the JSON endpoint
 * (the frontend renders/prints it) and the server-side PDF (A4 or 80mm
 * thermal, Arabic RTL) for sharing.
 */
class PrintService
{
    public const SIZE_A4 = 'a4';

    public const SIZE_THERMAL = 'thermal';

    public function __construct(private readonly OrderService $orders) {}

    /**
     * @return array<string, mixed>
     */
    public function invoiceData(Order $order): array
    {
        $order = $this->orders->load($order);
        $tenant = tenantContext()->tenant();

        return [
            'center' => $this->centerData($tenant, $order->branch?->name),
            'invoice' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status->response(),
                'is_opening_balance' => $order->is_opening_balance,
                'created_at' => $this->localTime($order->created_at),
                'completed_at' => $this->localTime($order->completed_at),
                'odometer' => $order->odometer,
                'next_service_odometer' => $order->next_service_odometer,
                'next_service_date' => $order->next_service_date?->toDateString(),
                'technician' => $order->technician?->name,
                'cashier' => $order->creator?->name,
                'notes' => $order->notes,
            ],
            'customer' => ['name' => $order->customer?->name, 'phone' => $order->customer?->phone],
            'vehicle' => [
                'plate_number' => $order->vehicle?->plate_number,
                'make' => $order->vehicle?->carMake?->name_ar,
                'model' => $order->vehicle?->carModel?->name_ar,
                'year' => $order->vehicle?->year,
                'color' => $order->vehicle?->color,
            ],
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->name,
                'qty' => formatNumber($item->qty),
                'unit_price' => formatNumber($item->unit_price),
                'line_total' => Money::of($item->unit_price)->multipliedBy($item->qty)->toFloat(),
            ])->all(),
            'totals' => [
                'subtotal' => formatNumber($order->subtotal),
                'parts_total' => $order->kindTotal(ProductKind::Part)->toFloat(),
                'services_total' => $order->kindTotal(ProductKind::Service)->toFloat(),
                'discount' => formatNumber($order->discount),
                'total' => formatNumber($order->total),
                'paid' => formatNumber($order->paid),
                'remaining' => $order->remaining()->toFloat(),
            ],
            'payments' => $order->payments->filter(fn ($payment) => ! $payment->isVoided())->map(fn ($payment): array => [
                'type' => $payment->type->response(),
                'method' => $payment->method->response(),
                'amount' => formatNumber($payment->amount),
                'receipt_no' => $payment->receipt?->receipt_no,
                'paid_at' => $this->localTime($payment->paid_at),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptData(PaymentReceipt $receipt): array
    {
        $receipt->load(['branch:id,name', 'customer:id,name,phone', 'vehicle:id,plate_number', 'receiver:id,name', 'payments.order:id,order_no,total,paid']);

        return [
            'center' => $this->centerData(tenantContext()->tenant(), $receipt->branch?->name),
            'receipt' => [
                'id' => $receipt->id,
                'receipt_no' => $receipt->receipt_no,
                'amount' => formatNumber($receipt->amount),
                'method' => $receipt->method->response(),
                'received_at' => $this->localTime($receipt->received_at),
                'received_by' => $receipt->receiver?->name,
                'notes' => $receipt->notes,
                'is_voided' => $receipt->isVoided(),
            ],
            'customer' => ['name' => $receipt->customer?->name, 'phone' => $receipt->customer?->phone],
            'vehicle' => ['plate_number' => $receipt->vehicle?->plate_number],
            'allocations' => $receipt->payments->map(fn ($payment): array => [
                'order_no' => $payment->order?->order_no,
                'amount' => formatNumber($payment->amount),
                'order_remaining' => $payment->order ? $payment->order->remaining()->toFloat() : null,
            ])->values()->all(),
        ];
    }

    /**
     * Render a print view to a PDF binary string.
     *
     * @param  array<string, mixed>  $data
     */
    public function pdf(string $view, array $data, string $size): string
    {
        $tempDir = storage_path('app/mpdf-temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $thermal = $size === self::SIZE_THERMAL;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $thermal ? [80, $this->thermalHeight($data)] : 'A4',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font_size' => $thermal ? 8 : 10,
            'margin_top' => $thermal ? 3 : 12,
            'margin_bottom' => $thermal ? 3 : 12,
            'margin_left' => $thermal ? 3 : 12,
            'margin_right' => $thermal ? 3 : 12,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML(view($view, [...$data, 'size' => $size])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return array<string, mixed>
     */
    private function centerData(Tenant $tenant, ?string $branchName): array
    {
        $logoPath = $tenant->logo_path && Storage::disk(config('filesystems.default'))->exists($tenant->logo_path)
            ? Storage::disk(config('filesystems.default'))->path($tenant->logo_path)
            : null;

        return [
            'name' => $tenant->name,
            'branch' => $branchName,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'logo_url' => $tenant->logoUrl(),
            'logo_path' => $logoPath,
        ];
    }

    private function localTime(?CarbonInterface $time): ?string
    {
        return $time?->copy()->setTimezone(tenantContext()->tenant()->timezone)->format('Y-m-d H:i');
    }

    /**
     * Roll length in mm for the thermal printer: grows with the line count.
     *
     * @param  array<string, mixed>  $data
     */
    private function thermalHeight(array $data): int
    {
        $lines = count($data['items'] ?? []) + count($data['payments'] ?? []) + count($data['allocations'] ?? []);

        return 120 + ($lines * 8);
    }
}
