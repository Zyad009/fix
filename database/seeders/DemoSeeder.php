<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\CarMake;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\V1\Admin\TenantProvisioningService;
use App\Services\V1\ExpenseService;
use App\Services\V1\OpeningBalanceService;
use App\Services\V1\OrderService;
use App\Services\V1\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A ready-to-try center for local development and demos:
 * `php artisan db:seed --class=DemoSeeder`.
 *
 * Logins (password: "password"): owner@dawar.test, cashier@dawar.test,
 * tech@dawar.test — and admin@dawar.test for /api/v1/admin.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        if (User::query()->withoutGlobalScope('tenant')->where('email', 'owner@dawar.test')->exists()) {
            $this->command?->warn('Demo center already exists — skipped.');

            return;
        }

        $tenant = app(TenantProvisioningService::class)->provision([
            'name' => 'مركز الدوار التجريبي',
            'phone' => '01000000000',
            'address' => 'القاهرة - مدينة نصر',
            'plan' => 'pro',
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'cashier_max_discount' => '100',
            'owner' => ['name' => 'صاحب المركز', 'email' => 'owner@dawar.test', 'password' => 'password'],
        ]);

        tenantContext()->run($tenant, fn () => $this->seedCenter($tenant));
    }

    private function seedCenter(Tenant $tenant): void
    {
        $main = Branch::query()->where('is_default', true)->firstOrFail();
        Branch::query()->create(['name' => 'فرع المعادي', 'address' => 'القاهرة - المعادي']);
        branchContext()->set($main);

        User::query()->create(['name' => 'كاشير الفرع', 'email' => 'cashier@dawar.test', 'password' => 'password', 'role' => UserRole::Cashier, 'branch_id' => $main->id]);
        $techUser = User::query()->create(['name' => 'فني الفرع', 'email' => 'tech@dawar.test', 'password' => 'password', 'role' => UserRole::Technician, 'branch_id' => $main->id]);

        $technician = Employee::query()->create(['name' => 'حسن الفني', 'user_id' => $techUser->id, 'job_title' => 'ميكانيكي', 'base_salary' => '6000', 'hired_at' => '2025-01-01']);
        Employee::query()->create(['name' => 'سعيد', 'job_title' => 'عامل غيار زيت', 'base_salary' => '4500', 'hired_at' => '2025-06-01']);

        $products = $this->seedCatalog($main);
        $vehicles = $this->seedCustomers();

        $orders = app(OrderService::class);

        foreach ($vehicles->take(6) as $index => $vehicle) {
            $result = $orders->create([
                'vehicle_id' => $vehicle->id,
                'technician_id' => $technician->id,
                'odometer' => 40000 + ($index * 7500),
                'next_service_odometer' => 45000 + ($index * 7500),
                'next_service_date' => now()->addDays(($index * 10) - 15)->toDateString(),
                'items' => [
                    ['product_id' => $products['زيت 5W30 - 4 لتر']->id, 'qty' => 1],
                    ['product_id' => $products['فلتر زيت']->id, 'qty' => 1],
                    ['product_id' => $products['مصنعية تغيير زيت']->id, 'qty' => 1],
                ],
            ]);

            $payments = match ($index % 3) {
                0 => [['amount' => '1190', 'method' => PaymentMethod::Cash->value]],
                1 => [['amount' => '500', 'method' => PaymentMethod::Instapay->value]],
                default => [],
            };

            $orders->complete($result['order'], $payments);
        }

        $orders->create(['vehicle_id' => $vehicles[6]->id, 'odometer' => 120000, 'items' => [['product_id' => $products['تيل فرامل أمامي']->id, 'qty' => 1]]]);

        app(OpeningBalanceService::class)->create($vehicles[7], '1500', 'رصيد قديم من الدفتر');

        $expenses = app(ExpenseService::class);
        $categories = ExpenseCategory::query()->pluck('id', 'name');
        $expenses->store(['category_id' => $categories['إيجار'], 'amount' => '8000', 'spent_at' => now()->startOfMonth()->toDateString()]);
        $expenses->store(['category_id' => $categories['كهرباء'], 'amount' => '950', 'method' => PaymentMethod::Instapay->value]);
        $expenses->store(['category_id' => $categories['نثريات'], 'amount' => '120']);
    }

    /**
     * @return array<string, Product>
     */
    private function seedCatalog(Branch $branch): array
    {
        $categories = Category::query()->pluck('id', 'name');
        $stock = app(StockService::class);

        $catalog = [
            ['زيوت', 'زيت 5W30 - 4 لتر', 'part', '850', '700', true, '40'],
            ['زيوت', 'زيت 10W40 - 4 لتر', 'part', '650', '520', true, '30'],
            ['فلاتر', 'فلتر زيت', 'part', '140', '90', true, '60'],
            ['فلاتر', 'فلتر هواء', 'part', '220', '150', true, '25'],
            ['كاوتش', 'كاوتش 195/65 R15', 'part', '2900', '2500', true, '12'],
            ['بطاريات', 'بطارية 70 أمبير', 'part', '3200', '2800', true, '6'],
            ['عمالة / مصنعية', 'مصنعية تغيير زيت', 'service', '200', null, false, null],
            ['عمالة / مصنعية', 'ضبط زوايا', 'service', '350', null, false, null],
            ['عمالة / مصنعية', 'تيل فرامل أمامي', 'part', '950', '700', true, '10'],
        ];

        $products = [];

        foreach ($catalog as [$category, $name, $kind, $price, $cost, $tracksStock, $qty]) {
            $products[$name] = Product::query()->create([
                'category_id' => $categories[$category],
                'name' => $name,
                'kind' => $kind,
                'sale_price' => $price,
                'cost_price' => $cost,
                'track_stock' => $tracksStock,
            ]);

            if ($qty !== null) {
                $stock->set($products[$name], $branch->id, $qty, 'رصيد أول المدة');
            }
        }

        return $products;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function seedCustomers(): Collection
    {
        $makes = CarMake::query()->with('models')->whereIn('name_ar', ['هيونداي', 'كيا', 'تويوتا', 'نيسان', 'شيفروليه'])->get();
        $names = ['أحمد محمود', 'محمد علي', 'سارة حسن', 'خالد إبراهيم', 'منى عبد الله', 'عمرو سمير', 'هبة مصطفى', 'ياسر فؤاد'];
        $vehicles = collect();

        foreach ($names as $index => $name) {
            $customer = Customer::query()->create(['name' => $name, 'phone' => '0101234567'.$index]);
            $make = $makes[$index % $makes->count()];

            $vehicles->push(Vehicle::query()->create([
                'customer_id' => $customer->id,
                'plate_number' => ['س ص ع', 'ب ج د', 'ر ق م', 'ط ه و', 'ل م ن', 'ف ك ع', 'ص د ق', 'ن ب ي'][$index].' '.(1000 + $index * 111),
                'car_make_id' => $make->id,
                'car_model_id' => $make->models->first()?->id,
                'year' => 2015 + $index,
                'color' => ['أبيض', 'أسود', 'فضي', 'أحمر'][$index % 4],
            ]));
        }

        return $vehicles;
    }
}
