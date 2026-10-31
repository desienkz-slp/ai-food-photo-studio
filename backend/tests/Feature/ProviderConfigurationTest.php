<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_is_workspace_scoped_and_never_exposes_secrets(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'admin']);
        $other->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/providers", ['type' => 'openai-compatible', 'model' => 'm1', 'api_key' => 'private-one'])->assertCreated();
        $this->postJson("/workspaces/{$other->id}/providers", ['type' => 'other', 'model' => 'm2', 'api_key' => 'private-two'])->assertCreated();
        $response = $this->getJson("/workspaces/{$workspace->id}/providers")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'openai-compatible')->assertJsonMissingPath('data.0.api_key');
        $this->assertStringNotContainsString('private-one', $response->getContent());
        $this->assertStringNotContainsString('private-two', $response->getContent());
        $raw = \Illuminate\Support\Facades\DB::table('provider_configurations')->where('workspace_id', $workspace->id)->value('api_key');
        $this->assertNotSame('private-one', $raw);
        $this->assertSame('private-one', \Illuminate\Support\Facades\Crypt::decryptString($raw));
    }

    public function test_updating_existing_type_preserves_empty_or_missing_key(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'admin']);
        $url = "/workspaces/{$workspace->id}/providers";
        $this->actingAs($user)->postJson($url, ['type' => 'openai-compatible', 'model' => 'old', 'api_key' => 'initial-secret'])->assertCreated();
        $original = \Illuminate\Support\Facades\DB::table('provider_configurations')->value('api_key');
        foreach ([['api_key' => ''], [], ['api_key' => null]] as $extra) {
            $this->postJson($url, array_merge(['type' => 'openai-compatible', 'model' => 'new'], $extra))->assertOk()->assertJsonPath('data.model', 'new')->assertJsonMissingPath('data.api_key');
            $this->assertSame($original, \Illuminate\Support\Facades\DB::table('provider_configurations')->value('api_key'));
        }
        $this->postJson($url, ['type' => 'openai-compatible', 'model' => 'new', 'api_key' => 'replacement-secret'])->assertOk();
        $raw = \Illuminate\Support\Facades\DB::table('provider_configurations')->value('api_key');
        $this->assertSame('replacement-secret', \Illuminate\Support\Facades\Crypt::decryptString($raw));
        $this->assertDatabaseCount('provider_configurations', 1);
    }

    public function test_access_requires_owner_or_admin_in_the_target_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $url = "/workspaces/{$workspace->id}/providers";
        $payload = ['type' => 'custom', 'model' => 'm', 'api_key' => 'value'];
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url, $payload)->assertUnauthorized();
        foreach ([null, 'member', 'unknown', 'Owner'] as $role) {
            $user = User::factory()->create();
            $other->users()->attach($user, ['role' => 'owner']);
            if ($role !== null) {
                $workspace->users()->attach($user, ['role' => $role]);
            }
            $this->actingAs($user)->getJson($url)->assertForbidden();
            $this->postJson($url, $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('provider_configurations', 0);
    }

    public function test_validation_rejects_invalid_configuration(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $url = "/workspaces/{$workspace->id}/providers";
        $this->actingAs($user)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['type', 'model']);
        $this->postJson($url, ['type' => 'openai-compatible', 'model' => 'm'])->assertUnprocessable()->assertJsonValidationErrors(['api_key']);
        $this->postJson($url, ['type' => 'custom', 'model' => [], 'api_key' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors(['type', 'model', 'api_key']);
        $this->assertDatabaseCount('provider_configurations', 0);
    }

    public function test_provider_type_must_be_supported(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/providers", [
            'type' => 'custom', 'model' => 'manual-model', 'api_key' => 'test-secret',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_provider_name_and_endpoint_are_saved_without_returning_secret(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/providers", [
            'type' => 'openai-compatible', 'name' => 'Kitchen AI',
            'endpoint' => 'https://api.example.com/v1', 'model' => 'manual-model', 'api_key' => 'test-secret',
        ])->assertCreated()->assertJsonPath('data.name', 'Kitchen AI')
            ->assertJsonPath('data.endpoint', 'https://api.example.com/v1')
            ->assertJsonMissingPath('data.api_key');
    }

    public function test_endpoint_rejects_non_https_private_or_credential_urls(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $payload = ['type' => 'openai-compatible', 'name' => 'x', 'model' => 'm', 'api_key' => 's'];
        foreach (['http://api.example.com', 'https://127.0.0.1/v1', 'https://user:pass@example.com/v1', 'ftp://example.com'] as $endpoint) {
            $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/providers", $payload + ['endpoint' => $endpoint])
                ->assertUnprocessable()->assertJsonValidationErrors(['endpoint']);
        }
    }

    public function test_owner_can_save_a_manual_provider_configuration(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/providers", [
            'type' => 'openai-compatible', 'model' => 'manual-model', 'api_key' => 'test-secret',
        ])->assertCreated()->assertJsonPath('data.type', 'openai-compatible')
            ->assertJsonPath('data.model', 'manual-model')->assertJsonMissingPath('data.api_key');
    }

    public function test_communication_mode_is_saved_and_defaults_to_chat(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $url = "/workspaces/{$workspace->id}/providers";

        $this->actingAs($user)->postJson($url, [
            'type' => 'openai-compatible', 'communication_mode' => 'response',
            'model' => 'm', 'api_key' => 'secret',
        ])->assertCreated()->assertJsonPath('data.communication_mode', 'response');

        // Omitting the field keeps backward compatibility with a chat default.
        $this->postJson($url, ['type' => 'other', 'model' => 'm', 'api_key' => 'secret'])
            ->assertCreated()->assertJsonPath('data.communication_mode', 'chat');

        $this->postJson($url, ['type' => 'other', 'communication_mode' => 'invalid', 'model' => 'm', 'api_key' => 'secret'])
            ->assertUnprocessable()->assertJsonValidationErrors(['communication_mode']);
    }
}
