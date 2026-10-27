<?php

namespace Tests\Feature\Admin;

use App\Models\CarMake;
use Database\Seeders\CarCatalogSeeder;
use Tests\CenterHttpTestCase;

class CarCatalogTest extends CenterHttpTestCase
{
    public function test_admin_curates_makes_and_models_and_centers_read_active_ones(): void
    {
        $admin = $this->asAdmin();

        $makeId = $this->postJson('/api/v1/admin/car-makes', ['name_ar' => 'تسلا', 'name_en' => 'Tesla'], $admin)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/car-makes', ['name_ar' => 'تسلا'], $admin)->assertStatus(422);

        $modelId = $this->postJson("/api/v1/admin/car-makes/{$makeId}/models", ['name_ar' => 'موديل 3'], $admin)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/car-makes/{$makeId}/models", ['name_ar' => 'موديل Y'], $admin)->assertCreated();
        $this->patchJson("/api/v1/admin/car-models/toggle/{$modelId}", [], $admin)->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson($this->url('car-makes'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.0.name_ar', 'تسلا')
            ->assertJsonCount(1, 'data.0.models');

        $this->postJson('/api/v1/admin/car-makes', ['name_ar' => 'x'], $this->as($this->owner))->assertUnauthorized();
    }

    public function test_makes_carry_a_logo_for_the_frontend(): void
    {
        $admin = $this->asAdmin();
        $logo = 'https://cdn.example.com/tesla.png';

        $makeId = $this->postJson('/api/v1/admin/car-makes', ['name_ar' => 'تسلا', 'logo_url' => $logo], $admin)
            ->assertCreated()
            ->assertJsonPath('data.logo_url', $logo)
            ->json('data.id');

        $this->postJson('/api/v1/admin/car-makes', ['name_ar' => 'x', 'logo_url' => 'not-a-url'], $admin)->assertStatus(422);
        $this->putJson("/api/v1/admin/car-makes/{$makeId}", ['name_ar' => 'تسلا', 'logo_url' => null], $admin)->assertOk()->assertJsonPath('data.logo_url', null);

        $this->seed(CarCatalogSeeder::class);
        $this->inTenant($this->tenant);
        $vehicle = $this->vehicle();
        $vehicle->update(['car_make_id' => CarMake::query()->where('name_en', 'Mercedes-Benz')->value('id'), 'year' => 2020]);

        $this->getJson($this->url("vehicles/{$vehicle->id}"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.year', 2020)
            ->assertJsonPath('data.make.logo_url', fn (string $url): bool => str_ends_with($url, '/logos/optimized/mercedes-benz.png'));
    }
}
