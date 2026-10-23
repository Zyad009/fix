<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum ProductKind: string
{
    use EnumTrait;

    case Part = 'part';
    case Service = 'service';

    public const LABELS = [
        'part' => ['ar' => 'قطعة', 'en' => 'Part'],
        'service' => ['ar' => 'خدمة', 'en' => 'Service'],
    ];
}
