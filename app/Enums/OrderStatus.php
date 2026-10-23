<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum OrderStatus: string
{
    use EnumTrait;

    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public const LABELS = [
        'open' => ['ar' => 'مفتوح', 'en' => 'Open'],
        'completed' => ['ar' => 'مقفول (فاتورة)', 'en' => 'Completed'],
        'cancelled' => ['ar' => 'ملغي', 'en' => 'Cancelled'],
    ];
}
