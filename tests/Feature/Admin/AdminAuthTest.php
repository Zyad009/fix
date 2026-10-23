<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Tests\CenterHttpTestCase;

class AdminAuthTest extends CenterHttpTestCase
{
    public function test_admin_can_log_in_and_read_profile(): void
    {
        $admin = Admin::factory()->create(['email' => 'root@dawar.test']);

        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@dawar.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.admin.id', $admin->id)
            ->json('data.token');

        $this->getJson('/api/v1/admin/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.email', 'root@dawar.test');
    }

    public function test_wrong_password_is_rejected(): void
    {
        Admin::factory()->create(['email' => 'root@dawar.test']);

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@dawar.test', 'password' => 'nope'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        Admin::factory()->create(['email' => 'root@dawar.test', 'is_active' => false]);

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@dawar.test', 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_center_token_cannot_reach_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/tenants', $this->as($this->owner))->assertUnauthorized();
    }

    public function test_admin_token_cannot_reach_center_routes(): void
    {
        $this->getJson($this->url('settings'), $this->asAdmin())->assertUnauthorized();
    }
}
