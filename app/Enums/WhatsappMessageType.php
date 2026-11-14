<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum WhatsappMessageType: string
{
    use EnumTrait;

    case Invoice = 'invoice';
    case InvoiceUpdated = 'invoice_updated';
    case Payment = 'payment';
    case Test = 'test';
    case ServiceReminder = 'service_reminder';
    case DebtReminder = 'debt_reminder';

    public const LABELS = [
        'invoice' => ['ar' => 'فاتورة', 'en' => 'Invoice'],
        'invoice_updated' => ['ar' => 'تعديل فاتورة', 'en' => 'Invoice update'],
        'payment' => ['ar' => 'إيصال سداد', 'en' => 'Payment receipt'],
        'test' => ['ar' => 'رسالة تجربة', 'en' => 'Test message'],
        'service_reminder' => ['ar' => 'تذكير بمعاد الصيانة', 'en' => 'Service reminder'],
        'debt_reminder' => ['ar' => 'تذكير بالمديونية', 'en' => 'Debt reminder'],
    ];
}
