<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Platform data needed in every environment. Local/demo data lives in
     * DemoSeeder (`php artisan db:seed --class=DemoSeeder`).
     */
    public function run(): void
    {
        $this->call(CarCatalogSeeder::class);

        Admin::query()->firstOrCreate(
            ['email' => config('dawar.admin.email')],
            ['name' => 'Super Admin', 'password' => config('dawar.admin.password')],
        );
    }
}
