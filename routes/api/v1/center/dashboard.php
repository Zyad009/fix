<?php

use App\Http\Controllers\V1\Center\AuditLogController;
use App\Http\Controllers\V1\Center\CategoryController;
use App\Http\Controllers\V1\Center\CustomerController;
use App\Http\Controllers\V1\Center\EmployeeController;
use App\Http\Controllers\V1\Center\ExpenseCategoryController;
use App\Http\Controllers\V1\Center\ExpenseController;
use App\Http\Controllers\V1\Center\LookupController;
use App\Http\Controllers\V1\Center\OrderController;
use App\Http\Controllers\V1\Center\ProductController;
use App\Http\Controllers\V1\Center\ReceiptController;
use App\Http\Controllers\V1\Center\ReportController;
use App\Http\Controllers\V1\Center\SettingsController;
use App\Http\Controllers\V1\Center\UserController;
use App\Http\Controllers\V1\Center\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'branch', 'subscription'])->group(function (): void {
    // Center settings & users
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');

    Route::apiResource('users', UserController::class)->except('destroy');
    Route::toggleActive('users', UserController::class);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Lookups
    Route::get('search', [LookupController::class, 'search'])->name('search');
    Route::get('car-makes', [LookupController::class, 'carMakes'])->name('car-makes.index');
    Route::get('options', [LookupController::class, 'options'])->name('options');

    // Customers & vehicles
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/balance', [CustomerController::class, 'balance'])->name('customers.balance');
    Route::post('customers/{customer}/payments', [CustomerController::class, 'collect'])->name('customers.payments');

    Route::apiResource('vehicles', VehicleController::class);
    Route::patch('vehicles/{vehicle}/transfer-owner', [VehicleController::class, 'transferOwner'])->name('vehicles.transfer-owner');
    Route::get('vehicles/{vehicle}/history', [VehicleController::class, 'history'])->name('vehicles.history');
    Route::post('vehicles/{vehicle}/payments', [VehicleController::class, 'collect'])->name('vehicles.payments');
    Route::post('vehicles/{vehicle}/opening-balance', [VehicleController::class, 'openingBalance'])->name('vehicles.opening-balance');

    // Catalog (mini POS)
    Route::get('pos/catalog', [ProductController::class, 'catalog'])->name('pos.catalog');
    Route::apiResource('categories', CategoryController::class);
    Route::toggleActive('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::toggleActive('products', ProductController::class);
    Route::post('products/{product}/image', [ProductController::class, 'image'])->name('products.image');
    Route::patch('products/{product}/stock', [ProductController::class, 'stock'])->name('products.stock');

    // Orders (= invoices)
    Route::apiResource('orders', OrderController::class)->except('destroy');
    Route::prefix('orders/{order}')->name('orders.')->controller(OrderController::class)->group(function (): void {
        Route::post('items', 'addItem')->name('items.store');
        Route::put('items/{item}', 'updateItem')->name('items.update')->scopeBindings();
        Route::delete('items/{item}', 'removeItem')->name('items.destroy')->scopeBindings();
        Route::post('complete', 'complete')->name('complete');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::post('payments', 'pay')->name('payments');
        Route::get('print', 'print')->name('print');
    });

    // Receipts
    Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::patch('receipts/{receipt}/void', [ReceiptController::class, 'void'])->name('receipts.void');
    Route::get('receipts/{receipt}/print', [ReceiptController::class, 'print'])->name('receipts.print');

    // Employees & payroll
    Route::get('employees/technicians', [EmployeeController::class, 'technicians'])->name('employees.technicians');
    Route::get('payroll/preview', [EmployeeController::class, 'payrollPreview'])->name('payroll.preview');
    Route::post('payroll/issue', [EmployeeController::class, 'payrollIssue'])->name('payroll.issue');
    Route::apiResource('employees', EmployeeController::class)->except('destroy');
    Route::toggleActive('employees', EmployeeController::class);
    Route::get('employees/{employee}/statement', [EmployeeController::class, 'statement'])->name('employees.statement');
    Route::post('employees/{employee}/transactions', [EmployeeController::class, 'storeTransaction'])->name('employees.transactions.store');
    Route::patch('employee-transactions/{transaction}/void', [EmployeeController::class, 'voidTransaction'])->name('employee-transactions.void');

    // Expenses
    Route::apiResource('expense-categories', ExpenseCategoryController::class)->except('show');
    Route::toggleActive('expense-categories', ExpenseCategoryController::class);
    Route::get('expenses/summary', [ExpenseController::class, 'summary'])->name('expenses.summary');
    Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'show']);
    Route::patch('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');

    // Reports (owner)
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function (): void {
        Route::get('today', 'today')->name('today');
        Route::get('sales', 'sales')->name('sales');
        Route::get('debts', 'debts')->name('debts');
        Route::get('profit', 'profit')->name('profit');
        Route::get('cash-flow', 'cashFlow')->name('cash-flow');
        Route::get('service-due', 'serviceDue')->name('service-due');
    });
});
