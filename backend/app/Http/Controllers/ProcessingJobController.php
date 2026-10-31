<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetVersion;
use App\Models\ProcessingJob;
use App\Models\Workspace;
use App\Services\ImageEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessingJobController extends Controller
{
    public function store(Request $request, Workspace $workspace, Asset $asset, AssetVersion $version, ImageEditor $editor)
    {
        Gate::authorize('upload', $workspace);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        // Optional edit instruction. Bounded length; AI editors may use it,
        // the identity fallback ignores it.
        $validated = $request->validate(['prompt' => ['nullable', 'string', 'max:2000']]);
        $prompt = $validated['prompt'] ?? null;
        [$job, $original] = DB::transaction(function () use ($request, $workspace, $asset, $version, $prompt) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $version = AssetVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($asset->workspace_id === $workspace->id && $version->asset_id === $asset->id, 403);
            abort_unless($asset->status === 'committed' && $version->status === 'draft', 409);
            try {
                $original = Storage::disk('asset_originals')->get($asset->path);
            } catch (\League\Flysystem\UnableToReadFile $exception) {
                abort(409, 'Original is unavailable');
            }
            abort_unless(is_string($original) && hash_equals($asset->sha256, hash('sha256', $original)) && hash_equals($version->original_sha256, hash('sha256', $original)), 409);
            $job = ProcessingJob::create(['workspace_id' => $workspace->id, 'asset_id' => $asset->id, 'asset_version_id' => $version->id, 'requested_by' => $request->user()->id, 'status' => 'queued', 'original_sha256' => $version->original_sha256, 'prompt' => $prompt]);
            return [$job, $original];
        });
        try {
            $edited = $editor->render($original, $prompt);
            $key = 'processing/'.Str::uuid().'.'.$edited->ext;
            $disk = Storage::disk('asset_originals');
            if (! $disk->put($key, $edited->bytes, ['visibility' => 'private'])) { throw new \RuntimeException('Write failed'); }
            $stored = $disk->get($key);
            if (! is_string($stored) || ! hash_equals(hash('sha256', $edited->bytes), hash('sha256', $stored))) { throw new \RuntimeException('Output verification failed'); }
            $job->update(['status' => 'succeeded', 'output_key' => $key, 'output_sha256' => hash('sha256', $stored), 'output_mime' => $edited->mime, 'output_ext' => $edited->ext]);
        } catch (Throwable $exception) {
            // A commit may have succeeded despite an exception: do not delete an artifact.
            $job->refresh();
            if ($job->status === 'queued') { $job->update(['status' => 'failed']); }
        }
        return response()->json(['data' => $job->fresh()], 201);
    }
}
