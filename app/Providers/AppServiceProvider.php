<?php

namespace App\Providers;

use App\Models;
use App\Support\Tenancy\BranchContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(BranchContext::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'admin' => Models\Admin::class,
            'user' => Models\User::class,
            'tenant' => Models\Tenant::class,
            'branch' => Models\Branch::class,
            'customer' => Models\Customer::class,
            'vehicle' => Models\Vehicle::class,
            'category' => Models\Category::class,
            'product' => Models\Product::class,
            'employee' => Models\Employee::class,
            'employee_transaction' => Models\EmployeeTransaction::class,
            'order' => Models\Order::class,
            'order_item' => Models\OrderItem::class,
            'payment_receipt' => Models\PaymentReceipt::class,
            'payment' => Models\Payment::class,
            'expense_category' => Models\ExpenseCategory::class,
            'expense' => Models\Expense::class,
        ]);
    }
}
