<?php

namespace App\Http\Controllers\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\CustomerPaymentRequest;
use App\Http\Requests\V1\Center\CustomerRequest;
use App\Http\Requests\V1\Center\CustomerStatementRequest;
use App\Http\Requests\V1\Center\IndexCustomerRequest;
use App\Http\Resources\V1\Center\CustomerResource;
use App\Http\Resources\V1\Center\OrderResource;
use App\Http\Resources\V1\Center\ReceiptResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\V1\BalanceService;
use App\Services\V1\CustomerService;
use App\Services\V1\CustomerStatementService;
use App\Services\V1\OrderService;
use App\Services\V1\PaymentService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller implements HasMiddleware
{
    protected string $resource = CustomerResource::class;

    public function __construct(
        protected CustomerService $customerService,
        protected BalanceService $balanceService,
        protected PaymentService $paymentService,
        protected CustomerStatementService $statementService,
        protected OrderService $orderService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show', 'balance']),
            new Middleware('permission:customers.manage', only: ['store', 'update', 'destroy']),
            new Middleware('permission:orders.view_closed', only: ['statement', 'showOrder']),
            new Middleware('permission:payments.collect', only: ['collect', 'unpaidOrders']),
        ];
    }

    public function index(IndexCustomerRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            if ($this->wantsExcel($request)) {
                $rows = $this->customerService->query($request->validated())->get()
                    ->map(fn (Customer $customer): array => [$customer->name, $customer->phone, $customer->vehicles_count, formatNumber((string) $customer->balance)])
                    ->all();

                return $this->excel('customers', ['الاسم', 'التليفون', 'عدد العربيات', 'المديونية'], $rows);
            }

            return $this->paginate($this->customerService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->customerService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Customer $customer): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->customerService->show($customer)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->customerService->update($customer, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $this->customerService->destroy($customer);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function balance(Customer $customer): JsonResponse
    {
        try {
            return $this->response($this->balanceService->customerDebts($customer->id));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Account statement: invoices, collections and the running balance.
     */
    public function statement(CustomerStatementRequest $request, Customer $customer): JsonResponse|BinaryFileResponse
    {
        try {
            $statement = $this->statementService->statement($customer, $request->validated());

            if ($this->wantsExcel($request)) {
                $rows = array_map(fn (array $entry): array => [
                    CarbonImmutable::createFromTimestamp($entry['date'], tenantContext()->tenant()->timezone)->format('Y-m-d H:i'),
                    $entry['type']['label'],
                    $entry['reference'],
                    $entry['vehicle_plate'],
                    $entry['method']['label'] ?? null,
                    $entry['debit'],
                    $entry['credit'],
                    $entry['balance'],
                ], $statement['entries']);

                return $this->excel('statement-'.$customer->id, ['التاريخ', 'الحركة', 'الرقم', 'اللوحة', 'طريقة الدفع', 'عليه', 'له', 'الرصيد'], $rows);
            }

            return $this->response($statement);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Invoices that still owe money, every branch, oldest first.
     */
    public function unpaidOrders(CustomerStatementRequest $request, Customer $customer): JsonResponse
    {
        try {
            $orders = $this->paymentService->unpaidOrders($customer->id, $request->validated('vehicle_id'));

            return $this->response([
                'total_due' => Money::sum($orders->map(fn (Order $order): Money => $order->remaining()))->toFloat(),
                'orders' => OrderResource::collection($orders),
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * One of the customer's invoices, whichever branch issued it.
     */
    public function showOrder(Customer $customer, int $orderId): JsonResponse
    {
        try {
            $order = Order::query()->withoutGlobalScope('branch')
                ->where('customer_id', $customer->id)
                ->findOrFail($orderId);

            return $this->response(new OrderResource($this->orderService->load($order)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Collect from the customer page: oldest-first over the total debt, or a
     * manual split over chosen invoices (one invoice = pay that invoice).
     */
    public function collect(CustomerPaymentRequest $request, Customer $customer): JsonResponse
    {
        try {
            $method = PaymentMethod::from($request->validated('method'));
            $amount = (string) $request->validated('amount');

            $receipt = $request->validated('allocation') === CustomerPaymentRequest::ALLOCATION_MANUAL
                ? $this->paymentService->collectAllocated($customer, $request->validated('orders'), $amount, $method, $request->validated('notes'))
                : $this->paymentService->collectDebt($customer, $request->validated('vehicle_id'), $amount, $method, $request->validated('notes'));

            return $this->response(new ReceiptResource($receipt), __('messages.payment_recorded'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
