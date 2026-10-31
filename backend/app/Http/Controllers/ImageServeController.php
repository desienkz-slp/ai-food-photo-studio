<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ProcessingJob;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Serve asset originals and processing-job outputs as images for in-app
 * preview. Bytes are verified against the stored SHA-256 before serving so a
 * corrupted file is never shown. Private cache only.
 */
class ImageServeController extends Controller
{
    public function original(Workspace $workspace, Asset $asset)
    {
        Gate::authorize('view', $workspace);
        abort_unless($asset->workspace_id === $workspace->id && $asset->status === 'committed', 404);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        $bytes = $this->readVerified($asset->path, $asset->sha256);
        // Originals are only ever PNG or JPEG (enforced at upload).
        $info = @getimagesizefromstring($bytes);
        $mime = ($info && in_array($info['mime'], ['image/png', 'image/jpeg'], true)) ? $info['mime'] : 'application/octet-stream';
        return $this->imageResponse($bytes, $mime);
    }

    public function output(Workspace $workspace, Asset $asset, ProcessingJob $job)
    {
        Gate::authorize('view', $workspace);
        abort_unless($job->workspace_id === $workspace->id && $job->asset_id === $asset->id, 404);
        abort_unless($job->status === 'succeeded' && $job->output_key && $job->output_sha256, 404);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        $bytes = $this->readVerified($job->output_key, $job->output_sha256);
        $mime = $job->output_mime ?: 'application/octet-stream';
        return $this->imageResponse($bytes, $mime);
    }

    private function readVerified(string $key, string $expectedSha): string
    {
        try {
            $bytes = Storage::disk('asset_originals')->get($key);
        } catch (\League\Flysystem\UnableToReadFile $exception) {
            abort(409, 'Image is unavailable or corrupted');
        }
        abort_unless(is_string($bytes) && hash_equals($expectedSha, hash('sha256', $bytes)), 409, 'Image is unavailable or corrupted');
        return $bytes;
    }

    private function imageResponse(string $bytes, string $mime)
    {
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
