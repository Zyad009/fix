<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum WhatsappMessageType: string
{
    use EnumTrait;

    case Invoice = 'invoice';
    case Payment = 'payment';
    case Test = 'test';

    public const LABELS = [
        'invoice' => ['ar' => 'فاتورة', 'en' => 'Invoice'],
        'payment' => ['ar' => 'إيصال سداد', 'en' => 'Payment receipt'],
        'test' => ['ar' => 'رسالة تجربة', 'en' => 'Test message'],
    ];
}
