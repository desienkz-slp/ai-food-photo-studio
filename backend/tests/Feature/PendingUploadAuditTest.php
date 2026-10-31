<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PendingUploadAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_pending_existence_and_age_without_disclosing_or_mutating_data(): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('asset_originals');
        $workspace = Workspace::factory()->create();
        $base = ['workspace_id' => $workspace->id, 'sha256' => str_repeat('a', 64), 'created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(3)];
        $present = DB::table('assets')->insertGetId($base + ['path' => 'originals/private-present', 'status' => 'pending', 'idempotency_key' => 'secret-key']);
        $missing = DB::table('assets')->insertGetId($base + ['path' => 'originals/private-missing', 'status' => 'pending']);
        DB::table('assets')->insert($base + ['path' => 'originals/committed', 'status' => 'committed']);
        Storage::disk('asset_originals')->put('originals/private-present', 'active upload bytes');
        $before = DB::table('assets')->orderBy('id')->get()->toArray();

        $this->assertSame(0, Artisan::call('uploads:audit-pending'));
        $output = Artisan::output();
        $this->assertStringContainsString("$present | pending | 180 | yes", $output);
        $this->assertStringContainsString("$missing | pending | 180 | no", $output);
        $this->assertStringNotContainsString('committed', $output);
        $this->assertStringNotContainsString('originals/', $output);
        $this->assertStringNotContainsString('secret-key', $output);
        $this->assertStringNotContainsString(str_repeat('a', 64), $output);
        $this->assertEquals($before, DB::table('assets')->orderBy('id')->get()->toArray());
        $this->assertSame('active upload bytes', Storage::disk('asset_originals')->get('originals/private-present'));
        Storage::disk('asset_originals')->assertMissing('originals/private-missing');
    }
}
