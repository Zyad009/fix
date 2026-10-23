<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum EmployeeTransactionType: string
{
    use EnumTrait;

    case Salary = 'salary';
    case Bonus = 'bonus';
    case Deduction = 'deduction';
    case Advance = 'advance';
    case Payment = 'payment';

    public const LABELS = [
        'salary' => ['ar' => 'مرتب', 'en' => 'Salary'],
        'bonus' => ['ar' => 'حافز', 'en' => 'Bonus'],
        'deduction' => ['ar' => 'خصم / جزاء', 'en' => 'Deduction'],
        'advance' => ['ar' => 'سلفة', 'en' => 'Advance'],
        'payment' => ['ar' => 'قبض', 'en' => 'Payment'],
    ];

    /**
     * Credits increase what the center owes the employee.
     */
    public function isCredit(): bool
    {
        return in_array($this, [self::Salary, self::Bonus], true);
    }

    /**
     * Cash physically leaving the drawer (all employee money is cash).
     */
    public function isCashOut(): bool
    {
        return in_array($this, [self::Advance, self::Payment], true);
    }
}
