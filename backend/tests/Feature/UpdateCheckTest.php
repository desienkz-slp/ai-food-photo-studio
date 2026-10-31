<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        return [$user, $workspace];
    }

    public function test_owner_can_check_fixed_github_release_read_only(): void
    {
        [$user, $workspace] = $this->owner();
        config(['release.version' => '1.1.0']);
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/repos/desienkz-slp/ai-food-photo-studio/releases/latest' => Http::response([
            'tag_name' => 'v1.2.0', 'name' => 'release', 'body' => 'private notes',
        ])]);

        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")
            ->assertOk()->assertJson(['update_available' => true, 'latest_version' => '1.2.0'])
            ->assertJsonMissing(['body' => 'private notes'])->assertJsonMissingPath('release_url');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/desienkz-slp/ai-food-photo-studio/releases/latest' && $request->method() === 'GET');
    }

    public function test_missing_or_invalid_build_version_fails_closed(): void
    {
        [$user, $workspace] = $this->owner();
        Http::fake(['*' => Http::response(['tag_name' => 'v1.2.0'])]);
        foreach ([null, '', '1.2', 'not-a-version'] as $version) {
            config(['release.version' => $version]);
            $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")->assertStatus(503);
        }
        Http::assertNothingSent();
    }

    public function test_build_version_controls_comparison(): void
    {
        [$user, $workspace] = $this->owner();
        config(['release.version' => 'v2.0.0']);
        Http::fake(['*' => Http::response(['tag_name' => 'v1.2.0'])]);
        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")
            ->assertOk()->assertJson(['update_available' => false, 'latest_version' => '1.2.0']);
    }

    public function test_oversized_release_body_is_rejected_with_streaming_enabled(): void
    {
        [$user, $workspace] = $this->owner();
        config(['release.version' => '1.0.0']);
        $options = [];
        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options = $requestOptions;
            return Http::response(str_repeat('x', 65537));
        });
        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")->assertStatus(503);
        $this->assertTrue($options['stream'] ?? false);
    }

    public function test_update_checks_are_limited_to_ten_per_minute(): void
    {
        [$user, $workspace] = $this->owner();
        config(['release.version' => '1.0.0']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['tag_name' => 'v1.1.0']));

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")->assertOk();
        }
        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")->assertTooManyRequests();
        Http::assertSentCount(10);
    }

    public function test_admin_can_check_updates(): void
    {
        [$admin, $workspace] = $this->owner();
        $workspace->users()->updateExistingPivot($admin->id, ['role' => 'admin']);
        config(['release.version' => '1.0.0']);
        Http::fake(['*' => Http::response(['tag_name' => 'v1.1.0'])]);

        $this->actingAs($admin)->getJson("/workspaces/{$workspace->id}/updates")
            ->assertOk()->assertJson(['update_available' => true, 'latest_version' => '1.1.0']);
    }

    public function test_authorized_user_cannot_check_updates_for_another_workspace(): void
    {
        [$owner, $workspace] = $this->owner();
        $otherWorkspace = Workspace::factory()->create();
        config(['release.version' => '1.0.0']);
        Http::fake(['*' => Http::response(['tag_name' => 'v1.1.0'])]);

        $this->actingAs($owner)->getJson("/workspaces/{$otherWorkspace->id}/updates")
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_member_and_guest_are_denied(): void
    {
        [$owner, $workspace] = $this->owner();
        $member = User::factory()->create(); $workspace->users()->attach($member, ['role' => 'member']);
        Http::preventStrayRequests();
        Http::fake();
        $this->actingAs($member)->getJson("/workspaces/{$workspace->id}/updates")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_guest_is_denied(): void
    {
        $workspace = Workspace::factory()->create();
        Http::preventStrayRequests();
        Http::fake();
        $this->getJson("/workspaces/{$workspace->id}/updates")->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_invalid_release_tag_fails_safe_without_metadata(): void
    {
        [$user, $workspace] = $this->owner();
        Http::fake(['*' => Http::response(['tag_name' => 'not-a-version', 'html_url' => 'https://secret'])]);
        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")
            ->assertStatus(503)->assertJson(['update_available' => false])->assertJsonMissing(['html_url' => 'https://secret']);
    }

    public function test_network_failure_fails_safe(): void
    {
        [$user, $workspace] = $this->owner(); Http::fake(fn () => Http::failedConnection());
        $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/updates")
            ->assertStatus(503)->assertJson(['update_available' => false]);
    }
}
