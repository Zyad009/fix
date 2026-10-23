<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum AuditEvent: string
{
    use EnumTrait;

    case Created = 'created';
    case Updated = 'updated';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
    case Toggled = 'toggled';
    case StockAdjusted = 'stock_adjusted';
    case OwnerTransferred = 'owner_transferred';
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';

    public const LABELS = [
        'created' => ['ar' => 'إنشاء', 'en' => 'Created'],
        'updated' => ['ar' => 'تعديل', 'en' => 'Updated'],
        'cancelled' => ['ar' => 'إلغاء', 'en' => 'Cancelled'],
        'voided' => ['ar' => 'إبطال', 'en' => 'Voided'],
        'toggled' => ['ar' => 'تفعيل / إيقاف', 'en' => 'Toggled'],
        'stock_adjusted' => ['ar' => 'تعديل مخزون', 'en' => 'Stock adjusted'],
        'owner_transferred' => ['ar' => 'نقل ملكية', 'en' => 'Owner transferred'],
        'item_added' => ['ar' => 'إضافة بند', 'en' => 'Item added'],
        'item_updated' => ['ar' => 'تعديل بند', 'en' => 'Item updated'],
        'item_removed' => ['ar' => 'حذف بند', 'en' => 'Item removed'],
    ];
}
