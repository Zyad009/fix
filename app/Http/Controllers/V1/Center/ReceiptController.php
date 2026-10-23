<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexReceiptRequest;
use App\Http\Requests\V1\Center\PrintRequest;
use App\Http\Requests\V1\Center\VoidRequest;
use App\Http\Resources\V1\Center\ReceiptResource;
use App\Models\PaymentReceipt;
use App\Services\V1\PaymentService;
use App\Services\V1\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReceiptController extends Controller implements HasMiddleware
{
    protected string $resource = ReceiptResource::class;

    public function __construct(
        protected PaymentService $paymentService,
        protected PrintService $printService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:payments.collect', only: ['index', 'show', 'print']),
            new Middleware('permission:payments.void', only: ['void']),
        ];
    }

    public function index(IndexReceiptRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->paymentService->listReceipts($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(PaymentReceipt $receipt): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->paymentService->loadReceipt($receipt)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function void(VoidRequest $request, PaymentReceipt $receipt): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->paymentService->voidReceipt($receipt, $request->validated('reason'))), __('messages.voided'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function print(PrintRequest $request, PaymentReceipt $receipt): JsonResponse|Response
    {
        try {
            $data = $this->printService->receiptData($receipt);

            if ($request->validated('output') !== 'pdf') {
                return $this->response($data);
            }

            $pdf = $this->printService->pdf('pdf.receipt', $data, $request->validated('size', PrintService::SIZE_THERMAL));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="receipt-'.$receipt->receipt_no.'.pdf"',
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
