<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Workspace;
use App\Services\AiVisionAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class VisionController extends Controller
{
    public function analyze(Request $request, Workspace $workspace, Asset $asset, AiVisionAnalyzer $analyzer): JsonResponse
    {
        Gate::authorize('upload', $workspace);
        abort_unless($asset->workspace_id === $workspace->id && $asset->status === 'committed', 404);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        $validated = $request->validate(['prompt' => ['nullable', 'string', 'max:2000']]);

        try {
            $bytes = Storage::disk('asset_originals')->get($asset->path);
        } catch (\League\Flysystem\UnableToReadFile $exception) {
            abort(409, 'Original is unavailable');
        }
        abort_unless(is_string($bytes) && hash_equals($asset->sha256, hash('sha256', $bytes)), 409, 'Original is unavailable or corrupted');
        $info = @getimagesizefromstring($bytes);
        $mime = ($info && in_array($info['mime'], ['image/png', 'image/jpeg'], true)) ? $info['mime'] : 'image/png';

        try {
            $analysis = $analyzer->analyze($bytes, $mime, $validated['prompt'] ?? null);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Analisis gambar tidak dapat dilakukan.'], 502);
        }
        return response()->json(['data' => ['analysis' => $analysis]]);
    }
}
