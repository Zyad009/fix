<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\ReportRequest;
use App\Http\Resources\V1\Center\OrderResource;
use App\Models\Order;
use App\Services\V1\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller implements HasMiddleware
{
    public function __construct(protected ReportService $reportService) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:reports.view')];
    }

    public function today(): JsonResponse
    {
        try {
            return $this->response($this->reportService->today());
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function sales(ReportRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            $report = $this->reportService->sales($request->validated());

            if ($this->wantsExcel($request)) {
                return $this->excel('sales', ['التاريخ', 'عدد الفواتير', 'المبيعات'], array_map(
                    fn (array $day): array => [$day['date'], $day['orders_count'], $day['total']],
                    $report['daily'],
                ));
            }

            return $this->response($report);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function debts(ReportRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            $debts = $this->reportService->debts($request->validated());

            if ($this->wantsExcel($request)) {
                $rows = [];

                foreach ($debts->items() as $debt) {
                    foreach ($debt['vehicles'] as $vehicle) {
                        $rows[] = [$debt['customer']['name'], $debt['customer']['phone'], $vehicle['plate_number'], $vehicle['balance'], $debt['total']];
                    }
                }

                return $this->excel('debts', ['العميل', 'التليفون', 'اللوحة', 'مديونية العربية', 'إجمالي العميل'], $rows);
            }

            return $this->response($debts->items(), pagination: self::formatPagination($debts));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function profit(ReportRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            $report = $this->reportService->profit($request->validated());

            if ($this->wantsExcel($request)) {
                return $this->excel('profit', ['البند', 'القيمة'], [
                    ['المبيعات', $report['sales']],
                    ['تكلفة البضاعة', $report['cost_of_goods']],
                    ['مجمل الربح', $report['gross_profit']],
                    ['المصروفات', $report['expenses']],
                    ['المرتبات', $report['payroll']],
                    ['صافي الربح', $report['net_profit']],
                ]);
            }

            return $this->response($report);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function cashFlow(ReportRequest $request): JsonResponse
    {
        try {
            return $this->response($this->reportService->cashFlow($request->validated()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function serviceDue(ReportRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            $due = $this->reportService->serviceDue($request->validated());

            if ($this->wantsExcel($request)) {
                return $this->excel('service-due', ['اللوحة', 'العميل', 'التليفون', 'موعد الصيانة', 'عداد الصيانة', 'أيام متبقية'], array_map(
                    fn (Order $order): array => [
                        $order->vehicle?->plate_number,
                        $order->customer?->name,
                        $order->customer?->phone,
                        $order->next_service_date?->toDateString(),
                        $order->next_service_odometer,
                        $order->getAttribute('days_left'),
                    ],
                    $due->items(),
                ));
            }

            return $this->paginate($due, OrderResource::class);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
