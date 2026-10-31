<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AssetVersionController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        Gate::authorize('view', $workspace);
        return response()->json(['data' => $workspace->assets()->where('status', 'committed')->with('versions')->get()]);
    }

    public function store(Request $request, Workspace $workspace, Asset $asset)
    {
        Gate::authorize('upload', $workspace);
        abort_unless($asset->workspace_id === $workspace->id, 403);
        $validated = $request->validate(['label' => ['nullable', 'string', 'max:255']]);
        $label = $validated['label'] ?? null;
        abort_unless($asset->status === 'committed', 409, 'Asset is pending reconciliation');
        abort_unless(config('filesystems.disks.asset_originals.driver') === 'local', 503);
        $checksum = @hash_file('sha256', Storage::disk('asset_originals')->path($asset->path));
        abort_unless($checksum !== false && hash_equals($asset->sha256, $checksum), 409, 'Original is unavailable or corrupted');

        $version = $asset->versions()->first();
        if (! $version) {
            try {
                $version = $asset->versions()->create(['original_sha256' => $asset->sha256, 'status' => 'draft', 'label' => $label]);
            } catch (UniqueConstraintViolationException $exception) {
                $version = $asset->versions()->first();
                if (! $version) { throw $exception; }
            }
        }
        abort_unless(hash_equals($version->original_sha256, $asset->sha256) && $version->label === $label, 409, 'Version conflicts with existing metadata');
        return response()->json(['data' => $version], 201);
    }
}
