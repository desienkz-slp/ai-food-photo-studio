<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'member']);

        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}")->assertOk()
            ->assertJsonPath('data.id', $workspace->id);
    }

    public function test_user_cannot_view_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}")->assertForbidden();
    }

    public function test_guest_cannot_view_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $this->getJson("/workspaces/{$workspace->id}")->assertUnauthorized();
    }
}
