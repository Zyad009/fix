<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum PaymentType: string
{
    use EnumTrait;

    case Payment = 'payment';
    case Refund = 'refund';

    public const LABELS = [
        'payment' => ['ar' => 'تحصيل', 'en' => 'Payment'],
        'refund' => ['ar' => 'مرتجع', 'en' => 'Refund'],
    ];
}
