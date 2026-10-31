<?php
namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AiImageCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Create an image from a text prompt and store it as a committed asset so it
 * enters the normal version/approval/export pipeline. Mirrors the storage
 * guarantees of AssetController (sha256 + verified write).
 */
class ImageCreateController extends Controller
{
    public function store(Request $request, Workspace $workspace, AiImageCreator $creator): JsonResponse
    {
        Gate::authorize('upload', $workspace);
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'size' => ['nullable', 'string', 'in:512x512,1024x1024'],
        ]);

        try {
            $image = $creator->create($validated['prompt'], $validated['size'] ?? null);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Gambar tidak dapat dibuat.'], 502);
        }

        $path = 'originals/'.Str::uuid();
        $checksum = hash('sha256', $image->bytes);
        $disk = Storage::disk('asset_originals');
        $absolute = $disk->path($path);
        if (! is_dir(dirname($absolute)) && ! @mkdir(dirname($absolute), 0700, true) && ! is_dir(dirname($absolute))) {
            abort(503);
        }
        $handle = @fopen($absolute, 'x');
        if ($handle === false) { abort(file_exists($absolute) ? 409 : 503); }
        try {
            if (! chmod($absolute, 0600) || fwrite($handle, $image->bytes) !== strlen($image->bytes)) {
                throw new \RuntimeException('Unable to store generated image');
            }
            fclose($handle);
            $handle = null;
        } catch (\Throwable $exception) {
            if (is_resource($handle)) { fclose($handle); }
            @unlink($absolute);
            throw $exception;
        }
        $stored = @hash_file('sha256', $absolute);
        if ($stored === false || ! hash_equals($checksum, $stored)) {
            @unlink($absolute);
            abort(409, 'Generated image could not be verified');
        }

        $id = DB::table('assets')->insertGetId([
            'workspace_id' => $workspace->id,
            'idempotency_key' => (string) Str::uuid(),
            'path' => $path,
            'sha256' => $checksum,
            'status' => 'committed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['data' => ['id' => $id, 'sha256' => $checksum]], 201);
    }
}
