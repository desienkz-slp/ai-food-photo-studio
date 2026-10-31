<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('asset_originals');
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->users()->attach($user, ['role' => 'owner']);
        $asset = $this->actingAs($user)->withHeader('Idempotency-Key', 'processing')
            ->postJson("/workspaces/$workspace->id/assets", ['file' => UploadedFile::fake()->image('food.png', 2, 2)])
            ->assertCreated()->json('data.id');
        $version = $this->postJson("/workspaces/$workspace->id/assets/$asset/versions")
            ->assertCreated()->json('data.id');
        return [$user, $workspace, $asset, $version, "/workspaces/$workspace->id/assets/$asset/versions/$version"];
    }

    public function test_processing_job_creates_verified_candidate(): void
    {
        [$owner, $workspace, $asset, $version, $base] = $this->fixture();
        $response = $this->actingAs($owner)->postJson("$base/processing-jobs")->assertCreated();
        $this->assertSame('succeeded', $response->json('data.status'));
        $this->assertNotEmpty($response->json('data.output_key'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('processing_jobs')->count());
    }

    public function test_approval_requires_candidate_before_approval(): void
    {
        [$owner, $workspace, $asset, $version, $base] = $this->fixture();
        $this->actingAs($owner)->postJson("$base/approval")->assertStatus(409);
        $this->assertDatabaseCount('processing_jobs', 0);
        $this->assertCount(1, Storage::disk('asset_originals')->allFiles());
    }

    public function test_unauthorized_processing_requests_remain_denied_without_side_effects(): void
    {
        [$owner, $workspace, $asset, $version, $base] = $this->fixture();
        foreach (['member', 'viewer'] as $role) {
            $user = User::factory()->create();
            $workspace->users()->attach($user, ['role' => $role]);
            $this->actingAs($user)->postJson("$base/processing-jobs")->assertForbidden();
        }
        $this->assertDatabaseCount('processing_jobs', 0);
    }
}
