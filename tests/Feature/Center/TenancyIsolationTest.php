<?php

namespace Tests\Feature\Center;

use App\Exceptions\MissingTenantContextException;
use App\Models\Branch;
use App\Models\User;
use Tests\CenterHttpTestCase;

/**
 * brief §4.1: a user of center A can never read or change center B's data,
 * even by guessing IDs or smuggling tenant_id into the payload.
 */
class TenancyIsolationTest extends CenterHttpTestCase
{
    public function test_ids_of_another_tenant_resolve_to_not_found(): void
    {
        [$otherTenant, $otherOwner] = $this->createTenant('مركز ب');

        $this->getJson($this->url("users/{$otherOwner->id}"), $this->as($this->owner))->assertNotFound();
        $this->putJson($this->url("users/{$otherOwner->id}"), ['name' => 'hacked'], $this->as($this->owner))->assertNotFound();
        $this->patchJson($this->url("users/toggle/{$otherOwner->id}"), [], $this->as($this->owner))->assertNotFound();

        $this->assertSame('مالك مركز ب', $otherOwner->fresh()->name);
        $this->assertNotSame($this->tenant->id, $otherTenant->id);
    }

    public function test_tenant_id_in_the_payload_is_ignored(): void
    {
        [$otherTenant, , $otherBranch] = $this->createTenant('مركز ب');

        $response = $this->postJson($this->url('users'), [
            'tenant_id' => $otherTenant->id,
            'name' => 'كاشير',
            'email' => 'cashier@a.test',
            'password' => 'secret123',
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ], $this->as($this->owner))->assertCreated();

        $this->assertSame($this->tenant->id, User::query()->withoutGlobalScope('tenant')->findOrFail($response->json('data.id'))->tenant_id);
        $this->assertNotSame($otherBranch->id, $this->branch->id);
    }

    public function test_branch_of_another_tenant_cannot_be_assigned(): void
    {
        [, , $otherBranch] = $this->createTenant('مركز ب');

        $this->postJson($this->url('users'), [
            'name' => 'كاشير',
            'email' => 'cashier@a.test',
            'password' => 'secret123',
            'role' => 'cashier',
            'branch_id' => $otherBranch->id,
        ], $this->as($this->owner))->assertStatus(422);
    }

    public function test_owner_cannot_select_a_branch_of_another_tenant(): void
    {
        [, , $otherBranch] = $this->createTenant('مركز ب');

        $this->getJson($this->url('settings'), $this->as($this->owner, $otherBranch))
            ->assertForbidden()
            ->assertJsonPath('code', 'branch_forbidden');
    }

    public function test_staff_are_pinned_to_their_branch(): void
    {
        $secondBranch = $this->createBranch('فرع 2');
        $cashier = $this->cashier();

        $this->getJson($this->url('settings'), $this->as($cashier, $secondBranch))->assertForbidden();
        $this->getJson($this->url('settings'), $this->as($cashier, $this->branch))->assertOk();
    }

    public function test_tenant_scoped_queries_fail_closed_without_a_context(): void
    {
        tenantContext()->set(null);

        $this->expectException(MissingTenantContextException::class);

        Branch::query()->count();
    }
}
