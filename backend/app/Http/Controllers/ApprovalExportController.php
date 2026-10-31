<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetVersion;
use App\Models\Workspace;
use App\Models\ProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ApprovalExportController extends Controller
{
    private function lifecycleUnavailable()
    {
        abort(503, 'Lifecycle unavailable');
    }

    public function approve(Request $request, Workspace $workspace, Asset $asset, AssetVersion $version)
    {
        Gate::authorize('upload', $workspace);
        return DB::transaction(function () use ($request, $workspace, $asset, $version) {
            $asset=Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail(); $version=AssetVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($asset->workspace_id===$workspace->id && $version->asset_id===$asset->id,403); abort_unless($version->status==='draft',409);
            $job=ProcessingJob::whereKey($request->input('job_id'))->where('workspace_id',$workspace->id)->where('asset_id',$asset->id)->where('asset_version_id',$version->id)->where('status','succeeded')->lockForUpdate()->first(); abort_unless($job,409,'Candidate unavailable');
            abort_unless(is_string($request->input('output_sha256')) && hash_equals($job->output_sha256,$request->input('output_sha256')),409,'Candidate mismatch'); abort_unless($job->output_key && Storage::disk('asset_originals')->exists($job->output_key),409);
            $bytes=Storage::disk('asset_originals')->get($job->output_key); abort_unless(hash_equals($job->output_sha256,hash('sha256',$bytes)),409,'Candidate corrupted'); abort_unless(!DB::table('asset_version_approvals')->where('asset_version_id',$version->id)->exists(),409);
            DB::table('asset_version_approvals')->insert(['workspace_id'=>$workspace->id,'asset_id'=>$asset->id,'asset_version_id'=>$version->id,'original_sha256'=>$version->original_sha256,'candidate_job_id'=>$job->id,'candidate_sha256'=>$job->output_sha256,'approver_id'=>$request->user()->id,'approved_at'=>now()]); DB::table('asset_versions')->where('id',$version->id)->update(['status'=>'approved']); return response()->json(['data'=>['job_id'=>$job->id,'output_sha256'=>$job->output_sha256]],201);
        });
    }

    private function verifyApproved(Workspace $workspace, Asset $asset, AssetVersion $version): void
    {
        abort_unless($asset->workspace_id === $workspace->id && $version->asset_id === $asset->id, 403);
        abort_unless($asset->status === 'committed', 409);
        abort_unless(DB::table('asset_version_approvals')->where('asset_version_id', $version->id)->exists(), 409, 'Version is not approved');
    }

    public function export(Workspace $workspace, Asset $asset, AssetVersion $version)
    {
        Gate::authorize('upload', $workspace);
        $key = null;
        try {
        return DB::transaction(function () use ($workspace, $asset, $version, &$key) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $version = AssetVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $this->verifyApproved($workspace, $asset, $version);
            abort_unless($version->status === 'approved', 409, 'Version is not approved');
            $approval = DB::table('asset_version_approvals')->where('asset_version_id', $version->id)->first();
            abort_unless($approval && $approval->workspace_id === $workspace->id && $approval->asset_id === $asset->id && hash_equals($approval->original_sha256, $version->original_sha256), 409, 'Version is not approved');
            abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
            $job=ProcessingJob::whereKey($approval->candidate_job_id)->where('status','succeeded')->first(); abort_unless($job && hash_equals($approval->candidate_sha256,$job->output_sha256) && $job->output_key,409,'Candidate unavailable');
            $contents = Storage::disk('asset_originals')->get($job->output_key);
            abort_unless(is_string($contents) && hash_equals($job->output_sha256, hash('sha256', $contents)), 409, 'Original is unavailable or corrupted');
            $key = (string) \Illuminate\Support\Str::uuid();
            abort_unless(Storage::disk('asset_originals')->put($key, $contents), 503, 'Export write failed');
            $written = Storage::disk('asset_originals')->get($key);
            $digest = hash('sha256', $written);
            abort_unless(hash_equals($job->output_sha256, $digest), 409, 'Export is corrupted');
            $id = (string) \Illuminate\Support\Str::uuid();
            DB::table('asset_exports')->insert(['id' => $id, 'workspace_id' => $workspace->id, 'asset_version_id' => $version->id, 'artifact_key' => $key, 'sha256' => $digest, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            abort_unless(DB::table('asset_versions')->where('id', $version->id)->where('status', 'approved')->update(['status' => 'exported']) === 1, 409);
            return response()->json(['data' => ['id' => $id, 'workspace_id' => $workspace->id, 'asset_id' => $asset->id, 'asset_version_id' => $version->id, 'original_sha256' => $version->original_sha256, 'artifact_sha256' => $digest]]);
        });
        } catch (\Throwable $e) {
            // A commit error may be ambiguous: only remove a file when the database
            // can confirm that no committed metadata references it.
            if ($key !== null) {
                try {
                    if (! DB::table('asset_exports')->where('artifact_key', $key)->exists()) {
                        Storage::disk('asset_originals')->delete($key);
                    }
                } catch (\Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $e;
        }
    }

    public function download(Workspace $workspace, string $export)
    {
        Gate::authorize('upload', $workspace);
        $record = DB::table('asset_exports')->where('id', $export)->where('workspace_id', $workspace->id)->first();
        abort_unless($record, 404);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        try {
            $contents = Storage::disk('asset_originals')->get($record->artifact_key);
        } catch (\League\Flysystem\UnableToReadFile $e) {
            abort(409, 'Export is unavailable or corrupted');
        }
        abort_unless(is_string($contents) && hash_equals($record->sha256, hash('sha256', $contents)), 409, 'Export is unavailable or corrupted');
        return response($contents, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="export.bin"', 'Cache-Control' => 'private, no-store']);
    }
}
