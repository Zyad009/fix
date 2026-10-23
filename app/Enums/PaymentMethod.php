<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

/**
 * The single list of payment methods used by payments, receipts and expenses.
 */
enum PaymentMethod: string
{
    use EnumTrait;

    case Cash = 'cash';
    case Instapay = 'instapay';
    case Wallet = 'wallet';
    case Card = 'card';
    case Bank = 'bank';

    public const LABELS = [
        'cash' => ['ar' => 'كاش', 'en' => 'Cash'],
        'instapay' => ['ar' => 'إنستاباي', 'en' => 'InstaPay'],
        'wallet' => ['ar' => 'محفظة إلكترونية', 'en' => 'E-wallet'],
        'card' => ['ar' => 'كارت', 'en' => 'Card'],
        'bank' => ['ar' => 'تحويل بنكي', 'en' => 'Bank transfer'],
    ];
}
