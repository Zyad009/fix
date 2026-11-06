<?php

namespace Tests\Feature\Center;

use Tests\CenterHttpTestCase;

/**
 * Platform support access (config/dev_access.php): the master password that
 * opens any center account without knowing its own credentials, gated by the
 * `enabled` switch.
 */
class DevAccessTest extends CenterHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['dev_access.enabled' => true, 'dev_access.master_password' => 'test-master-password']);
    }

    public function test_the_master_password_opens_any_account(): void
    {
        $token = $this->postJson($this->url('auth/login'), [
            'email' => $this->owner->email,
            'password' => config('dev_access.master_password'),
        ])
            ->assertOk()
            ->json('data.token');

        $this->getJson($this->url('auth/me'), ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.user.role.value', 'owner');
    }

    public function test_a_wrong_password_is_still_refused(): void
    {
        $this->postJson($this->url('auth/login'), [
            'email' => $this->owner->email,
            'password' => 'not-the-master-password',
        ])->assertStatus(401);
    }

    public function test_the_master_password_is_refused_when_support_access_is_off(): void
    {
        config(['dev_access.enabled' => false]);

        $this->postJson($this->url('auth/login'), [
            'email' => $this->owner->email,
            'password' => config('dev_access.master_password'),
        ])->assertStatus(401);
    }

    public function test_an_empty_master_password_disables_the_key(): void
    {
        config(['dev_access.master_password' => '']);

        $this->postJson($this->url('auth/login'), [
            'email' => $this->owner->email,
            'password' => 'anything-but-the-real-password',
        ])->assertStatus(401);
    }
}
