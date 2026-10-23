<?php

namespace Tests\Feature\Admin;

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
}
