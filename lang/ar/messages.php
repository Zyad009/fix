<?php

return [
    // General
    'main_message_success' => 'تمت العملية بنجاح',
    'main_message_error' => 'حدث خطأ ما، برجاء المحاولة لاحقاً',
    'unauthenticated' => 'غير مصرح لك. يجب تسجيل الدخول أولاً.',
    'unauthorized' => 'ليس لديك صلاحية للقيام بهذا الإجراء.',
    'not_found' => 'العنصر غير موجود.',
    'too_many_requests' => 'محاولات كثيرة، برجاء المحاولة بعد قليل.',
    'at_least_one_field' => 'يجب إرسال حقل واحد على الأقل.',
    'created' => 'تمت الإضافة بنجاح',
    'updated' => 'تم التعديل بنجاح',
    'deleted' => 'تم الحذف بنجاح',
    'status_changed' => 'تم تغيير الحالة بنجاح',
    'already_voided' => 'تم إبطال هذه العملية من قبل.',
    'voided' => 'تم الإبطال بنجاح',

    // Auth & tenancy
    'login_success' => 'تم تسجيل الدخول بنجاح',
    'logout_success' => 'تم تسجيل الخروج بنجاح',
    'invalid_credentials' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    'account_inactive' => 'هذا الحساب موقوف، برجاء التواصل مع الإدارة.',
    'password_changed' => 'تم تغيير كلمة المرور بنجاح.',
    'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
    'branch_required' => 'برجاء اختيار الفرع أولاً.',
    'branch_forbidden' => 'لا يمكنك العمل على هذا الفرع.',
    'subscription_expired' => 'انتهى اشتراك المركز. يمكنك عرض البيانات فقط حتى يتم التجديد.',
    'cannot_modify_self' => 'لا يمكنك تغيير دورك أو إيقاف حسابك.',
    'staff_branch_required' => 'يجب تحديد فرع للكاشير والفني.',
    'default_branch_locked' => 'لا يمكن إيقاف الفرع الرئيسي.',
    'default_branch_name' => 'الفرع الرئيسي',

    // Customers & vehicles
    'customer_has_debt' => 'لا يمكن حذف عميل عليه مديونية.',
    'vehicle_has_debt' => 'لا يمكن حذف عربية عليها مديونية.',
    'car_model_mismatch' => 'الموديل لا يتبع الماركة المختارة.',
    'same_owner' => 'العربية مسجلة بالفعل باسم هذا العميل.',
    'owner_transferred' => 'تم نقل ملكية العربية بنجاح.',

    // Catalog
    'category_in_use' => 'لا يمكن حذف تصنيف يحتوي على منتجات.',
    'product_inactive' => 'المنتج غير متاح للبيع.',
    'stock_adjusted' => 'تم تعديل المخزون بنجاح.',

    // Orders
    'order_cancelled_locked' => 'هذا الأوردر ملغي ولا يمكن تعديله.',
    'order_not_open' => 'هذا الأوردر ليس مفتوحاً.',
    'order_completed_locked' => 'الفاتورة مقفولة، والتعديل عليها للمالك فقط.',
    'order_has_no_items' => 'لا يمكن قفل أوردر بدون بنود.',
    'discount_exceeds_subtotal' => 'الخصم لا يمكن أن يزيد عن إجمالي البنود.',
    'discount_exceeds_limit' => 'أقصى خصم مسموح لك هو :limit جنيه.',
    'discount_forbidden' => 'ليس لديك صلاحية عمل خصم.',
    'price_override_forbidden' => 'ليس لديك صلاحية تعديل سعر البند.',
    'total_below_paid' => 'إجمالي الفاتورة لا يمكن أن يقل عن المدفوع (:paid جنيه). أبطل الدفعة أولاً.',
    'odometer_lower_than_last' => 'قراءة العداد (:reading) أقل من آخر قراءة مسجلة (:last).',
    'stock_insufficient' => 'رصيد ":product" في المخزن أصبح :qty.',
    'technician_invalid' => 'الفني المختار غير متاح في هذا الفرع.',
    'order_completed' => 'تم قفل الأوردر وتحويله لفاتورة.',
    'order_cancelled' => 'تم إلغاء الأوردر.',
    'cancel_reason_required' => 'سبب الإلغاء مطلوب لإلغاء فاتورة مقفولة.',
    'opening_balance_locked' => 'لا يمكن تعديل أوردر رصيد افتتاحي.',
    'opening_balance_item' => 'رصيد افتتاحي',
    'opening_balance_created' => 'تم تسجيل الرصيد الافتتاحي.',

    // Payments
    'payment_recorded' => 'تم تسجيل الدفعة بنجاح.',
    'amount_exceeds_due' => 'المبلغ أكبر من المتبقي على الأوردر (:due جنيه).',
    'amount_exceeds_debt' => 'المبلغ أكبر من إجمالي المديونية (:debt جنيه).',
    'no_debt' => 'لا توجد مديونية للتحصيل.',
    'receipt_has_cancelled_order' => 'لا يمكن إبطال إيصال يخص أوردر ملغي (تم رد المبلغ بالفعل).',

    // Employees & payroll
    'salary_already_issued' => 'تم إصدار مرتب هذا الموظف لهذا الشهر من قبل.',
    'period_required' => 'الشهر مطلوب لحركة المرتب.',
    'payroll_issued' => 'تم إصدار مرتبات :count موظف.',
    'employee_inactive' => 'الموظف موقوف.',

    // Expenses
    'expense_category_in_use' => 'لا يمكن حذف تصنيف مستخدم في مصروفات.',
];
