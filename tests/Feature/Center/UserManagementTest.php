<?php

namespace Tests\Feature\Center;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CenterHttpTestCase;

class UserManagementTest extends CenterHttpTestCase
{
    public function test_owner_creates_a_user_with_a_username(): void
    {
        $this->postJson($this->url('users'), [
            'name' => 'مالك تاني',
            'username' => 'second_owner',
            'email' => 'second@a.test',
            'password' => 'secret123',
            'role' => 'owner',
        ], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.username', 'second_owner');

        $this->assertDatabaseHas('users', ['email' => 'second@a.test', 'username' => 'second_owner']);
    }

    public function test_username_is_optional(): void
    {
        $this->postJson($this->url('users'), [
            'name' => 'مالك تاني',
            'email' => 'second@a.test',
            'password' => 'secret123',
            'role' => 'owner',
        ], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.username', null);
    }

    public function test_owner_updates_username_and_email_and_the_new_ones_log_in(): void
    {
        $cashier = $this->cashier();

        $this->putJson($this->url("users/{$cashier->id}"), [
            'username' => 'new_cashier',
            'email' => 'new-cashier@a.test',
        ], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.username', 'new_cashier')
            ->assertJsonPath('data.email', 'new-cashier@a.test');

        $this->postJson($this->url('auth/login'), ['username' => 'new_cashier', 'password' => 'password'])->assertOk();
        $this->postJson($this->url('auth/login'), ['email' => 'new-cashier@a.test', 'password' => 'password'])->assertOk();
    }

    public function test_updating_a_user_can_keep_or_clear_their_username(): void
    {
        $cashier = $this->cashier();
        $cashier->update(['username' => 'same_name']);
        $headers = $this->as($this->owner);

        $this->putJson($this->url("users/{$cashier->id}"), ['username' => 'same_name'], $headers)->assertOk();

        $this->putJson($this->url("users/{$cashier->id}"), ['name' => 'اسم جديد'], $headers)
            ->assertOk()
            ->assertJsonPath('data.username', 'same_name');

        $this->putJson($this->url("users/{$cashier->id}"), ['username' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.username', null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUsernames(): array
    {
        return [
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 51)],
            'has a space' => ['two words'],
            'arabic' => ['مستخدم'],
            'looks like an email' => ['user@a.test'],
        ];
    }

    #[DataProvider('invalidUsernames')]
    public function test_invalid_username_is_rejected(string $username): void
    {
        $this->putJson($this->url("users/{$this->owner->id}"), ['username' => $username], $this->as($this->owner))
            ->assertStatus(422);
    }

    public function test_username_must_be_unique_across_centers_in_any_letter_case(): void
    {
        [, $otherOwner] = $this->createTenant('مركز ب');
        $otherOwner->update(['username' => 'taken_name']);
        $cashier = $this->cashier();
        $headers = $this->as($this->owner);

        $this->putJson($this->url("users/{$cashier->id}"), ['username' => 'taken_name'], $headers)->assertStatus(422);
        $this->putJson($this->url("users/{$cashier->id}"), ['username' => 'TAKEN_NAME'], $headers)->assertStatus(422);
    }

    public function test_user_of_another_center_cannot_be_updated(): void
    {
        [, $otherOwner] = $this->createTenant('مركز ب');

        $this->putJson($this->url("users/{$otherOwner->id}"), ['username' => 'hijacked'], $this->as($this->owner))
            ->assertNotFound();

        $this->assertDatabaseMissing('users', ['username' => 'hijacked']);
    }

    public function test_users_can_be_searched_by_username(): void
    {
        $cashier = $this->cashier();
        $cashier->update(['username' => 'findable_name']);

        $this->getJson($this->url('users?q=findable'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cashier->id);
    }
}
