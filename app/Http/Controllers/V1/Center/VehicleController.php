<?php

namespace App\Http\Controllers\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexVehicleRequest;
use App\Http\Requests\V1\Center\OpeningBalanceRequest;
use App\Http\Requests\V1\Center\PaymentRequest;
use App\Http\Requests\V1\Center\TransferOwnerRequest;
use App\Http\Requests\V1\Center\VehicleRequest;
use App\Http\Requests\V1\ListRequest;
use App\Http\Resources\V1\Center\OrderResource;
use App\Http\Resources\V1\Center\ReceiptResource;
use App\Http\Resources\V1\Center\VehicleResource;
use App\Models\Vehicle;
use App\Services\V1\OpeningBalanceService;
use App\Services\V1\PaymentService;
use App\Services\V1\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleController extends Controller implements HasMiddleware
{
    protected string $resource = VehicleResource::class;

    public function __construct(
        protected VehicleService $vehicleService,
        protected PaymentService $paymentService,
        protected OpeningBalanceService $openingBalanceService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show', 'history']),
            new Middleware('permission:customers.manage', only: ['store', 'update', 'destroy', 'transferOwner']),
            new Middleware('permission:payments.collect', only: ['collect']),
            new Middleware('permission:opening_balances.manage', only: ['openingBalance']),
        ];
    }

    public function index(IndexVehicleRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            if ($this->wantsExcel($request)) {
                $rows = $this->vehicleService->query($request->validated())->get()
                    ->map(fn (Vehicle $vehicle): array => [
                        $vehicle->plate_number,
                        $vehicle->carMake?->name_ar,
                        $vehicle->carModel?->name_ar,
                        $vehicle->year,
                        $vehicle->customer?->name,
                        $vehicle->customer?->phone,
                        $vehicle->last_odometer,
                        $vehicle->last_visit_at?->toDateString(),
                    ])->all();

                return $this->excel('vehicles', ['اللوحة', 'الماركة', 'الموديل', 'السنة', 'المالك', 'التليفون', 'آخر عداد', 'آخر زيارة'], $rows);
            }

            return $this->paginate($this->vehicleService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        try {
            $vehicle = $this->vehicleService->store($request->validated());

            return $this->response(new $this->resource($this->vehicleService->show($vehicle)), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->vehicleService->show($vehicle)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->vehicleService->update($vehicle, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        try {
            $this->vehicleService->destroy($vehicle);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function transferOwner(TransferOwnerRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->vehicleService->transferOwner($vehicle, (int) $request->validated('customer_id'))), __('messages.owner_transferred'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Full service history (every visit with its odometer reading).
     */
    public function history(ListRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            return $this->paginate($this->vehicleService->history($vehicle, $request->validated()), OrderResource::class);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Collect the current owner's debt on this vehicle, oldest invoices first.
     */
    public function collect(PaymentRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $receipt = $this->paymentService->collectDebt(
                $vehicle->customer()->firstOrFail(),
                $vehicle->id,
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('notes'),
            );

            return $this->response(new ReceiptResource($receipt), __('messages.payment_recorded'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function openingBalance(OpeningBalanceRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $order = $this->openingBalanceService->create($vehicle, (string) $request->validated('amount'), $request->validated('notes'));

            return $this->response(new OrderResource($order), __('messages.opening_balance_created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
