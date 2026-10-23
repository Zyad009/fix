<?php

return [
    // General
    'main_message_success' => 'Operation completed successfully.',
    'main_message_error' => 'Something went wrong, please try again later.',
    'unauthenticated' => 'Unauthenticated. Please log in first.',
    'unauthorized' => 'You are not allowed to perform this action.',
    'not_found' => 'Record not found.',
    'too_many_requests' => 'Too many attempts, please try again shortly.',
    'at_least_one_field' => 'At least one field must be submitted.',
    'created' => 'Created successfully.',
    'updated' => 'Updated successfully.',
    'deleted' => 'Deleted successfully.',
    'status_changed' => 'Status changed successfully.',
    'already_voided' => 'This record was already voided.',
    'voided' => 'Voided successfully.',

    // Auth & tenancy
    'login_success' => 'Logged in successfully.',
    'logout_success' => 'Logged out successfully.',
    'invalid_credentials' => 'Invalid email or password.',
    'account_inactive' => 'This account is suspended. Please contact support.',
    'password_changed' => 'Password changed successfully.',
    'current_password_incorrect' => 'The current password is incorrect.',
    'branch_required' => 'Please select a branch first.',
    'branch_forbidden' => 'You cannot work on this branch.',
    'subscription_expired' => 'The subscription has expired. Data is read-only until it is renewed.',
    'cannot_modify_self' => 'You cannot change your own role or deactivate yourself.',
    'staff_branch_required' => 'Cashiers and technicians must be assigned to a branch.',
    'default_branch_locked' => 'The default branch cannot be deactivated.',
    'default_branch_name' => 'Main branch',

    // Customers & vehicles
    'customer_has_debt' => 'A customer with outstanding debt cannot be deleted.',
    'vehicle_has_debt' => 'A vehicle with outstanding debt cannot be deleted.',
    'car_model_mismatch' => 'The model does not belong to the selected make.',
    'same_owner' => 'The vehicle already belongs to this customer.',
    'owner_transferred' => 'Vehicle ownership transferred successfully.',

    // Catalog
    'category_in_use' => 'A category that has products cannot be deleted.',
    'product_inactive' => 'This product is not available for sale.',
    'stock_adjusted' => 'Stock adjusted successfully.',

    // Orders
    'order_cancelled_locked' => 'This order is cancelled and cannot be changed.',
    'order_not_open' => 'This order is not open.',
    'order_completed_locked' => 'The invoice is closed; only the owner can change it.',
    'order_has_no_items' => 'An order without items cannot be completed.',
    'discount_exceeds_subtotal' => 'The discount cannot exceed the items subtotal.',
    'discount_exceeds_limit' => 'Your maximum allowed discount is :limit EGP.',
    'discount_forbidden' => 'You are not allowed to give discounts.',
    'price_override_forbidden' => 'You are not allowed to change item prices.',
    'total_below_paid' => 'The invoice total cannot drop below the paid amount (:paid EGP). Void the payment first.',
    'odometer_lower_than_last' => 'Odometer reading (:reading) is lower than the last recorded reading (:last).',
    'stock_insufficient' => 'Stock of ":product" is now :qty.',
    'technician_invalid' => 'The selected technician is not available in this branch.',
    'order_completed' => 'Order completed and turned into an invoice.',
    'order_cancelled' => 'Order cancelled.',
    'cancel_reason_required' => 'A reason is required to cancel a completed invoice.',
    'opening_balance_locked' => 'Opening-balance orders cannot be changed.',
    'opening_balance_item' => 'Opening balance',
    'opening_balance_created' => 'Opening balance recorded.',

    // Payments
    'payment_recorded' => 'Payment recorded successfully.',
    'amount_exceeds_due' => 'The amount exceeds what is due on the order (:due EGP).',
    'amount_exceeds_debt' => 'The amount exceeds the total debt (:debt EGP).',
    'no_debt' => 'There is no debt to collect.',
    'receipt_has_cancelled_order' => 'A receipt covering a cancelled order cannot be voided (it was already refunded).',

    // Employees & payroll
    'salary_already_issued' => 'This employee\'s salary for this month was already issued.',
    'period_required' => 'The month is required for a salary entry.',
    'payroll_issued' => 'Salaries issued for :count employees.',
    'employee_inactive' => 'The employee is inactive.',

    // Expenses
    'expense_category_in_use' => 'A category used by expenses cannot be deleted.',
];
