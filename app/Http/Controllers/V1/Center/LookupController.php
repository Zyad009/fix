<?php

namespace App\Http\Controllers\V1\Center;

use App\Enums\EmployeeTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductKind;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\SearchRequest;
use App\Http\Resources\V1\CarMakeResource;
use App\Http\Resources\V1\Center\CustomerResource;
use App\Http\Resources\V1\Center\VehicleResource;
use App\Services\V1\CarCatalogService;
use App\Services\V1\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Quick search and the static option lists the frontend needs.
 */
class LookupController extends Controller implements HasMiddleware
{
    public function __construct(
        protected SearchService $searchService,
        protected CarCatalogService $catalogService,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:customers.view', only: ['search'])];
    }

    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $result = $this->searchService->search($request->validated('q'));

            return $this->response([
                'vehicles' => VehicleResource::collection($result['vehicles']),
                'customers' => CustomerResource::collection($result['customers']),
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function carMakes(): JsonResponse
    {
        try {
            return $this->response(CarMakeResource::collection($this->catalogService->list()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function options(): JsonResponse
    {
        return $this->response([
            'payment_methods' => PaymentMethod::options(),
            'order_statuses' => OrderStatus::options(),
            'product_kinds' => ProductKind::options(),
            'roles' => UserRole::options(),
            'employee_transaction_types' => EmployeeTransactionType::options(),
        ]);
    }
}
