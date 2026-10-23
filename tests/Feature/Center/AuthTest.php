<?php

namespace Tests\Feature\Center;

use App\Models\User;
use Tests\CenterHttpTestCase;

class AuthTest extends CenterHttpTestCase
{
    public function test_owner_logs_in_and_gets_profile_branches_and_permissions(): void
    {
        $this->postJson($this->url('auth/login'), ['email' => $this->owner->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user', 'tenant', 'branches', 'permissions']])
            ->assertJsonPath('data.user.role.value', 'owner')
            ->assertJsonPath('data.tenant.name', 'مركز أ')
            ->assertJsonPath('data.branches.0.id', $this->branch->id)
            ->assertJsonFragment(['reports.view']);
    }

    public function test_cashier_profile_lists_only_their_branch_and_permissions(): void
    {
        $this->createBranch('فرع 2');
        $cashier = $this->cashier();

        $this->getJson($this->url('auth/me'), $this->as($cashier))
            ->assertOk()
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.id', $this->branch->id)
            ->assertJsonMissing(['reports.view']);
    }

    public function test_wrong_credentials(): void
    {
        $this->postJson($this->url('auth/login'), ['email' => $this->owner->email, 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJson(['status' => false, 'msg' => __('messages.invalid_credentials')]);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $cashier = $this->cashier();
        $cashier->update(['is_active' => false]);

        $this->postJson($this->url('auth/login'), ['email' => $cashier->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_inactive');
    }

    public function test_logout_revokes_the_token(): void
    {
        $headers = $this->as($this->owner);

        $this->postJson($this->url('auth/logout'), [], $headers)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url('auth/me'), $headers)->assertUnauthorized();
    }

    public function test_change_password(): void
    {
        $this->putJson($this->url('auth/password'), [
            'current_password' => 'password',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ], $this->as($this->owner))->assertOk();

        $this->postJson($this->url('auth/login'), ['email' => $this->owner->email, 'password' => 'new-secret-1'])->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson($this->url('settings'))
            ->assertUnauthorized()
            ->assertJson(['status' => false, 'msg' => __('messages.unauthenticated')]);
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url('auth/login'), ['email' => $this->owner->email, 'password' => 'wrong']);
        }

        $this->postJson($this->url('auth/login'), ['email' => $this->owner->email, 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_password_is_never_exposed(): void
    {
        $this->getJson($this->url('users/'.$this->owner->id), $this->as($this->owner))
            ->assertOk()
            ->assertJsonMissingPath('data.password');

        $this->assertInstanceOf(User::class, $this->owner);
    }
}
