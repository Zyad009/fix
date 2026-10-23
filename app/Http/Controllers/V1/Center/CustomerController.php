<?php

namespace App\Http\Controllers\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\CustomerRequest;
use App\Http\Requests\V1\Center\IndexCustomerRequest;
use App\Http\Requests\V1\Center\PaymentRequest;
use App\Http\Resources\V1\Center\CustomerResource;
use App\Http\Resources\V1\Center\ReceiptResource;
use App\Models\Customer;
use App\Services\V1\BalanceService;
use App\Services\V1\CustomerService;
use App\Services\V1\PaymentService;
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
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show', 'balance']),
            new Middleware('permission:customers.manage', only: ['store', 'update', 'destroy']),
            new Middleware('permission:payments.collect', only: ['collect']),
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
     * Lump-sum debt collection spread over the customer's invoices, oldest first.
     */
    public function collect(PaymentRequest $request, Customer $customer): JsonResponse
    {
        try {
            $receipt = $this->paymentService->collectDebt(
                $customer,
                $request->validated('vehicle_id'),
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('notes'),
            );

            return $this->response(new ReceiptResource($receipt), __('messages.payment_recorded'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
