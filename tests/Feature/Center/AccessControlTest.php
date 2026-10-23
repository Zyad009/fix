<?php

namespace Tests\Feature\Center;

use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CenterHttpTestCase;

class AccessControlTest extends CenterHttpTestCase
{
    public function test_only_owner_manages_users_and_settings(): void
    {
        $cashier = $this->cashier();
        $technician = $this->technician();

        $this->getJson($this->url('users'), $this->as($cashier))->assertForbidden();
        $this->putJson($this->url('settings'), ['name' => 'x'], $this->as($technician))->assertForbidden();
        $this->getJson($this->url('audit-logs'), $this->as($cashier))->assertForbidden();

        $this->getJson($this->url('settings'), $this->as($cashier))->assertOk();
    }

    public function test_expired_subscription_is_read_only(): void
    {
        $this->tenant->update(['subscription_ends_at' => now()->subDay()]);

        $this->getJson($this->url('settings'), $this->as($this->owner))->assertOk();

        $this->putJson($this->url('settings'), ['name' => 'جديد'], $this->as($this->owner))
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_expired');

        $this->postJson($this->url('auth/logout'), [], $this->as($this->owner))->assertOk();
    }

    public function test_owner_updates_settings_and_logo(): void
    {
        Storage::fake('public');

        $this->putJson($this->url('settings'), ['name' => 'مركز أ الجديد', 'cashier_max_discount' => 50], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.name', 'مركز أ الجديد')
            ->assertJsonPath('data.cashier_max_discount', 50);

        $path = $this->post($this->url('settings/logo'), ['image' => UploadedFile::fake()->image('logo.png')], $this->as($this->owner) + ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.logo_url');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($this->tenant->fresh()->logo_path);
    }

    public function test_user_management_rules(): void
    {
        $owner = $this->as($this->owner);

        $this->postJson($this->url('users'), [
            'name' => 'فني', 'email' => 'tech@a.test', 'password' => 'secret123', 'role' => 'technician',
        ], $owner)->assertStatus(422);

        $cashierId = $this->postJson($this->url('users'), [
            'name' => 'كاشير', 'email' => 'cash@a.test', 'password' => 'secret123', 'role' => 'cashier', 'branch_id' => $this->branch->id,
        ], $owner)->assertCreated()->assertJsonPath('data.branch.id', $this->branch->id)->json('data.id');

        $this->patchJson($this->url("users/toggle/{$this->owner->id}"), [], $owner)->assertStatus(422);
        $this->putJson($this->url("users/{$this->owner->id}"), ['role' => 'cashier'], $owner)->assertStatus(422);

        $this->patchJson($this->url("users/toggle/{$cashierId}"), [], $owner)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'user', 'auditable_id' => $cashierId, 'event' => 'toggled']);
        $this->assertSame(1, AuditLog::query()->count());

        $this->getJson($this->url('audit-logs?auditable_type=user'), $owner)
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $this->owner->id);
    }
}
