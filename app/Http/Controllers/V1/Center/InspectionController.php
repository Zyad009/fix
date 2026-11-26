<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\InspectionRequest;
use App\Http\Requests\V1\Center\PrintRequest;
use App\Http\Resources\V1\Center\InspectionResource;
use App\Models\Order;
use App\Services\V1\InspectionService;
use App\Services\V1\OrderGuard;
use App\Services\V1\PrintService;
use App\Services\V1\WhatsappNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InspectionController extends Controller implements HasMiddleware
{
    protected string $resource = InspectionResource::class;

    public function __construct(
        protected InspectionService $inspectionService,
        protected PrintService $printService,
        protected OrderGuard $guard,
        protected WhatsappNotifier $notifier,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['checklist', 'show', 'print']),
            new Middleware('permission:orders.inspect', only: ['update', 'destroy', 'whatsapp']),
            new Middleware('throttle:10,1', only: ['whatsapp']),
        ];
    }

    public function checklist(): JsonResponse
    {
        try {
            return $this->response($this->inspectionService->checklist());
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Order $order): JsonResponse
    {
        try {
            $this->guard->ensureVisible($order);
            $inspection = $this->inspectionService->find($order);

            return $this->response($inspection ? new $this->resource($inspection) : null);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(InspectionRequest $request, Order $order): JsonResponse
    {
        try {
            $this->guard->ensureVisible($order);
            $inspection = $this->inspectionService->save($order, $request->validated());

            return $this->response(new $this->resource($inspection), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Order $order): JsonResponse
    {
        try {
            $this->guard->ensureVisible($order);
            $this->inspectionService->destroy($order);

            return $this->response(null, __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function print(PrintRequest $request, Order $order): JsonResponse|Response
    {
        try {
            $this->guard->ensureVisible($order);
            $data = $this->printService->inspectionData($order, $this->inspectionService->findOrFail($order));

            if ($request->validated('output') !== 'pdf') {
                return $this->response($data);
            }

            return response($this->printService->pdf('pdf.inspection', $data, PrintService::SIZE_A4), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="inspection-'.$order->order_no.'.pdf"',
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Send the inspection report PDF to the customer on WhatsApp.
     */
    public function whatsapp(Order $order): JsonResponse
    {
        try {
            $this->guard->ensureVisible($order);
            $this->notifier->sendInspection($order);

            return $this->response([], __('messages.whatsapp_queued'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
