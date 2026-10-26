<?php

namespace App\Http\Controllers\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\CancelRequest;
use App\Http\Requests\V1\Center\CompleteOrderRequest;
use App\Http\Requests\V1\Center\IndexOrderRequest;
use App\Http\Requests\V1\Center\OrderItemRequest;
use App\Http\Requests\V1\Center\OrderRequest;
use App\Http\Requests\V1\Center\PaymentRequest;
use App\Http\Requests\V1\Center\PrintRequest;
use App\Http\Resources\V1\Center\OrderResource;
use App\Http\Resources\V1\Center\ReceiptResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\V1\OrderGuard;
use App\Services\V1\OrderItemService;
use App\Services\V1\OrderService;
use App\Services\V1\PaymentService;
use App\Services\V1\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller implements HasMiddleware
{
    protected string $resource = OrderResource::class;

    public function __construct(
        protected OrderService $orderService,
        protected OrderItemService $itemService,
        protected PaymentService $paymentService,
        protected PrintService $printService,
        protected OrderGuard $guard,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['index', 'show', 'print']),
            new Middleware('permission:orders.create', only: ['store', 'update', 'addItem', 'updateItem', 'removeItem', 'cancel']),
            new Middleware('permission:orders.complete', only: ['complete']),
            new Middleware('permission:payments.collect', only: ['pay']),
        ];
    }

    public function index(IndexOrderRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            if ($this->wantsExcel($request)) {
                $rows = $this->orderService->query($request->validated())->get()
                    ->map(fn (Order $order): array => [
                        $order->order_no,
                        $order->status->label(),
                        $order->vehicle?->plate_number,
                        $order->customer?->name,
                        formatNumber($order->total),
                        formatNumber($order->paid),
                        $order->remaining()->toFloat(),
                        $order->created_at?->toDateTimeString(),
                    ])->all();

                return $this->excel('orders', ['رقم الفاتورة', 'الحالة', 'اللوحة', 'العميل', 'الإجمالي', 'المدفوع', 'المتبقي', 'التاريخ'], $rows);
            }

            return $this->paginate($this->orderService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(OrderRequest $request): JsonResponse
    {
        try {
            $result = $this->orderService->create($request->validated());
            $message = $result['order']->isCompleted() ? __('messages.order_completed') : __('messages.created');

            return $this->withWarnings($result, $message, 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Order $order): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->orderService->show($order)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        try {
            return $this->withWarnings($this->orderService->update($order, $request->validated()), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function addItem(OrderItemRequest $request, Order $order): JsonResponse
    {
        try {
            $result = $this->itemService->add($order, $request->validated());

            return $this->withWarnings(['order' => $this->orderService->load($result['order']), 'warnings' => $result['warnings']], __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function updateItem(OrderItemRequest $request, Order $order, OrderItem $item): JsonResponse
    {
        try {
            $result = $this->itemService->update($order, $item, $request->validated());

            return $this->withWarnings(['order' => $this->orderService->load($result['order']), 'warnings' => $result['warnings']], __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        try {
            $order = $this->itemService->remove($order, $item);

            return $this->response(new $this->resource($this->orderService->load($order)), __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function complete(CompleteOrderRequest $request, Order $order): JsonResponse
    {
        try {
            return $this->withWarnings($this->orderService->complete($order, $request->validated('payments', [])), __('messages.order_completed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function cancel(CancelRequest $request, Order $order): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->orderService->cancel($order, $request->validated('reason'))), __('messages.order_cancelled'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Collect money against this order (deposit on an open order, or invoice payment).
     */
    public function pay(PaymentRequest $request, Order $order): JsonResponse
    {
        try {
            $receipt = $this->paymentService->payOrder(
                $order,
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('notes'),
            );

            return $this->response(new ReceiptResource($receipt), __('messages.payment_recorded'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function print(PrintRequest $request, Order $order): JsonResponse|Response
    {
        try {
            $this->guard->ensureVisible($order);
            $data = $this->printService->invoiceData($order);

            if ($request->validated('output') !== 'pdf') {
                return $this->response($data);
            }

            $pdf = $this->printService->pdf('pdf.invoice', $data, $request->validated('size', PrintService::SIZE_A4));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="invoice-'.$order->order_no.'.pdf"',
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * @param  array{order: Order, warnings: list<string>}  $result
     */
    private function withWarnings(array $result, string $message, int $status = 200): JsonResponse
    {
        return $this->response([
            'order' => new $this->resource($result['order']),
            'warnings' => $result['warnings'],
        ], $message, $status);
    }
}
