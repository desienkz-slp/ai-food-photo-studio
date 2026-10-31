<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_session(): void
    {
        $this->getJson('/session')->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_minimal_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/session')->assertOk()->assertExactJson([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function test_guest_cannot_list_workspaces(): void
    {
        $this->getJson('/workspaces')->assertUnauthorized();
    }

    public function test_user_lists_only_member_workspaces_with_minimal_data(): void
    {
        $user = User::factory()->create();
        $member = Workspace::factory()->create();
        Workspace::factory()->create();
        $member->users()->attach($user, ['role' => 'member']);

        $this->actingAs($user)->getJson('/workspaces')->assertOk()->assertExactJson([
            'data' => [['id' => $member->id, 'name' => $member->name]],
        ]);
    }

    public function test_user_without_memberships_gets_empty_workspace_list(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create();
        $this->actingAs($user)->getJson('/workspaces')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_csrf_bootstrap_is_public_and_uses_web_middleware(): void
    {
        $this->getJson('/csrf-cookie')->assertNoContent();
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/csrf-cookie'));
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
