<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_requires_a_valid_idempotency_key(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])->assertUnprocessable();
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_committed_retry_returns_same_asset_without_duplicate(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $file = UploadedFile::fake()->image('meal.png');
        $url = "/workspaces/{$workspace->id}/assets";
        $first = $this->actingAs($user)->withHeader('Idempotency-Key', 'retry-1')->postJson($url, ['file' => $file])->assertCreated();
        $second = $this->postJson($url, ['file' => $file])->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('assets', 1);
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
    }

    public function test_committed_retry_rejects_missing_original_without_modifying_database(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $file = UploadedFile::fake()->image('meal.png');
        $url = "/workspaces/{$workspace->id}/assets";
        $this->actingAs($user)->withHeader('Idempotency-Key', 'missing')->postJson($url, ['file' => $file])->assertCreated();
        $asset = DB::table('assets')->first();
        Storage::disk('asset_originals')->delete($asset->path);

        $this->postJson($url, ['file' => $file])->assertStatus(409);
        $this->assertEquals($asset, DB::table('assets')->first());
        $this->assertDatabaseCount('assets', 1);
        Storage::disk('asset_originals')->assertMissing($asset->path);
    }

    public function test_committed_retry_rejects_corrupted_original_without_modifying_it(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $file = UploadedFile::fake()->image('meal.png');
        $url = "/workspaces/{$workspace->id}/assets";
        $this->actingAs($user)->withHeader('Idempotency-Key', 'corrupt')->postJson($url, ['file' => $file])->assertCreated();
        $asset = DB::table('assets')->first();
        Storage::disk('asset_originals')->put($asset->path, 'corrupted original');

        $this->postJson($url, ['file' => $file])->assertStatus(409);
        $this->assertSame('corrupted original', Storage::disk('asset_originals')->get($asset->path));
        $this->assertEquals($asset, DB::table('assets')->first());
        $this->assertDatabaseCount('assets', 1);
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
    }

    public function test_pending_retry_does_not_touch_original(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $file = UploadedFile::fake()->image('meal.png');
        DB::table('assets')->insert(['workspace_id' => $workspace->id, 'idempotency_key' => 'pending', 'path' => 'originals/existing', 'sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('asset_originals')->put('originals/existing', 'do not touch');
        $this->actingAs($user)->withHeader('Idempotency-Key', 'pending')->postJson("/workspaces/{$workspace->id}/assets", ['file' => $file])->assertStatus(409);
        $this->assertSame('do not touch', Storage::disk('asset_originals')->get('originals/existing'));
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_same_key_rejects_different_payload(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $url = "/workspaces/{$workspace->id}/assets";
        $this->actingAs($user)->withHeader('Idempotency-Key', 'payload')->postJson($url, ['file' => UploadedFile::fake()->image('a.png', 10, 10)])->assertCreated();
        $this->postJson($url, ['file' => UploadedFile::fake()->image('b.png', 11, 11)])->assertStatus(409);
        $this->assertDatabaseCount('assets', 1);
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
    }

    public function test_same_key_can_be_used_in_different_workspaces(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $one = Workspace::factory()->create();
        $two = Workspace::factory()->create();
        $one->users()->attach($user, ['role' => 'owner']);
        $two->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->withHeader('Idempotency-Key', 'shared')->postJson("/workspaces/{$one->id}/assets", ['file' => UploadedFile::fake()->image('a.png')])->assertCreated();
        $this->postJson("/workspaces/{$two->id}/assets", ['file' => UploadedFile::fake()->image('b.png')])->assertCreated();
        $this->assertDatabaseCount('assets', 2);
    }

    public function test_rejects_malformed_idempotency_keys(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        foreach (['bad key', str_repeat('a', 129), ''] as $key) {
            $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('a.png')])->assertUnprocessable();
        }
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_guest_and_nonmember_cannot_upload(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $workspace = Workspace::factory()->create();
        $this->postJson("/workspaces/{$workspace->id}/assets", [])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson("/workspaces/{$workspace->id}/assets", [])->assertForbidden();
        $this->assertCount(0, Storage::disk('asset_originals')->allFiles());
    }

    public function test_rejects_fake_image_and_oversize(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->createWithContent('fake.png', 'not an image')])->assertUnprocessable();
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->create('large.png', 10241, 'image/png')])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('asset_originals')->allFiles());
    }

    public function test_rejects_image_over_four_million_pixels(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", [
            'file' => UploadedFile::fake()->image('large.png', 2001, 2000),
        ])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('asset_originals')->allFiles());
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_accepts_image_at_pixel_limit(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", [
            'file' => UploadedFile::fake()->image('boundary.png', 2000, 2000),
        ])->assertCreated();
    }

    public function test_rejects_truncated_image_with_valid_header(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $valid = UploadedFile::fake()->image('valid.png', 20, 20);
        $truncated = substr(file_get_contents($valid->getRealPath()), 0, 33);
        $file = UploadedFile::fake()->createWithContent('corrupt.png', $truncated);
        $this->assertNotFalse(@getimagesize($file->getRealPath()));
        $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => $file])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('asset_originals')->allFiles());
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_upload_route_limits_requests_per_user(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", [])->assertUnprocessable();
        }
        $this->postJson("/workspaces/{$workspace->id}/assets", [])->assertTooManyRequests();
        $other = User::factory()->create();
        $workspace->users()->attach($other, ['role' => 'owner']);
        $this->actingAs($other)->postJson("/workspaces/{$workspace->id}/assets", [])->assertUnprocessable();
    }

    public function test_original_cannot_be_overwritten_on_key_collision(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        \Illuminate\Support\Str::createUuidsUsing(fn () => \Illuminate\Support\Str::of('00000000-0000-4000-8000-000000000001'));
        try {
            Storage::disk('asset_originals')->put('originals/00000000-0000-4000-8000-000000000001', 'original');
            $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])->assertStatus(409);
            $this->assertSame('original', Storage::disk('asset_originals')->get('originals/00000000-0000-4000-8000-000000000001'));
        } finally {
            \Illuminate\Support\Str::createUuidsNormally();
        }
    }

    public function test_original_is_kept_when_status_update_commits_then_throws(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $updates = 0;
        app()->instance(AssetController::class, (new AssetController)->setAssetStatusUpdater(function (int $id) use (&$updates): int {
            $updates++;
            DB::table('assets')->where('id', $id)->update(['status' => 'committed', 'updated_at' => now()]);
            throw new \RuntimeException('ambiguous commit');
        }));

        try {
            $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')]);
            $response->assertServerError();
            $this->assertInstanceOf(\RuntimeException::class, $response->exception);
            $this->assertSame('ambiguous commit', $response->exception->getMessage());
        } finally {
            $this->assertSame(1, $updates);
            $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
            $this->assertDatabaseHas('assets', ['status' => 'committed']);
        }
    }

    public function test_status_update_zero_rows_fails_without_deleting_original(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $updates = 0;
        app()->instance(AssetController::class, (new AssetController)->setAssetStatusUpdater(function () use (&$updates): int {
            $updates++;
            return 0;
        }));

        try {
            $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])->assertServerError();
        } finally {
            $this->assertSame(1, $updates);
            $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
        }
    }

    public function test_member_upload_is_forbidden_without_side_effects(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'member']);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'member-denied')
            ->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])
            ->assertForbidden();
        $this->assertDatabaseCount('assets', 0);
        $this->assertCount(0, Storage::disk('asset_originals')->allFiles());
    }

    public function test_admin_can_upload(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'admin']);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'admin-upload')
            ->postJson("/workspaces/{$workspace->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])
            ->assertCreated();
    }

    public function test_owner_cannot_upload_to_another_workspace(): void
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'cross-workspace')
            ->postJson("/workspaces/{$other->id}/assets", ['file' => UploadedFile::fake()->image('meal.png')])
            ->assertForbidden();
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_member_uploads_private_original_with_checksum(): void
    {
        $this->withHeader('Idempotency-Key', 'test-key');
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $file = UploadedFile::fake()->image('meal.png', 10, 10);
        $checksum = hash_file('sha256', $file->getRealPath());
        $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/assets", ['file' => $file]);
        $response->assertCreated()->assertJsonPath('data.sha256', $checksum);
        $this->assertDatabaseHas('assets', ['workspace_id' => $workspace->id, 'sha256' => $checksum]);
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
    }
}
