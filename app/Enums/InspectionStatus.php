<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

/**
 * Result of one inspection item. An item with no stored result was not inspected.
 */
enum InspectionStatus: string
{
    use EnumTrait;

    case Ok = 'ok';
    case Attention = 'attention';
    case Urgent = 'urgent';

    public const LABELS = [
        'ok' => ['ar' => 'سليم', 'en' => 'OK'],
        'attention' => ['ar' => 'يحتاج متابعة', 'en' => 'Needs attention'],
        'urgent' => ['ar' => 'لازم يتغير', 'en' => 'Replace now'],
    ];
}
