<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexWhatsappMessageRequest;
use App\Http\Requests\V1\Center\WhatsappResendRequest;
use App\Http\Requests\V1\Center\WhatsappTestRequest;
use App\Http\Resources\V1\Center\WhatsappMessageResource;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Services\V1\WhatsappNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WhatsappController extends Controller implements HasMiddleware
{
    public function __construct(protected WhatsappNotifier $notifier) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.complete', only: ['sendInvoice', 'resendInvoice']),
            new Middleware('permission:customers.manage', only: ['resendInvoice']),
            new Middleware('permission:payments.collect', only: ['sendReceipt']),
            new Middleware('permission:settings.manage', only: ['test', 'messages']),
            new Middleware('throttle:10,1', only: ['test', 'resendInvoice']),
        ];
    }

    public function sendInvoice(Order $order): JsonResponse
    {
        try {
            $this->notifier->resendInvoice($order);

            return $this->response([], __('messages.whatsapp_queued'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Correct the customer's wrong number, then send the invoice image again.
     */
    public function resendInvoice(WhatsappResendRequest $request, Order $order): JsonResponse
    {
        try {
            $this->notifier->correctPhoneAndResendInvoice($order, $request->validated('phone'));

            return $this->response([], __('messages.whatsapp_queued'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function sendReceipt(PaymentReceipt $receipt): JsonResponse
    {
        try {
            $this->notifier->resendReceipt($receipt);

            return $this->response([], __('messages.whatsapp_queued'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function test(WhatsappTestRequest $request): JsonResponse
    {
        try {
            $this->notifier->sendTest($request->validated('phone'));

            return $this->response([], __('messages.whatsapp_queued'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function messages(IndexWhatsappMessageRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->notifier->log($request->validated()), WhatsappMessageResource::class);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
