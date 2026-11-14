<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum WhatsappMessageStatus: string
{
    use EnumTrait;

    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case SkippedDisabled = 'skipped_disabled';
    case SkippedNoDevice = 'skipped_no_device';
    case SkippedInvalidPhone = 'skipped_invalid_phone';
    case SkippedNoDebt = 'skipped_no_debt';
    case SkippedOrderCancelled = 'skipped_order_cancelled';

    public const LABELS = [
        'queued' => ['ar' => 'قيد الإرسال', 'en' => 'Queued'],
        'sent' => ['ar' => 'أُرسلت', 'en' => 'Sent'],
        'failed' => ['ar' => 'فشلت', 'en' => 'Failed'],
        'skipped_disabled' => ['ar' => 'لم تُرسل: الرسالة موقوفة', 'en' => 'Skipped: message disabled'],
        'skipped_no_device' => ['ar' => 'لم تُرسل: واتساب غير مربوط', 'en' => 'Skipped: WhatsApp not connected'],
        'skipped_invalid_phone' => ['ar' => 'لم تُرسل: رقم غير صالح', 'en' => 'Skipped: invalid phone'],
        'skipped_no_debt' => ['ar' => 'لم تُرسل: المديونية اتسددت قبل الإرسال', 'en' => 'Skipped: debt settled before sending'],
        'skipped_order_cancelled' => ['ar' => 'لم تُرسل: الفاتورة اتلغت قبل الإرسال', 'en' => 'Skipped: invoice cancelled before sending'],
    ];
}
