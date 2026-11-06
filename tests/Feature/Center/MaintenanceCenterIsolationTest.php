<?php

namespace Tests\Feature\Center;

use App\Exceptions\MissingMaintenanceCenterContextException;
use App\Models\Branch;
use App\Models\User;
use Tests\CenterHttpTestCase;

/**
 * brief §4.1: a user of center A can never read or change center B's data,
 * even by guessing IDs or smuggling maintenance_center_id into the payload.
 */
class MaintenanceCenterIsolationTest extends CenterHttpTestCase
{
    public function test_ids_of_another_maintenance_center_resolve_to_not_found(): void
    {
        [$otherMaintenanceCenter, $otherOwner] = $this->createMaintenanceCenter('مركز ب');

        $this->getJson($this->url("users/{$otherOwner->id}"), $this->as($this->owner))->assertNotFound();
        $this->putJson($this->url("users/{$otherOwner->id}"), ['name' => 'hacked'], $this->as($this->owner))->assertNotFound();
        $this->patchJson($this->url("users/toggle/{$otherOwner->id}"), [], $this->as($this->owner))->assertNotFound();

        $this->assertSame('مالك مركز ب', $otherOwner->fresh()->name);
        $this->assertNotSame($this->maintenanceCenter->id, $otherMaintenanceCenter->id);
    }

    public function test_maintenance_center_id_in_the_payload_is_ignored(): void
    {
        [$otherMaintenanceCenter, , $otherBranch] = $this->createMaintenanceCenter('مركز ب');

        $response = $this->postJson($this->url('users'), [
            'maintenance_center_id' => $otherMaintenanceCenter->id,
            'name' => 'كاشير',
            'email' => 'cashier@a.test',
            'password' => 'secret123',
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ], $this->as($this->owner))->assertCreated();

        $this->assertSame($this->maintenanceCenter->id, User::query()->withoutGlobalScope('maintenance_center')->findOrFail($response->json('data.id'))->maintenance_center_id);
        $this->assertNotSame($otherBranch->id, $this->branch->id);
    }

    public function test_branch_of_another_maintenance_center_cannot_be_assigned(): void
    {
        [, , $otherBranch] = $this->createMaintenanceCenter('مركز ب');

        $this->postJson($this->url('users'), [
            'name' => 'كاشير',
            'email' => 'cashier@a.test',
            'password' => 'secret123',
            'role' => 'cashier',
            'branch_id' => $otherBranch->id,
        ], $this->as($this->owner))->assertStatus(422);
    }

    public function test_owner_cannot_select_a_branch_of_another_maintenance_center(): void
    {
        [, , $otherBranch] = $this->createMaintenanceCenter('مركز ب');

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

    public function test_maintenance_center_scoped_queries_fail_closed_without_a_context(): void
    {
        maintenanceCenterContext()->set(null);

        $this->expectException(MissingMaintenanceCenterContextException::class);

        Branch::query()->count();
    }
}
